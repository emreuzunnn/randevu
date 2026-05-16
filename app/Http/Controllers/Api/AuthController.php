<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Kayıt: telefon, email ve şifre ile hesap oluştur */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['nullable', 'string', 'max:255'],
            'surname'  => ['nullable', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'bio'      => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::query()->create([
            'name'     => $validated['name'] ?? '',
            'surname'  => $validated['surname'] ?? null,
            'email'    => $validated['email'],
            'phone'    => $validated['phone'],
            'password' => $validated['password'],
            'role'     => UserRole::Kullanici,
            'bio'      => $validated['bio'] ?? null,
        ]);

        $token = $user->issueApiToken();

        return response()->json([
            'status'  => 'success',
            'message' => 'Kayıt başarılı.',
            'data'    => [
                'token'      => $token,
                'token_type' => 'Bearer',
                'user'       => [
                    'id'    => $user->id,
                    'name'  => $user->fullName(),
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role'  => $user->role?->value,
                ],
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Email veya şifre hatalı.'], 422);
        }

        $token = $user->issueApiToken();
        $primaryStudio = $this->resolvePrimaryStudio($user);
        $membership = $primaryStudio?->users()->where('users.id', $user->id)->first()?->pivot;

        return response()->json([
            'message' => 'Giriş başarılı.',
            'data' => [
                'token'      => $token,
                'token_type' => 'Bearer',
                'studio_id'  => $primaryStudio?->id,
                'user'       => [
                    'id'            => $user->id,
                    'name'          => $user->fullName(),
                    'email'         => $user->email,
                    'role'          => $membership?->role ?? $user->role?->value,
                    'profile_image' => $user->profile_image,
                    'bio'           => $user->bio,
                    'status'        => $membership?->work_status ?? 'working',
                    'is_active'     => (bool) ($membership?->is_active ?? true),
                ],
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()?->load(['studios', 'managedShops']);
        $primaryStudio = $user ? $this->resolvePrimaryStudio($user) : null;
        $membership = $primaryStudio?->users()->where('users.id', $user?->id)->first()?->pivot;

        $studioHistory = $user?->studios()
            ->wherePivot('is_active', false)
            ->get(['studios.id', 'studios.name', 'studios.location', 'studio_user.joined_at', 'studio_user.left_at']);

        $hasPortfolio = ! in_array(
            $user?->role?->value,
            [\App\Enums\UserRole::Sofor->value, \App\Enums\UserRole::Kullanici->value],
            true
        );

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                 => $user?->id,
                'name'               => $user?->fullName(),
                'email'              => $user?->email,
                'phone'              => $user?->phone,
                'bio'                => $user?->bio,
                'location'           => $user?->location,
                'availability_start' => $user?->availability_start,
                'availability_end'   => $user?->availability_end,
                'portfolio'          => $hasPortfolio ? ($user?->portfolio ?? []) : null,
                'has_portfolio'      => $hasPortfolio,
                'role'               => $membership?->role ?? $user?->role?->value,
                'profile_image'      => $user?->profile_image,
                'rating'             => $user?->rating,
                'status'             => $membership?->work_status ?? 'working',
                'is_active'          => (bool) ($membership?->is_active ?? true),
                'current_studio'     => $primaryStudio ? [
                    'id'       => $primaryStudio->id,
                    'name'     => $primaryStudio->name,
                    'location' => $primaryStudio->location,
                ] : null,
                'studio_history' => $studioHistory?->map(fn ($s): array => [
                    'id'        => $s->id,
                    'name'      => $s->name,
                    'location'  => $s->location,
                    'joined_at' => $s->pivot->joined_at,
                    'left_at'   => $s->pivot->left_at,
                ])->values() ?? [],
                'created_at' => $user?->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $validated = $request->validate([
            'name'               => ['sometimes', 'string', 'max:255'],
            'surname'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'              => ['sometimes', 'string', 'email', 'max:255'],
            'phone'              => ['sometimes', 'nullable', 'string', 'max:30'],
            'bio'                => ['sometimes', 'nullable', 'string', 'max:1000'],
            'location'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'availability_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'availability_end'   => ['sometimes', 'nullable', 'date_format:H:i'],
            'profile_image'      => ['sometimes', 'nullable', 'string', 'max:2048'],
            'status'             => ['sometimes', 'string', 'in:working,break,transfer'],
            'password'           => ['sometimes', 'nullable', 'string', 'min:6', 'confirmed'],
        ]);

        if (array_key_exists('email', $validated)) {
            $emailExists = User::query()
                ->where('email', $validated['email'])
                ->whereKeyNot($user->id)
                ->exists();

            if ($emailExists) {
                return response()->json([
                    'message' => 'Bu email zaten kullanımda.',
                    'errors'  => ['email' => ['Bu email zaten kullanımda.']],
                ], 422);
            }
        }

        $user->fill(collect($validated)->only([
            'name', 'surname', 'email', 'phone', 'bio',
            'location', 'availability_start', 'availability_end', 'profile_image',
        ])->all());

        if (! empty($validated['password'] ?? null)) {
            $user->password = $validated['password'];
        }

        $user->save();

        $primaryStudio = $this->resolvePrimaryStudio($user);
        if ($primaryStudio !== null && array_key_exists('status', $validated)) {
            $primaryStudio->users()->updateExistingPivot($user->id, ['work_status' => $validated['status']]);
        }

        $refreshedUser = $user->fresh()?->load(['studios', 'managedShops']);
        $primaryStudio = $refreshedUser ? $this->resolvePrimaryStudio($refreshedUser) : null;
        $membership = $primaryStudio?->users()->where('users.id', $refreshedUser?->id)->first()?->pivot;

        $hasPortfolio = ! in_array(
            $refreshedUser?->role?->value,
            [UserRole::Sofor->value, UserRole::Kullanici->value],
            true
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Profil güncellendi.',
            'data'    => [
                'id'                 => $refreshedUser?->id,
                'name'               => $refreshedUser?->fullName(),
                'email'              => $refreshedUser?->email,
                'phone'              => $refreshedUser?->phone,
                'bio'                => $refreshedUser?->bio,
                'location'           => $refreshedUser?->location,
                'availability_start' => $refreshedUser?->availability_start,
                'availability_end'   => $refreshedUser?->availability_end,
                'portfolio'          => $hasPortfolio ? ($refreshedUser?->portfolio ?? []) : null,
                'has_portfolio'      => $hasPortfolio,
                'role'               => $membership?->role ?? $refreshedUser?->role?->value,
                'profile_image'      => $refreshedUser?->profile_image,
                'rating'             => $refreshedUser?->rating,
                'status'             => $membership?->work_status ?? 'working',
                'is_active'          => (bool) ($membership?->is_active ?? true),
                'created_at'         => $refreshedUser?->created_at?->toIso8601String(),
            ],
        ]);
    }

    /** Portfolyo tüm içeriğini güncelle/değiştir */
    public function updatePortfolio(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $validated = $request->validate([
            'portfolio'               => ['required', 'array'],
            'portfolio.*.title'       => ['required', 'string', 'max:255'],
            'portfolio.*.image_path'  => ['nullable', 'string', 'max:2048'],
            'portfolio.*.description' => ['nullable', 'string', 'max:1000'],
            'portfolio.*.category'    => ['nullable', 'string', 'max:50'],
        ]);

        $user->update(['portfolio' => $validated['portfolio']]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Portfolyo güncellendi.',
            'data'    => ['portfolio' => $user->fresh()->portfolio ?? []],
        ]);
    }

    /**
     * Portfolyoya tek öğe ekle.
     * image alanı (dosya) veya image_path (görece yol / tam URL) kabul eder.
     * Dosya gönderilirse yüklenir ve görece path olarak saklanır.
     */
    public function addPortfolioItem(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'image'       => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'image_path'  => ['nullable', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category'    => ['nullable', 'string', 'max:50'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imagePath = $file->storeAs(
                'portfolio/' . $user->id,
                Str::uuid() . '.' . $file->getClientOriginalExtension(),
                'public'
            );
        } elseif (! empty($validated['image_path'])) {
            $imagePath = $validated['image_path'];
        }

        // Mevcut portfolyoyu raw olarak oku (accessor'ın URL dönüşümünü atla).
        $rawPortfolio = $user->getRawOriginal('portfolio');
        $portfolio = $rawPortfolio ? json_decode($rawPortfolio, true) : [];

        $item = array_filter([
            'title'       => $validated['title'],
            'image_path'  => $imagePath,
            'description' => $validated['description'] ?? null,
            'category'    => $validated['category'] ?? null,
        ], fn ($v) => $v !== null);

        $portfolio[] = $item;
        $user->update(['portfolio' => $portfolio]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Portfolyo öğesi eklendi.',
            'data'    => ['portfolio' => $user->fresh()->portfolio ?? []],
        ]);
    }

    /** Portfolyodan öğe sil (index ile) */
    public function removePortfolioItem(Request $request, int $index): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $rawPortfolio = $user->getRawOriginal('portfolio');
        $portfolio = array_values($rawPortfolio ? json_decode($rawPortfolio, true) : []);
        abort_if(! isset($portfolio[$index]), 404, 'Portfolyo öğesi bulunamadı.');

        array_splice($portfolio, $index, 1);
        $user->update(['portfolio' => $portfolio]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Portfolyo öğesi silindi.',
            'data'    => ['portfolio' => $user->fresh()->portfolio ?? []],
        ]);
    }

    /** Portfolyo getir */
    public function getPortfolio(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return response()->json([
            'status' => 'success',
            'data'   => ['portfolio' => $user->portfolio ?? []],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->revokeApiToken();
        return response()->json(['message' => 'Çıkış yapıldı.']);
    }

    private function resolvePrimaryStudio(User $user): ?Studio
    {
        $studioId = $user->accessibleStudioIds()[0] ?? null;
        return $studioId ? Studio::query()->find($studioId) : null;
    }
}
