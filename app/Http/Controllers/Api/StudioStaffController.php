<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Models\User;
use App\Services\StudioStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioStaffController extends Controller
{
    public function index(Studio $studio, Request $request): JsonResponse
    {
        $role = UserRole::fromValue((string) $request->route('role'));
        $users = $studio->users()
            ->wherePivot('role', $role->value)
            ->get(['users.id', 'users.name', 'users.email']);

        return response()->json([
            'data' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'studio_role' => $role->value,
            ])->values(),
        ]);
    }

    public function store(Studio $studio, Request $request, StudioStaffService $studioStaffService): JsonResponse
    {
        $role = UserRole::fromValue((string) $request->route('role'));
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $result = $studioStaffService->createOrAttach($studio, $role, $validated);

        return response()->json([
            'message' => $role->label().' olusturuldu.',
            'data' => [
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                ],
                'studio_id' => $studio->id,
                'studio_role' => $result['studio_role'],
                'action' => $result['action'],
            ],
        ], 201);
    }

    public function update(
        Studio $studio,
        Request $request,
        User $user,
        StudioStaffService $studioStaffService
    ): JsonResponse {
        $role = UserRole::fromValue((string) $request->route('role'));
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updatedUser = $studioStaffService->updateMembership($studio, $user, $role, $validated);

        return response()->json([
            'message' => $role->label().' guncellendi.',
            'data' => [
                'id' => $updatedUser->id,
                'name' => $updatedUser->name,
                'email' => $updatedUser->email,
                'studio_role' => $role->value,
            ],
        ]);
    }

    public function destroy(
        Studio $studio,
        Request $request,
        User $user,
        StudioStaffService $studioStaffService
    ): JsonResponse {
        $role = UserRole::fromValue((string) $request->route('role'));
        $studioStaffService->deactivateMembership($studio, $user, $role);

        return response()->json([
            'message' => $role->label().' studyodan pasife alindi.',
        ]);
    }
}
