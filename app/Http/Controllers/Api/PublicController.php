<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PublicController extends Controller
{
    /** Stüdyo herkese açık detay + portfolio */
    public function studio(Studio $studio): JsonResponse
    {
        $studio->load(['shop.company']);

        $artists = $studio->users()
            ->wherePivotIn('role', [UserRole::Artist->value, UserRole::Dovmeci->value, UserRole::Designer->value])
            ->wherePivot('is_active', true)
            ->get(['users.id', 'users.name', 'users.surname', 'users.profile_image', 'users.bio', 'users.rating', 'users.portfolio', 'studio_user.role']);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'             => $studio->id,
                'name'           => $studio->name,
                'slug'           => $studio->slug,
                'location'       => $studio->location,
                'about'          => $studio->about,
                'logo_path'      => $studio->logo_path,
                'opening_time'   => $studio->opening_time ?? $studio->shop?->opening_time,
                'closing_time'   => $studio->closing_time ?? $studio->shop?->closing_time,
                'gallery_images' => $studio->gallery_images ?? [],
                'shop'           => $studio->shop ? [
                    'id'           => $studio->shop->id,
                    'name'         => $studio->shop->name,
                    'location'     => $studio->shop->location,
                    'logo_path'    => $studio->shop->logo_path,
                    'opening_time' => $studio->shop->opening_time,
                    'closing_time' => $studio->shop->closing_time,
                    'company'      => $studio->shop->company ? [
                        'id'        => $studio->shop->company->id,
                        'name'      => $studio->shop->company->name,
                        'logo_path' => $studio->shop->company->logo_path,
                        'about'     => $studio->shop->company->about,
                        'website'   => $studio->shop->company->website,
                    ] : null,
                ] : null,
                'staff' => $artists->map(fn ($a): array => [
                    'id'            => $a->id,
                    'name'          => $a->fullName(),
                    'role'          => $a->pivot->role,
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
            ->with(['shop.company'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $studios->map(fn ($studio): array => [
                'id'           => $studio->id,
                'name'         => $studio->name,
                'slug'         => $studio->slug,
                'location'     => $studio->location,
                'about'        => $studio->about,
                'logo_path'    => $studio->logo_path,
                'opening_time' => $studio->opening_time ?? $studio->shop?->opening_time,
                'closing_time' => $studio->closing_time ?? $studio->shop?->closing_time,
                'shop'         => $studio->shop ? [
                    'id'           => $studio->shop->id,
                    'name'         => $studio->shop->name,
                    'location'     => $studio->shop->location,
                    'logo_path'    => $studio->shop->logo_path,
                    'opening_time' => $studio->shop->opening_time,
                    'closing_time' => $studio->shop->closing_time,
                    'company'      => $studio->shop->company ? [
                        'id'        => $studio->shop->company->id,
                        'name'      => $studio->shop->company->name,
                        'logo_path' => $studio->shop->company->logo_path,
                    ] : null,
                ] : null,
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
