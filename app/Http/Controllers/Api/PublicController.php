<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PublicController extends Controller
{
    /** Stüdyo herkese açık profili */
    public function studio(Studio $studio): JsonResponse
    {
        $studio->load('shop');

        $artists = $studio->users()
            ->wherePivot('role', UserRole::Artist->value)
            ->wherePivot('is_active', true)
            ->get(['users.id', 'users.name', 'users.surname', 'users.profile_image', 'users.bio', 'users.rating', 'users.portfolio']);

        return response()->json([
            'data' => [
                'id'       => $studio->id,
                'name'     => $studio->name,
                'location' => $studio->location,
                'logo_path' => $studio->logo_path,
                'shop'     => $studio->shop ? ['id' => $studio->shop->id, 'name' => $studio->shop->name] : null,
                'artists'  => $artists->map(fn ($a): array => [
                    'id'            => $a->id,
                    'name'          => $a->fullName(),
                    'profile_image' => $a->profile_image,
                    'bio'           => $a->bio,
                    'rating'        => $a->rating,
                    'portfolio'     => $a->portfolio ?? [],
                ])->values(),
            ],
        ]);
    }

    /** Artist / rol sahibi kullanıcı herkese açık profil */
    public function artist(User $user): JsonResponse
    {
        abort_unless(
            $user->hasAnyRole([UserRole::Artist, UserRole::KullaniciRol]),
            404
        );

        $studioMemberships = $user->studios()
            ->wherePivot('is_active', true)
            ->get(['studios.id', 'studios.name', 'studios.location', 'studios.logo_path']);

        return response()->json([
            'data' => [
                'id'             => $user->id,
                'name'           => $user->fullName(),
                'profile_image'  => $user->profile_image,
                'bio'            => $user->bio,
                'rating'         => $user->rating,
                'portfolio'      => $user->portfolio ?? [],
                'studios'        => $studioMemberships->map(fn ($s): array => [
                    'id'       => $s->id,
                    'name'     => $s->name,
                    'location' => $s->location,
                    'logo_path' => $s->logo_path,
                ])->values(),
            ],
        ]);
    }

    /** Stüdyo listesi (herkese açık, discovery için) */
    public function studios(): JsonResponse
    {
        $studios = Studio::query()
            ->with('shop')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $studios->map(fn ($studio): array => [
                'id'       => $studio->id,
                'name'     => $studio->name,
                'location' => $studio->location,
                'logo_path' => $studio->logo_path,
                'shop'     => $studio->shop ? ['name' => $studio->shop->name] : null,
            ])->values(),
        ]);
    }

    /** Artist listesi (herkese açık) */
    public function artists(): JsonResponse
    {
        $artists = User::query()
            ->whereIn('role', [UserRole::Artist->value, UserRole::KullaniciRol->value])
            ->orderBy('name')
            ->get(['id', 'name', 'surname', 'profile_image', 'bio', 'rating', 'portfolio']);

        return response()->json([
            'data' => $artists->map(fn ($a): array => [
                'id'            => $a->id,
                'name'          => $a->fullName(),
                'profile_image' => $a->profile_image,
                'bio'           => $a->bio,
                'rating'        => $a->rating,
                'portfolio'     => $a->portfolio ?? [],
            ])->values(),
        ]);
    }
}
