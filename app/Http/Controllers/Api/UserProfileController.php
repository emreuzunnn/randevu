<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserProfileController extends Controller
{
    /** Herhangi bir kullanıcının herkese açık profil bilgisi (giriş yapılmış kullanıcılar görüntüleyebilir) */
    public function show(User $user): JsonResponse
    {
        $hasPortfolio = ! in_array(
            $user->role?->value,
            [UserRole::Sofor->value, UserRole::Kullanici->value],
            true
        );

        // Aktif stüdyo üyelikleri
        $activeStudios = $user->studios()
            ->wherePivot('is_active', true)
            ->get(['studios.id', 'studios.name', 'studios.location', 'studios.logo_path']);

        // Eski stüdyo geçmişi
        $pastStudios = $user->studios()
            ->wherePivot('is_active', false)
            ->get(['studios.id', 'studios.name', 'studios.location', 'studio_user.joined_at', 'studio_user.left_at']);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'            => $user->id,
                'name'          => $user->fullName(),
                'bio'           => $user->bio,
                'profile_image' => $user->profile_image,
                'rating'        => $user->rating,
                'role'          => $user->role?->value,
                'has_portfolio' => $hasPortfolio,
                'portfolio'     => $hasPortfolio ? ($user->portfolio ?? []) : null,
                'current_studios' => $activeStudios->map(fn ($s): array => [
                    'id'        => $s->id,
                    'name'      => $s->name,
                    'location'  => $s->location,
                    'logo_path' => $s->logo_path,
                    'role'      => $s->pivot->role,
                ])->values(),
                'past_studios' => $pastStudios->map(fn ($s): array => [
                    'id'        => $s->id,
                    'name'      => $s->name,
                    'location'  => $s->location,
                    'joined_at' => $s->pivot->joined_at,
                    'left_at'   => $s->pivot->left_at,
                ])->values(),
                'member_since' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }
}
