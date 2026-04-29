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
    public function store(Request $request, StudioStaffService $studioStaffService): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole([UserRole::Admin, UserRole::Yonetici]), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'role' => ['required', 'string', 'in:admin,yonetici,calisan,sofor,supervisor'],
            'studio_id' => ['required', 'integer', 'exists:studios,id'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $studio = Studio::query()->findOrFail($validated['studio_id']);
        abort_unless($request->user()?->canManageStudio($studio), 403);

        if (
            ! $request->user()?->hasRole(UserRole::Admin)
            && in_array($validated['role'], ['admin', 'yonetici'], true)
        ) {
            abort(403);
        }

        $role = UserRole::fromValue($validated['role']);

        $result = $studioStaffService->createOrAttach($studio, $role, $validated);

        return response()->json([
            'message' => 'Kullanici basariyla olusturuldu.',
            'data' => [
                'id' => $result['user']->id,
                'name' => $result['user']->fullName(),
                'email' => $result['user']->email,
                'role' => $result['studio_role'],
                'is_active' => true,
            ],
        ], 201);
    }

    public function studioOptions(): JsonResponse
    {
        $user = request()->user();
        $studios = Studio::query()
            ->when(
                ! $user?->hasRole(UserRole::Admin),
                fn ($query) => $query->whereIn('id', $user?->accessibleStudioIds() ?? [])
            )
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
        abort_unless(request()->user()?->canManageStudio($studio), 403);

        $users = $studio->users()->get();

        return response()->json([
            'data' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'role' => $user->pivot->role,
                'profile_image' => $user->profile_image,
                'location' => $studio->location,
                'status' => $user->pivot->work_status,
                'is_active' => (bool) $user->pivot->is_active,
            ])->values(),
        ]);
    }

    public function update(Request $request, Studio $studio, User $user, StudioStaffService $studioStaffService): JsonResponse
    {
        abort_unless($request->user()?->canManageStudio($studio), 403);

        $currentRole = $studio->users()->where('users.id', $user->id)->first()?->pivot?->role;
        abort_if($currentRole === null, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'surname' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'role' => ['sometimes', 'string', 'in:admin,yonetici,supervisor,calisan,sofor'],
            'status' => ['sometimes', 'string', 'in:working,break,transfer'],
            'is_active' => ['sometimes', 'boolean'],
            'profile_image' => ['nullable', 'string', 'max:2048'],
        ]);

        if (
            ! $request->user()?->hasRole(UserRole::Admin)
            && isset($validated['role'])
            && in_array($validated['role'], ['admin', 'yonetici'], true)
        ) {
            abort(403);
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
                'location' => $studio->location,
                'status' => $pivot->work_status,
                'is_active' => (bool) $pivot->is_active,
            ],
        ]);
    }
}
