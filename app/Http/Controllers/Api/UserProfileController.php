<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    /** Yetkili kapsamda kullanıcı profili. */
    public function show(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 401);
        abort_unless($this->canViewProfile($viewer, $user), 403);

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
            $appointments = Appointment::query()
                ->where('assigned_artist_user_id', $user->id);

            $appointmentStats = [
                'completed' => (int) (clone $appointments)
                    ->where('status', 'completed')
                    ->count(),
                'cancelled' => (int) (clone $appointments)
                    ->where('status', 'cancelled')
                    ->count(),
                'total'     => (int) $appointments->count(),
            ];
        }

        $hasSpecializations = in_array(
            $user->role?->value,
            [UserRole::Artist->value, UserRole::Designer->value],
            true
        );

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                  => $user->id,
                'name'                => $user->fullName(),
                'email'               => $user->email,
                'phone'               => $user->phone,
                'username'            => $user->username,
                'bio'                 => $user->bio,
                'location'            => $user->location,
                'experience_years'    => $user->experience_years,
                'specializations'     => $hasSpecializations ? ($user->specializations ?? []) : null,
                'has_specializations' => $hasSpecializations,
                'availability_start'  => $user->availability_start,
                'availability_end'    => $user->availability_end,
                'profile_image'       => $user->profile_image,
                'rating'              => $user->rating,
                'role'                => $user->role?->value,
                'is_banned'           => $user->banned_at !== null,
                'ban_reason'          => $user->ban_reason,
                'has_portfolio'       => $hasPortfolio,
                'portfolio'           => $hasPortfolio ? ($user->portfolio ?? []) : null,
                'appointment_stats'  => $appointmentStats,
                'current_studios' => $activeStudios->map(fn ($s): array => [
                    'id'        => $s->id,
                    'name'      => $s->name,
                    'location'  => $s->location,
                    'logo_path' => $s->logo_path,
                    'role'      => $s->pivot->role,
                    'status'    => $s->pivot->work_status,
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

    private function canViewProfile(User $viewer, User $target): bool
    {
        if ($viewer->is($target) || $viewer->hasRole(UserRole::Admin)) {
            return true;
        }

        $targetStudioIds = $target->studios()
            ->pluck('studios.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($targetStudioIds === []) {
            return false;
        }

        if ($viewer->hasRole(UserRole::Yonetici)) {
            return count(array_intersect($targetStudioIds, $viewer->accessibleStudioIds())) > 0;
        }

        if ($viewer->hasRole(UserRole::Supervisor)) {
            $supervisedStudioIds = $viewer->studios()
                ->wherePivot('is_active', true)
                ->wherePivot('role', UserRole::Supervisor->value)
                ->pluck('studios.id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            return count(array_intersect($targetStudioIds, $supervisedStudioIds)) > 0;
        }

        return false;
    }
}
