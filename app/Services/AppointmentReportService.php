<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AppointmentReportService
{
    private const ROLE_LABELS = [
        'admin'      => 'Admin',
        'yonetici'   => 'Yönetici',
        'supervisor' => 'Süpervizör',
        'sofor'      => 'Şoför',
        'calisan'    => 'Çalışan',
    ];

    private const DAY_NAMES = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];

    /**
     * Yeni format: tek dönem raporu.
     *
     * @return array<string, mixed>
     */
    public function buildReport(User $user, string $period = 'monthly', ?int $studioId = null): array
    {
        $now = CarbonImmutable::now();

        [$start, $end, $periodLabel] = match ($period) {
            'daily', 'today' => [$now->startOfDay(),                    $now->endOfDay(),   'Bugün'],
            'weekly'         => [$now->startOfWeek(),                   $now->endOfWeek(),  'Bu Hafta'],
            'quarterly'      => [$now->subMonths(2)->startOfMonth(),    $now->endOfMonth(), 'Son 3 Ay'],
            default          => [$now->startOfMonth(),                  $now->endOfMonth(), 'Bu Ay'],
        };

        $base        = $this->baseQuery($user, $studioId);
        $periodQuery = (clone $base)->whereBetween('appointment_at', [$start, $end]);

        $weekStart = $now->startOfWeek();
        $weekEnd   = $now->endOfWeek();
        $thisWeek  = (clone $base)->whereBetween('appointment_at', [$weekStart, $weekEnd])->count();

        $lastWeekCount = (clone $base)
            ->whereBetween('appointment_at', [$weekStart->subWeek(), $weekEnd->subWeek()])
            ->count();

        return [
            'selected_period' => $periodLabel,
            'stats'           => [
                'total_appointments' => (clone $periodQuery)->count(),
                'cancelled'          => (clone $periodQuery)->where('status', 'cancelled')->count(),
                'completed'          => (clone $periodQuery)->where('status', 'completed')->count(),
                'this_week'          => $thisWeek,
            ],
            'weekly_data'  => $this->buildWeeklyData($base, $weekStart),
            'performance'  => $this->buildPerformance($periodQuery),
            'insight'      => $this->buildInsight($thisWeek, $lastWeekCount),
        ];
    }

    /**
     * Eski format: üç dönemlik özet (daily/monthly/quarterly).
     *
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
                'Günlük'
            ),
            'monthly' => $this->summarize(
                $this->baseQuery($user, $studioId),
                $now->startOfMonth(),
                $now->endOfMonth(),
                'Aylık'
            ),
            'quarterly' => $this->summarize(
                $this->baseQuery($user, $studioId),
                $now->subMonths(2)->startOfMonth(),
                $now->endOfMonth(),
                '3 Aylık'
            ),
        ];
    }

    // ── Özel yardımcılar ──────────────────────────────────────

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
     * @return array<int, array{day: string, value: int}>
     */
    private function buildWeeklyData(Builder $base, CarbonImmutable $weekStart): array
    {
        return array_map(function (int $i) use ($base, $weekStart): array {
            $day   = $weekStart->addDays($i);
            $count = (clone $base)->whereDate('appointment_at', $day->toDateString())->count();

            return ['day' => self::DAY_NAMES[$i], 'value' => $count];
        }, range(0, 6));
    }

    /**
     * @return array<int, array{name: string, role: string, appointments: int, rating: float|null}>
     */
    private function buildPerformance(Builder $periodQuery): array
    {
        return (clone $periodQuery)
            ->select('created_by_user_id', DB::raw('count(*) as appointments_count'))
            ->whereNotNull('created_by_user_id')
            ->groupBy('created_by_user_id')
            ->orderByDesc('appointments_count')
            ->limit(5)
            ->get()
            ->map(function (Appointment $row): ?array {
                /** @var User|null $user */
                $user = User::query()->find($row->created_by_user_id);

                if ($user === null) {
                    return null;
                }

                return [
                    'name'         => $user->fullName(),
                    'role'         => self::ROLE_LABELS[$user->role?->value ?? ''] ?? ($user->role?->value ?? ''),
                    'appointments' => (int) $row->appointments_count,
                    'rating'       => $user->rating !== null ? round((float) $user->rating, 1) : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function buildInsight(int $thisWeek, int $lastWeek): string
    {
        if ($lastWeek === 0) {
            return $thisWeek > 0
                ? "Bu hafta {$thisWeek} yeni randevu oluşturuldu."
                : 'Bu hafta henüz randevu kaydı bulunmuyor.';
        }

        $diff    = $thisWeek - $lastWeek;
        $percent = abs((int) round(($diff / $lastWeek) * 100));

        if ($diff > 0) {
            return "Bu hafta randevu yoğunluğu %{$percent} arttı.";
        }

        if ($diff < 0) {
            return "Bu hafta randevu yoğunluğu %{$percent} azaldı.";
        }

        return 'Bu hafta randevu yoğunluğu geçen haftayla aynı seviyede.';
    }

    /**
     * @return array<string, int|string>
     */
    private function summarize(Builder $query, CarbonImmutable $start, CarbonImmutable $end, string $label): array
    {
        $periodQuery = (clone $query)->whereBetween('appointment_at', [$start, $end]);

        return [
            'label'                  => $label,
            'date_from'              => $start->toDateString(),
            'date_to'                => $end->toDateString(),
            'total_appointments'     => (clone $periodQuery)->count(),
            'completed_appointments' => (clone $periodQuery)->where('status', 'completed')->count(),
            'cancelled_appointments' => (clone $periodQuery)->where('status', 'cancelled')->count(),
            'confirmed_appointments' => (clone $periodQuery)->where('status', 'confirmed')->count(),
            'active_appointments'    => (clone $periodQuery)->where('status', 'confirmed')->count(),
        ];
    }
}
