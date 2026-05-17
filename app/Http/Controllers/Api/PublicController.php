<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Review;
use App\Models\Shop;
use App\Models\Studio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    /** Stüdyo herkese açık detay + portfolio + istatistikler */
    public function studio(Studio $studio): JsonResponse
    {
        $studio->load(['shop.company']);

        $artists = $studio->users()
            ->wherePivotIn('role', [UserRole::Artist->value, UserRole::Designer->value])
            ->wherePivot('is_active', true)
            ->get(['users.id', 'users.name', 'users.surname', 'users.profile_image', 'users.bio', 'users.rating', 'users.portfolio', 'studio_user.role']);

        $stats = Appointment::query()
            ->where('studio_id', $studio->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

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
                'appointment_stats' => [
                    'total'     => $stats->sum(),
                    'completed' => (int) ($stats['completed'] ?? 0),
                    'accepted'  => (int) ($stats['confirmed'] ?? 0),
                    'cancelled' => (int) ($stats['cancelled'] ?? 0),
                    'pending'   => (int) ($stats['pending'] ?? 0),
                ],
                'staff' => $artists->map(fn ($a): array => [
                    'id'                 => $a->id,
                    'name'               => $a->fullName(),
                    'username'           => $a->username,
                    'role'               => $a->pivot->role,
                    'profile_image'      => $a->profile_image,
                    'bio'                => $a->bio,
                    'location'           => $a->location,
                    'experience_years'   => $a->experience_years,
                    'specializations'    => $a->specializations ?? [],
                    'availability_start' => $a->availability_start,
                    'availability_end'   => $a->availability_end,
                    'rating'             => $a->rating,
                    'portfolio'          => $a->portfolio ?? [],
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

        // Portfolyodaki benzersiz kategorileri uzmanlık alanı olarak döndür
        $portfolio  = $user->portfolio ?? [];
        $specialties = collect($portfolio)
            ->pluck('category')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $reviewStats = Review::query()
            ->where('artist_id', $user->id)
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating')
            ->first();

        return response()->json([
            'data' => [
                'id'                  => $user->id,
                'name'                => $user->fullName(),
                'username'            => $user->username,
                'profile_image'       => $user->profile_image,
                'bio'                 => $user->bio,
                'location'            => $user->location,
                'experience_years'    => $user->experience_years,
                'specializations'     => $user->specializations ?? [],
                'specialties'         => $specialties,
                'rating'              => $user->rating,
                'review_count'        => (int) ($reviewStats->total ?? 0),
                'response_time_hours' => $user->response_time_hours,
                'portfolio'           => $portfolio,
                'social'              => [
                    'instagram' => $user->instagram,
                    'whatsapp'  => $user->whatsapp,
                ],
                'availability_start' => $user->availability_start,
                'availability_end'   => $user->availability_end,
                'studios'            => $studioMemberships->map(fn ($s): array => [
                    'id'        => $s->id,
                    'name'      => $s->name,
                    'location'  => $s->location,
                    'logo_path' => $s->logo_path,
                ])->values(),
            ],
        ]);
    }

    /** Artist müsaitlik takvimi — önümüzdeki 7 gün */
    public function artistAvailability(User $user): JsonResponse
    {
        abort_unless(
            $user->hasAnyRole([UserRole::Artist, UserRole::KullaniciRol]),
            404
        );

        $maxDailySlots = 4;
        $days          = [];

        for ($i = 0; $i < 7; $i++) {
            $date    = Carbon::today()->addDays($i);
            $dateStr = $date->toDateString();

            $bookedCount = Appointment::query()
                ->where('assigned_artist_user_id', $user->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereDate('appointment_at', $dateStr)
                ->count();

            $available = max(0, $maxDailySlots - $bookedCount);

            if ($i === 0) {
                $label = 'Bugün';
            } elseif ($i === 1) {
                $label = 'Yarın';
            } else {
                $label = $date->locale('tr')->isoFormat('dddd');
            }

            $days[] = [
                'date'            => $dateStr,
                'label'           => $label,
                'day_name'        => $date->locale('tr')->isoFormat('dddd'),
                'available_slots' => $available,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'artist_id'           => $user->id,
                'artist_name'         => $user->fullName(),
                'response_time_hours' => $user->response_time_hours,
                'availability'        => $days,
            ],
        ]);
    }

    /** Artist değerlendirmeleri */
    public function artistReviews(User $user): JsonResponse
    {
        abort_unless(
            $user->hasAnyRole([UserRole::Artist, UserRole::KullaniciRol]),
            404
        );

        $reviews = Review::query()
            ->with('reviewer:id,name,surname')
            ->where('artist_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $total   = $reviews->count();
        $average = $total > 0 ? round($reviews->avg('rating'), 1) : null;

        $distribution = collect([5, 4, 3, 2, 1])->mapWithKeys(
            fn (int $star): array => [(string) $star => $reviews->where('rating', $star)->count()]
        );

        return response()->json([
            'status' => 'success',
            'data'   => [
                'summary' => [
                    'average'      => $average,
                    'total'        => $total,
                    'distribution' => $distribution,
                ],
                'items' => $reviews->map(fn ($r): array => [
                    'id'         => $r->id,
                    'user_name'  => $r->reviewer ? $r->reviewer->fullName() : 'Anonim',
                    'rating'     => $r->rating,
                    'comment'    => $r->comment,
                    'created_at' => $r->created_at?->toIso8601String(),
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
            ->get(['id', 'name', 'surname', 'username', 'profile_image', 'bio', 'rating', 'portfolio', 'experience_years', 'specializations', 'location', 'response_time_hours']);

        return response()->json([
            'data' => $artists->map(fn ($a): array => [
                'id'                  => $a->id,
                'name'                => $a->fullName(),
                'username'            => $a->username,
                'profile_image'       => $a->profile_image,
                'bio'                 => $a->bio,
                'location'            => $a->location,
                'experience_years'    => $a->experience_years,
                'specializations'     => $a->specializations ?? [],
                'rating'              => $a->rating,
                'response_time_hours' => $a->response_time_hours,
                'portfolio'           => $a->portfolio ?? [],
            ])->values(),
        ]);
    }
}
