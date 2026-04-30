<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AppointmentReportService
{
    /**
     * @return array<string, array<string, int|string>>
     */
    public function buildPeriodReports(User $user, ?int $studioId = null): array
    {
        $now = CarbonImmutable::now();

        return [
            'daily' => $this->summarize(
                $this->baseQuery($user, $studioId),
                $now->startOfDay(),
                $now->endOfDay(),
                'Gunluk'
            ),
            'monthly' => $this->summarize(
                $this->baseQuery($user, $studioId),
                $now->startOfMonth(),
                $now->endOfMonth(),
                'Aylik'
            ),
            'quarterly' => $this->summarize(
                $this->baseQuery($user, $studioId),
                $now->subMonths(2)->startOfMonth(),
                $now->endOfMonth(),
                '3 Aylik'
            ),
        ];
    }

    private function baseQuery(User $user, ?int $studioId = null): Builder
    {
        $query = Appointment::query();

        if (! $user->hasRole(UserRole::Admin)) {
            $query->whereIn('studio_id', $user->accessibleStudioIds());
        }

        if ($studioId !== null && $studioId > 0) {
            $query->where('studio_id', $studioId);
        }

        return $query;
    }

    /**
     * @return array<string, int|string>
     */
    private function summarize(Builder $query, CarbonImmutable $start, CarbonImmutable $end, string $label): array
    {
        $periodQuery = (clone $query)
            ->whereBetween('appointment_at', [$start, $end]);

        return [
            'label' => $label,
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
            'total_appointments' => (clone $periodQuery)->count(),
            'completed_appointments' => (clone $periodQuery)->where('status', 'completed')->count(),
            'cancelled_appointments' => (clone $periodQuery)->where('status', 'cancelled')->count(),
            'confirmed_appointments' => (clone $periodQuery)->where('status', 'confirmed')->count(),
            'pending_appointments' => (clone $periodQuery)->where('status', 'pending')->count(),
        ];
    }
}
