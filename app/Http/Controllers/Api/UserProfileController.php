<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserProfileController extends Controller
{
    /** Herhangi bir kullanıcının profili (giriş yapılmış kullanıcılar) */
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
            ->get(['studios.id', 'studios.name', 'studios.location', 'studios.logo_path', 'studio_user.role']);

        // Geçmiş stüdyo üyelikleri
        $pastStudios = $user->studios()
            ->wherePivot('is_active', false)
            ->get(['studios.id', 'studios.name', 'studios.location', 'studio_user.joined_at', 'studio_user.left_at', 'studio_user.role']);

        // Randevu istatistikleri (artist olarak atandığı randevular)
        $appointmentStats = null;
        if ($hasPortfolio) {
            $stats = Appointment::query()
                ->where('assigned_artist_user_id', $user->id)
                ->select('artist_status', DB::raw('count(*) as count'))
                ->groupBy('artist_status')
                ->pluck('count', 'artist_status');

            $appointmentStats = [
                'accepted' => (int) ($stats['accepted'] ?? 0),
                'rejected' => (int) ($stats['rejected'] ?? 0),
                'pending'  => (int) ($stats['pending'] ?? 0),
                'total'    => $stats->sum(),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                 => $user->id,
                'name'               => $user->fullName(),
                'bio'                => $user->bio,
                'location'           => $user->location,
                'availability_start' => $user->availability_start,
                'availability_end'   => $user->availability_end,
                'profile_image'      => $user->profile_image,
                'rating'             => $user->rating,
                'role'               => $user->role?->value,
                'has_portfolio'      => $hasPortfolio,
                'portfolio'          => $hasPortfolio ? ($user->portfolio ?? []) : null,
                'appointment_stats'  => $appointmentStats,
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
                    'role'      => $s->pivot->role,
                    'joined_at' => $s->pivot->joined_at,
                    'left_at'   => $s->pivot->left_at,
                ])->values(),
                'member_since' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }
}
