<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Models\User;
use App\Services\StudioStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                fn ($sq) => $sq->where('company_id', (int) $companyId)
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

    public function lookupByProfileCode(Request $request, string $code): JsonResponse
    {
        $authUser = $request->user();
        abort_unless($authUser?->hasAnyRole([UserRole::Admin, UserRole::Yonetici, UserRole::Supervisor]), 403);

        $numericCode = preg_replace('/\D+/', '', $code) ?: '';
        abort_if($numericCode === '', 404);

        $user = User::query()
            ->whereKey((int) ltrim($numericCode, '0'))
            ->whereNull('banned_at')
            ->firstOrFail();

        $manageableRoles = collect($authUser->manageableStaffRoles())
            ->filter(fn (UserRole $role): bool => in_array($role, UserRole::studioRoles(), true))
            ->values();
        $inviteRoles = $authUser->is($user)
            ? collect()
            : $manageableRoles
                ->filter(fn (UserRole $role): bool => $user->isAvailableForStaffInvitation($role))
                ->values();
        $activeStudio = $user->studios()
            ->wherePivot('is_active', true)
            ->first(['studios.id', 'studios.name']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'profile_code' => $user->profileCode(),
                'name' => $user->fullName(),
                'email' => $user->email,
                'phone' => $user->phone,
                'username' => $user->username,
                'role' => $user->role?->value,
                'requested_staff_role' => $user->requested_staff_role?->value,
                'profile_role' => $user->profileRole()->value,
                'profile_role_label' => $user->profileRole()->label(),
                'profile_image' => $user->profile_image,
                'bio' => $user->bio,
                'specializations' => $user->specializations ?? [],
                'is_current_user' => $authUser->is($user),
                'is_available' => $activeStudio === null,
                'current_studio' => $activeStudio ? [
                    'id' => $activeStudio->id,
                    'name' => $activeStudio->name,
                ] : null,
                'can_invite_roles' => $inviteRoles
                    ->map(fn (UserRole $role): array => [
                        'value' => $role->value,
                        'label' => $role->label(),
                    ])
                    ->values(),
            ],
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
            'studio_id' => ['required', 'integer', 'exists:studios,id'],
            'email'    => ['required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        $role = UserRole::fromValue($validated['role']);
        abort_unless($request->user()?->canManageStaffRole($role), 403);

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
                'studio_id' => $studio->id,
                'is_active' => ! $isInvitation,
                'action' => $result['action'],
                'invitation_id' => $result['invitation']->id ?? null,
            ],
        ], $isInvitation ? 202 : 201);
    }

    public function studioOptions(): JsonResponse
    {
        $user = request()->user();
        $studios = Studio::query()
            ->whereIn('id', $user?->staffScopeStudioIds() ?? [])
            ->with('company:id,name')
            ->get(['id', 'company_id', 'name']);

        return response()->json([
            'data' => $studios->map(fn (Studio $studio): array => [
                'id' => $studio->id,
                'name' => $studio->name,
                'company_id' => $studio->company_id,
                'company' => $studio->company ? [
                    'id' => $studio->company->id,
                    'name' => $studio->company->name,
                ] : null,
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
        $actor = $request->user();

        abort_unless($this->canAccessStaffInStudio($actor, $studio), 403);
        abort_if(
            ! $actor?->hasRole(UserRole::Admin)
                && $user->hasAnyRole([UserRole::Kullanici, UserRole::KullaniciRol]),
            403
        );

        $currentRole = $studio->users()->where('users.id', $user->id)->first()?->pivot?->role;
        abort_if($currentRole === null, 404);
        abort_unless($actor?->canManageStaffRole($currentRole) || $actor?->is($user), 403);

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

        if (! $actor?->hasRole(UserRole::Admin)) {
            if ($actor?->is($user)) {
                abort_if(
                    array_intersect(['role', 'status', 'is_active'], array_keys($validated)) !== [],
                    403
                );
            } else {
                $isFireOnly = count($validated) === 1
                    && array_key_exists('is_active', $validated)
                    && $validated['is_active'] === false;

                abort_unless($isFireOnly, 403);
            }
        }

        if (isset($validated['role'])) {
            abort_unless($actor?->canManageStaffRole($validated['role']), 403);
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
