<?php

namespace App\Http\Controllers\Api;

use App\Models\Studio;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::query()
            ->where('email', $validated['email'])
            ->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email veya sifre hatali.',
            ], 422);
        }

        $token = $user->issueApiToken();
        $primaryStudio = $this->resolvePrimaryStudio($user);
        $membership = $primaryStudio?->users()->where('users.id', $user->id)->first()?->pivot;

        return response()->json([
            'message' => 'Giris basarili.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'studio_id' => $primaryStudio?->id,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->fullName(),
                    'email' => $user->email,
                    'role' => $membership?->role ?? $user->role?->value,
                    'profile_image' => $user->profile_image,
                    'status' => $membership?->work_status ?? 'working',
                    'is_active' => (bool) ($membership?->is_active ?? true),
                ],
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()?->load(['studios', 'managedShops']);
        $primaryStudio = $user ? $this->resolvePrimaryStudio($user) : null;
        $membership = $primaryStudio?->users()->where('users.id', $user?->id)->first()?->pivot;

        return response()->json([
            'data' => [
                'id' => $user?->id,
                'name' => $user?->fullName(),
                'email' => $user?->email,
                'role' => $membership?->role ?? $user?->role?->value,
                'profile_image' => $user?->profile_image,
                'status' => $membership?->work_status ?? 'working',
                'location' => $primaryStudio?->location ?? $user?->managedShops->first()?->location,
                'is_active' => (bool) ($membership?->is_active ?? true),
                'created_at' => $user?->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->revokeApiToken();

        return response()->json([
            'message' => 'Cikis yapildi.',
        ]);
    }

    private function resolvePrimaryStudio(\App\Models\User $user): ?Studio
    {
        $studioId = $user->accessibleStudioIds()[0] ?? null;

        return $studioId ? Studio::query()->find($studioId) : null;
    }
}
