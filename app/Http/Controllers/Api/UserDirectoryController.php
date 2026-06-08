<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Studio;
use App\Models\User;
use App\Services\StudioStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserDirectoryController extends Controller
{
    /** Yetkili kullanıcının hiyerarşik kapsamındaki tüm çalışanları döndürür. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole([UserRole::Admin, UserRole::Yonetici, UserRole::Supervisor]), 403);

        return $this->staffResponse($user, $user->staffScopeStudioIds());
    }

    /** Mobil admin paneli için tüm stüdyolardaki çalışanları döndürür */
    public function adminIndex(Request $request): JsonResponse
    {
        return $this->staffResponse(
            $request->user(),
            Studio::query()->pluck('id')->map('intval')->all()
        );
    }

    /**
     * @param  array<int, int>  $studioIds
     */
    private function staffResponse(User $viewer, array $studioIds): JsonResponse
    {
        $visibleRoles = array_map(
            static fn (UserRole $role): string => $role->value,
            $viewer->manageableStaffRoles()
        );

        $studios = Studio::query()
            ->whereIn('id', $studioIds)
            ->with(['users' => fn ($query) => $query
                ->wherePivotIn('role', $visibleRoles)
                ->orderBy('users.name')])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $studios->flatMap(fn (Studio $studio) => $studio->users->map(
                fn (User $user): array => [
                    'id'            => $user->id,
                    'name'          => $user->fullName(),
                    'email'         => $user->email,
                    'phone'         => $user->phone,
                    'role'          => $user->pivot->role,
                    'profile_image' => $user->profile_image,
                    'studio_id'     => $studio->id,
                    'status'        => $user->pivot->work_status,
                    'is_active'     => (bool) $user->pivot->is_active,
                    'is_banned'     => $user->banned_at !== null,
                    'ban_reason'    => $user->ban_reason,
                ]
            ))->values(),
        ]);
    }

    public function userOptions(Request $request): JsonResponse
    {
        $authUser = $request->user();
        abort_unless($authUser?->hasAnyRole([UserRole::Admin, UserRole::Yonetici, UserRole::Supervisor]), 403);

        $requestedRoles = collect(explode(',', (string) $request->query('roles')))
            ->filter()
            ->map(fn (string $role): string => UserRole::fromValue($role)->value)
            ->values();
        $manageableRoles = collect($authUser->manageableStaffRoles())
            ->map(static fn (UserRole $role): string => $role->value);
        $roles = $requestedRoles->intersect($manageableRoles)->values();

        $companyId = $request->query('company_id');
        $scopeStudioIds = $authUser->staffScopeStudioIds();

        $users = User::query()
            ->whereNull('banned_at')
            ->when(
                $requestedRoles->isNotEmpty() && $roles->isEmpty(),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->when(
                $roles->isNotEmpty(),
                fn ($q) => $q->where(function ($q) use ($roles): void {
                    $q->whereIn('role', $roles->all())
                        ->orWhereIn('requested_staff_role', $roles->all());
                })
            )
            ->when(
                ! $authUser?->hasRole(UserRole::Admin),
                fn ($q) => $q->whereHas(
                    'studios',
                    fn ($sq) => $sq->whereIn('studios.id', $scopeStudioIds)
                )
            )
            ->when($companyId, fn ($q) => $q->whereHas(
                'studios',
                fn ($sq) => $sq->whereHas(
                    'shop',
                    fn ($shq) => $shq->where('company_id', (int) $companyId)
                )
            ))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'role' => $user->requested_staff_role?->value ?? $user->role?->value,
            ])->values(),
        ]);
    }

    public function store(Request $request, StudioStaffService $studioStaffService): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole([
            UserRole::Admin,
            UserRole::Yonetici,
            UserRole::Supervisor,
        ]), 403);

        $validated = $request->validate([
            'name'     => ['nullable', 'string', 'max:255'],
            'surname'  => ['nullable', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'role'     => ['required', 'string', 'in:supervisor,designer,artist,info,sofor,calisan'],
            'studio_id' => [
                Rule::requiredIf(fn (): bool => $this->usesStudioAssignment($request->input('role'))),
                'nullable',
                'integer',
                'exists:studios,id',
            ],
            'shop_id' => [
                Rule::requiredIf(fn (): bool => ! $this->usesStudioAssignment($request->input('role')) && ! $request->filled('studio_id')),
                'nullable',
                'integer',
                'exists:shops,id',
            ],
            'email'    => ['required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        $role = UserRole::fromValue($validated['role']);
        abort_unless($request->user()?->canManageStaffRole($role), 403);

        if ($this->usesStudioAssignment($role)) {
            $studio = Studio::query()->findOrFail($validated['studio_id']);
            abort_unless($this->canAccessStaffInStudio($request->user(), $studio), 403);

            $result = $studioStaffService->createOrAttach($studio, $role, $validated, $request->user());
            $isInvitation = $result['action'] === 'invited_existing_freelancer';

            return response()->json([
                'message' => $isInvitation
                    ? 'Kullanıcıya çalışanlık daveti gönderildi.'
                    : 'Kullanıcı başarıyla oluşturuldu.',
                'data' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->fullName(),
                    'email' => $result['user']->email,
                    'role' => $result['studio_role'],
                    'is_active' => ! $isInvitation,
                    'action' => $result['action'],
                    'invitation_id' => $result['invitation']->id ?? null,
                ],
            ], $isInvitation ? 202 : 201);
        }

        $shop = isset($validated['shop_id'])
            ? Shop::query()->with('studios')->findOrFail($validated['shop_id'])
            : Studio::query()->with('shop.studios')->findOrFail($validated['studio_id'])->shop;

        abort_if($shop === null, 422, 'Seçilen stüdyoya bağlı şube bulunamadı.');
        abort_unless($request->user()?->canManageShop($shop), 403);

        $result = $this->createOrAttachBranchStaff($shop, $role, $validated);

        return response()->json([
            'message' => 'Kullanıcı şubeye atandı.',
            'data' => [
                'id' => $result['user']->id,
                'name' => $result['user']->fullName(),
                'email' => $result['user']->email,
                'role' => $role->value,
                'shop_id' => $shop->id,
                'is_active' => true,
                'action' => $result['action'],
            ],
        ], $result['action'] === 'created_new_user' ? 201 : 200);
    }

    private function usesStudioAssignment(UserRole|string|null $role): bool
    {
        if ($role === null) {
            return false;
        }

        $role = $role instanceof UserRole ? $role : UserRole::fromValue($role);

        return in_array($role, [UserRole::Artist, UserRole::Designer], true);
    }

    private function createOrAttachBranchStaff(Shop $shop, UserRole $role, array $attributes): array
    {
        abort_unless(in_array($role, [UserRole::Supervisor, UserRole::Info, UserRole::Sofor, UserRole::Calisan], true), 422);

        return DB::transaction(function () use ($shop, $role, $attributes): array {
            $user = User::query()->where('email', $attributes['email'])->first();
            $action = 'attached_existing_user';

            if ($user === null) {
                if (blank($attributes['password'] ?? null)) {
                    throw ValidationException::withMessages([
                        'password' => ['Yeni kullanıcı oluşturmak için şifre zorunludur.'],
                    ]);
                }

                $user = User::query()->create([
                    'name' => $attributes['name'] ?? '',
                    'surname' => $attributes['surname'] ?? null,
                    'email' => $attributes['email'],
                    'phone' => $attributes['phone'] ?? null,
                    'password' => $attributes['password'],
                    'role' => $role,
                ]);
                $action = 'created_new_user';
            } else {
                $user->fill(array_filter([
                    'name' => $attributes['name'] ?? null,
                    'surname' => $attributes['surname'] ?? null,
                    'phone' => $attributes['phone'] ?? null,
                ], static fn ($value) => $value !== null));
                $user->role = $role;
                $user->save();
            }

            if ($role === UserRole::Supervisor) {
                $shop->forceFill(['supervisor_user_id' => $user->id])->save();
            }

            $studios = $shop->studios()->get();
            if ($studios->isEmpty() && $role !== UserRole::Supervisor) {
                throw ValidationException::withMessages([
                    'shop_id' => ['Bu şubede personel atanacak stüdyo bulunamadı.'],
                ]);
            }

            foreach ($studios as $studio) {
                $membership = $studio->users()->where('users.id', $user->id)->first();
                $pivot = [
                    'role' => $role->value,
                    'work_status' => 'working',
                    'is_active' => true,
                    'joined_at' => now(),
                    'left_at' => null,
                ];

                if ($membership === null) {
                    $studio->users()->attach($user->id, $pivot);
                } else {
                    $studio->users()->updateExistingPivot($user->id, $pivot);
                }
            }

            return [
                'user' => $user->fresh(),
                'action' => $action,
            ];
        });
    }

    public function studioOptions(): JsonResponse
    {
        $user = request()->user();
        $studios = Studio::query()
            ->whereIn('id', $user?->staffScopeStudioIds() ?? [])
            ->get(['id', 'name']);

        return response()->json([
            'data' => $studios->map(fn (Studio $studio): array => [
                'id' => $studio->id,
                'name' => $studio->name,
            ])->values(),
        ]);
    }

    public function indexByStudio(Studio $studio): JsonResponse
    {
        abort_unless($this->canAccessStaffInStudio(request()->user(), $studio), 403);

        $visibleRoles = array_map(
            static fn (UserRole $role): string => $role->value,
            request()->user()->manageableStaffRoles()
        );
        $users = $studio->users()->wherePivotIn('role', $visibleRoles)->get();

        return response()->json([
            'data' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->pivot->role,
                'profile_image' => $user->profile_image,
                'studio_id' => $studio->id,
                'status' => $user->pivot->work_status,
                'is_active' => (bool) $user->pivot->is_active,
                'is_banned' => $user->banned_at !== null,
                'ban_reason' => $user->ban_reason,
            ])->values(),
        ]);
    }

    public function update(Request $request, Studio $studio, User $user, StudioStaffService $studioStaffService): JsonResponse
    {
        abort_unless($this->canAccessStaffInStudio($request->user(), $studio), 403);
        abort_if(
            ! $request->user()?->hasRole(UserRole::Admin)
                && $user->hasAnyRole([UserRole::Kullanici, UserRole::KullaniciRol]),
            403
        );

        $currentRole = $studio->users()->where('users.id', $user->id)->first()?->pivot?->role;
        abort_if($currentRole === null, 404);
        abort_unless($request->user()?->canManageStaffRole($currentRole), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'surname' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'role' => ['sometimes', 'string', 'in:admin,yonetici,supervisor,designer,artist,info,sofor,calisan'],
            'status' => ['sometimes', 'string', 'in:working,break,transfer'],
            'is_active' => ['sometimes', 'boolean'],
            'profile_image' => ['nullable', 'string', 'max:2048'],
        ]);

        if (isset($validated['role'])) {
            abort_unless($request->user()?->canManageStaffRole($validated['role']), 403);
        }

        $updatedUser = $studioStaffService->updateMembership(
            $studio,
            $user,
            \App\Enums\UserRole::fromValue($currentRole),
            $validated
        );

        $pivot = $studio->users()->where('users.id', $user->id)->first()->pivot;

        return response()->json([
            'message' => 'Kullanici guncellendi.',
            'data' => [
                'id' => $updatedUser->id,
                'name' => $updatedUser->fullName(),
                'email' => $updatedUser->email,
                'role' => $pivot->role,
                'profile_image' => $updatedUser->profile_image,
                'studio_id' => $studio->id,
                'status' => $pivot->work_status,
                'is_active' => (bool) $pivot->is_active,
            ],
        ]);
    }

    private function canAccessStaffInStudio(?User $user, Studio $studio): bool
    {
        if ($user === null) {
            return false;
        }

        return in_array($studio->id, $user->staffScopeStudioIds(), true);
    }
}
