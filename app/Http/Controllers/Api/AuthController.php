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
                'phone' => $user?->phone,
                'role' => $membership?->role ?? $user?->role?->value,
                'profile_image' => $user?->profile_image,
                'status' => $membership?->work_status ?? 'working',
                'location' => $primaryStudio?->location ?? $user?->managedShops->first()?->location,
                'is_active' => (bool) ($membership?->is_active ?? true),
                'created_at' => $user?->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'surname' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'profile_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'status' => ['sometimes', 'string', 'in:working,break,transfer'],
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'confirmed'],
        ]);

        if (array_key_exists('email', $validated)) {
            $emailExists = \App\Models\User::query()
                ->where('email', $validated['email'])
                ->whereKeyNot($user->id)
                ->exists();

            if ($emailExists) {
                return response()->json([
                    'message' => 'Bu email zaten kullanimda.',
                    'errors' => [
                        'email' => ['Bu email zaten kullanimda.'],
                    ],
                ], 422);
            }
        }

        $user->fill(collect($validated)->only([
            'name',
            'surname',
            'email',
            'phone',
            'profile_image',
        ])->all());

        if (! empty($validated['password'] ?? null)) {
            $user->password = $validated['password'];
        }

        $user->save();

        $primaryStudio = $this->resolvePrimaryStudio($user);
        if ($primaryStudio !== null && array_key_exists('status', $validated)) {
            $primaryStudio->users()->updateExistingPivot($user->id, [
                'work_status' => $validated['status'],
            ]);
        }

        $refreshedUser = $user->fresh()?->load(['studios', 'managedShops']);
        $primaryStudio = $refreshedUser ? $this->resolvePrimaryStudio($refreshedUser) : null;
        $membership = $primaryStudio?->users()->where('users.id', $refreshedUser?->id)->first()?->pivot;

        return response()->json([
            'message' => 'Profil guncellendi.',
            'data' => [
                'id' => $refreshedUser?->id,
                'name' => $refreshedUser?->fullName(),
                'email' => $refreshedUser?->email,
                'role' => $membership?->role ?? $refreshedUser?->role?->value,
                'profile_image' => $refreshedUser?->profile_image,
                'status' => $membership?->work_status ?? 'working',
                'location' => $primaryStudio?->location ?? $refreshedUser?->managedShops->first()?->location,
                'is_active' => (bool) ($membership?->is_active ?? true),
                'created_at' => $refreshedUser?->created_at?->toIso8601String(),
                'phone' => $refreshedUser?->phone,
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
