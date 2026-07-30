<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\StaffEarning;
use App\Models\Studio;
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
    public function buildReport(User $user, string $period = 'monthly', ?int $studioId = null, bool $includeStaff = true): array
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

        $report = [
            'selected_period' => $periodLabel,
            'stats'           => [
                'total_appointments' => (clone $periodQuery)->count(),
                'overall_total_appointments' => (clone $base)->count(),
                'overall_cancelled' => (clone $base)->where('status', 'cancelled')->count(),
                'overall_completed' => (clone $base)->where('status', 'completed')->count(),
                'overall_design_appointments' => (clone $base)->where('appointment_type', 'designer')->count(),
                'overall_ticket_appointments' => (clone $base)->where('appointment_type', 'tattoo')->count(),
                'cancelled'          => (clone $periodQuery)->where('status', 'cancelled')->count(),
                'completed'          => (clone $periodQuery)->where('status', 'completed')->count(),
                'this_week'          => $thisWeek,
            ],
            'weekly_data'  => $this->buildWeeklyData($base, $weekStart),
            'performance'  => $this->buildPerformance($periodQuery),
            'hotel_sources' => $this->buildHotelSources($periodQuery),
            'old_customers' => $this->buildOldCustomers($user, $start, $end, $studioId),
            'studio_revenues' => $this->buildStudioRevenues($user, $start, $end, $studioId),
            'company_revenues' => $this->buildCompanyRevenues($user, $start, $end, $studioId),
            'insight'      => $this->buildInsight($thisWeek, $lastWeekCount),
        ];

        if ($includeStaff) {
            $report['staff_reports'] = $this->buildStaffReports($user, $start, $end, $weekStart, $studioId);
            $report['staff_earnings'] = $this->buildStaffEarnings($user, $start, $end, $studioId);
        }

        return $report;
    }

    /**
     * Eski format: üç dönemlik özet (daily/monthly/yearly).
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
            'yearly' => $this->summarize(
                $this->baseQuery($user, $studioId),
                $now->startOfYear(),
                $now->endOfYear(),
                'Yıllık'
            ),
        ];
    }

    // ── Özel yardımcılar ──────────────────────────────────────

    private function baseQuery(User $user, ?int $studioId = null): Builder
    {
        $query = Appointment::query();
        $studioIds = $this->reportStudioIds($user);

        if (! $user->hasRole(UserRole::Admin)) {
            $query->whereIn('studio_id', $studioIds);
        }

        if ($studioId !== null && $studioId > 0) {
            $query->where('studio_id', $studioId);
        }

        return $query;
    }

    /**
     * @return array<int, int>
     */
    private function reportStudioIds(User $user): array
    {
        if ($user->hasAnyRole([UserRole::Admin, UserRole::Yonetici, UserRole::Supervisor])) {
            return $user->staffScopeStudioIds();
        }

        return $user->accessibleStudioIds();
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildStaffReports(
        User $viewer,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $weekStart,
        ?int $studioId = null
    ): array {
        $studioIds = $this->reportStudioIds($viewer);
        if ($studioId !== null && $studioId > 0) {
            $studioIds = in_array($studioId, $studioIds, true) || $viewer->hasRole(UserRole::Admin)
                ? [$studioId]
                : [];
        }

        if ($studioIds === []) {
            return [];
        }

        return User::query()
            ->whereHas('studios', function ($query) use ($studioIds): void {
                $query
                    ->whereIn('studios.id', $studioIds)
                    ->where('studio_user.is_active', true);
            })
            ->with(['studios' => function ($query) use ($studioIds): void {
                $query
                    ->whereIn('studios.id', $studioIds)
                    ->where('studio_user.is_active', true)
                    ->select('studios.id', 'studios.name');
            }])
            ->orderBy('name')
            ->get()
            ->map(function (User $staff) use ($studioIds, $start, $end, $weekStart): array {
                $base = $this->staffAppointmentQuery($staff, $studioIds);
                $periodQuery = (clone $base)->whereBetween('appointment_at', [$start, $end]);

                return [
                    'id' => $staff->id,
                    'name' => $staff->fullName(),
                    'role' => self::ROLE_LABELS[$staff->role?->value ?? ''] ?? ($staff->role?->value ?? ''),
                    'studio_names' => $staff->studios->pluck('name')->values()->all(),
                    'stats' => [
                        'total_appointments' => (clone $periodQuery)->count(),
                        'overall_total_appointments' => (clone $base)->count(),
                        'overall_cancelled' => (clone $base)->where('status', 'cancelled')->count(),
                        'overall_completed' => (clone $base)->where('status', 'completed')->count(),
                        'overall_design_appointments' => (clone $base)->where('appointment_type', 'designer')->count(),
                        'overall_ticket_appointments' => (clone $base)->where('appointment_type', 'tattoo')->count(),
                        'cancelled' => (clone $periodQuery)->where('status', 'cancelled')->count(),
                        'completed' => (clone $periodQuery)->where('status', 'completed')->count(),
                        'this_week' => (clone $base)
                            ->whereBetween('appointment_at', [$weekStart, $weekStart->endOfWeek()])
                            ->count(),
                    ],
                    'weekly_data' => $this->buildWeeklyData($base, $weekStart),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildHotelSources(Builder $periodQuery): array
    {
        $hotelNameExpression = "COALESCE(NULLIF(TRIM(appointments.hotel_name), ''), 'Belirtilmeyen')";

        return (clone $periodQuery)
            ->select(
                DB::raw("{$hotelNameExpression} as hotel_name"),
                DB::raw('count(*) as appointment_count'),
                DB::raw('sum(pax) as customer_count'),
                DB::raw("sum(case when status != 'cancelled' then coalesce(price, 0) else 0 end) as revenue"),
                DB::raw("sum(case when status = 'completed' then coalesce(price, 0) else 0 end) as completed_revenue"),
                DB::raw("sum(case when appointment_type = 'tattoo' and status != 'cancelled' then coalesce(price, 0) else 0 end) as ticket_revenue"),
                DB::raw("sum(case when appointment_type = 'designer' and status != 'cancelled' then coalesce(price, 0) else 0 end) as design_revenue"),
            )
            ->groupBy(DB::raw($hotelNameExpression))
            ->orderByDesc('customer_count')
            ->limit(12)
            ->get()
            ->map(fn ($row): array => [
                'hotel_name' => (string) $row->hotel_name,
                'appointment_count' => (int) $row->appointment_count,
                'customer_count' => (int) $row->customer_count,
                'revenue' => round((float) $row->revenue, 2),
                'completed_revenue' => round((float) $row->completed_revenue, 2),
                'ticket_revenue' => round((float) $row->ticket_revenue, 2),
                'design_revenue' => round((float) $row->design_revenue, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildOldCustomers(User $viewer, CarbonImmutable $start, CarbonImmutable $end, ?int $studioId = null): array
    {
        $studioIds = $this->reportStudioIds($viewer);
        if ($studioId !== null && $studioId > 0) {
            $studioIds = in_array($studioId, $studioIds, true) || $viewer->hasRole(UserRole::Admin)
                ? [$studioId]
                : [];
        }

        if (! $viewer->hasRole(UserRole::Admin) && $studioIds === []) {
            return [];
        }

        return Customer::query()
            ->when(! $viewer->hasRole(UserRole::Admin), fn ($query) => $query->whereIn('studio_id', $studioIds))
            ->when($viewer->hasRole(UserRole::Admin) && $studioId !== null && $studioId > 0, fn ($query) => $query->where('studio_id', $studioId))
            ->where('appointments_count', '>', 1)
            ->whereHas('appointments', fn ($query) => $query->whereBetween('appointment_at', [$start, $end]))
            ->with('studio:id,name')
            ->withCount([
                'appointments as period_appointment_count' => fn ($query) => $query
                    ->whereBetween('appointment_at', [$start, $end]),
            ])
            ->withSum([
                'appointments as period_revenue' => fn ($query) => $query
                    ->whereBetween('appointment_at', [$start, $end])
                    ->where('status', '!=', 'cancelled'),
            ], 'price')
            ->orderByDesc('last_appointment_at')
            ->limit(20)
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => trim($customer->first_name . ' ' . $customer->last_name) ?: 'İsimsiz müşteri',
                'phone' => trim(($customer->phone_country_code ?? '') . ' ' . ($customer->phone_number ?? '')),
                'hotel_name' => $customer->hotel_name,
                'room_number' => $customer->room_number,
                'studio_name' => $customer->studio?->name,
                'appointments_count' => (int) $customer->appointments_count,
                'period_appointment_count' => (int) $customer->period_appointment_count,
                'period_revenue' => round((float) ($customer->period_revenue ?? 0), 2),
                'first_appointment_at' => $customer->first_appointment_at?->toIso8601String(),
                'last_appointment_at' => $customer->last_appointment_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildStudioRevenues(User $viewer, CarbonImmutable $start, CarbonImmutable $end, ?int $studioId = null): array
    {
        $studioIds = $this->reportStudioIds($viewer);
        if ($studioId !== null && $studioId > 0) {
            $studioIds = in_array($studioId, $studioIds, true) || $viewer->hasRole(UserRole::Admin)
                ? [$studioId]
                : [];
        }

        if ($studioIds === []) {
            return [];
        }

        return Studio::query()
            ->whereIn('id', $studioIds)
            ->with('company')
            ->withCount([
                'appointments as appointment_count' => fn ($query) => $query
                    ->whereBetween('appointment_at', [$start, $end]),
                'appointments as completed_count' => fn ($query) => $query
                    ->whereBetween('appointment_at', [$start, $end])
                    ->where('status', 'completed'),
            ])
            ->withSum([
                'appointments as revenue' => fn ($query) => $query
                    ->whereBetween('appointment_at', [$start, $end])
                    ->where('status', '!=', 'cancelled'),
            ], 'price')
            ->withSum([
                'appointments as completed_revenue' => fn ($query) => $query
                    ->whereBetween('appointment_at', [$start, $end])
                    ->where('status', 'completed'),
            ], 'price')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn (Studio $studio): array => [
                'id' => $studio->id,
                'name' => $studio->name,
                'company_name' => $studio->company?->name,
                'appointment_count' => (int) $studio->appointment_count,
                'completed_count' => (int) $studio->completed_count,
                'revenue' => round((float) ($studio->revenue ?? 0), 2),
                'completed_revenue' => round((float) ($studio->completed_revenue ?? 0), 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCompanyRevenues(User $viewer, CarbonImmutable $start, CarbonImmutable $end, ?int $studioId = null): array
    {
        $studioIds = $this->reportStudioIds($viewer);
        if ($studioId !== null && $studioId > 0) {
            $studioIds = in_array($studioId, $studioIds, true) || $viewer->hasRole(UserRole::Admin)
                ? [$studioId]
                : [];
        }

        if ($studioIds === []) {
            return [];
        }

        $companies = Company::query()
            ->whereHas('studios', fn ($query) => $query->whereIn('studios.id', $studioIds))
            ->withCount([
                'studios as studio_count' => fn ($query) => $query->whereIn('studios.id', $studioIds),
            ])
            ->orderBy('name')
            ->get();

        return $companies
            ->map(function (Company $company) use ($studioIds, $start, $end): array {
                $companyStudioIds = Studio::query()
                    ->where('company_id', $company->id)
                    ->whereIn('id', $studioIds)
                    ->pluck('id');

                $appointments = Appointment::query()
                    ->whereIn('studio_id', $companyStudioIds)
                    ->whereBetween('appointment_at', [$start, $end]);

                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'studio_count' => (int) $company->studio_count,
                    'appointment_count' => (clone $appointments)->count(),
                    'completed_count' => (clone $appointments)->where('status', 'completed')->count(),
                    'revenue' => round((float) (clone $appointments)
                        ->where('status', '!=', 'cancelled')
                        ->sum('price'), 2),
                    'completed_revenue' => round((float) (clone $appointments)
                        ->where('status', 'completed')
                        ->sum('price'), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildStaffEarnings(User $viewer, CarbonImmutable $start, CarbonImmutable $end, ?int $studioId = null): array
    {
        $studioIds = $this->reportStudioIds($viewer);
        if ($studioId !== null && $studioId > 0) {
            $studioIds = in_array($studioId, $studioIds, true) || $viewer->hasRole(UserRole::Admin)
                ? [$studioId]
                : [];
        }

        if ($studioIds === []) {
            return [];
        }

        return StaffEarning::query()
            ->with(['user', 'studio'])
            ->whereIn('studio_id', $studioIds)
            ->whereHas('appointment', fn ($query) => $query->whereBetween('appointment_at', [$start, $end]))
            ->get()
            ->groupBy('user_id')
            ->map(function ($earnings): array {
                /** @var StaffEarning $first */
                $first = $earnings->first();
                $pending = $earnings->where('status', 'pending');
                $paid = $earnings->where('status', 'paid');

                return [
                    'user_id' => $first->user_id,
                    'name' => $first->user?->fullName() ?? 'Silinmiş kullanıcı',
                    'role' => self::ROLE_LABELS[$first->role] ?? $first->role,
                    'studio_names' => $earnings
                        ->pluck('studio.name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'earning_count' => $earnings->count(),
                    'gross_amount' => round((float) $earnings->sum('gross_amount'), 2),
                    'earning_amount' => round((float) $earnings->sum('earning_amount'), 2),
                    'pending_amount' => round((float) $pending->sum('earning_amount'), 2),
                    'paid_amount' => round((float) $paid->sum('earning_amount'), 2),
                ];
            })
            ->sortByDesc('earning_amount')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $studioIds
     */
    private function staffAppointmentQuery(User $staff, array $studioIds): Builder
    {
        return Appointment::query()
            ->whereIn('studio_id', $studioIds)
            ->where(function (Builder $query) use ($staff): void {
                $query
                    ->where('created_by_user_id', $staff->id)
                    ->orWhere('assigned_artist_user_id', $staff->id);

                if ($staff->hasRole(UserRole::Sofor)) {
                    $query->orWhere('pickup_required', true);
                }
            });
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
            'designer_appointments'  => (clone $periodQuery)->where('appointment_type', 'designer')->count(),
            'ticket_appointments'    => (clone $periodQuery)->where('appointment_type', 'tattoo')->count(),
            'completed_appointments' => (clone $periodQuery)->where('status', 'completed')->count(),
            'cancelled_appointments' => (clone $periodQuery)->where('status', 'cancelled')->count(),
            'confirmed_appointments' => (clone $periodQuery)->where('status', 'confirmed')->count(),
            'active_appointments'    => (clone $periodQuery)->where('status', 'confirmed')->count(),
        ];
    }
}
