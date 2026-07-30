<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Studio;
use App\Services\AppointmentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index(Request $request, AppointmentReportService $reportService): JsonResponse
    {
        $user     = $request->user();
        $period   = $request->input('period', 'monthly');
        $studioId = $request->integer('studio_id') ?: null;

        $data = $reportService->buildReport($user, $period, $studioId);

        return response()->json([
            'status' => 'success',
            'code'   => 200,
            'data'   => $data,
        ]);
    }

    public function hotelRevenues(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole([UserRole::Admin, UserRole::Yonetici]), 403);

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'studio_id'  => ['nullable', 'integer', 'exists:studios,id'],
            'search'     => ['nullable', 'string', 'max:255'],
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date'],
        ]);

        $studioIds = $this->scopedStudioIds(
            $user,
            $validated['company_id'] ?? null,
            $validated['studio_id'] ?? null,
        );

        $query = Appointment::query()
            ->whereIn('studio_id', $studioIds)
            ->where(fn ($builder) => $this->ticketOnly($builder))
            ->when($validated['date_from'] ?? null, fn ($builder, $date) => $builder->whereDate('appointment_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($builder, $date) => $builder->whereDate('appointment_at', '<=', $date))
            ->when($validated['search'] ?? null, function ($builder, string $search): void {
                $builder->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('hotel_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('room_number', 'like', "%{$search}%");
                });
            });

        $totals = [
            'ticket_count' => (clone $query)->count(),
            'customer_count' => (int) (clone $query)->sum('pax'),
            'revenue' => round((float) (clone $query)
                ->where('status', '!=', 'cancelled')
                ->sum('price'), 2),
            'completed_revenue' => round((float) (clone $query)
                ->where('status', 'completed')
                ->sum('price'), 2),
            'deposit_total' => round((float) (clone $query)
                ->where('status', '!=', 'cancelled')
                ->sum('deposit_amount'), 2),
        ];

        $hotelNameExpression = "COALESCE(NULLIF(TRIM(appointments.hotel_name), ''), 'Belirtilmeyen')";

        $items = (clone $query)
            ->select(
                DB::raw("{$hotelNameExpression} as hotel_name"),
                DB::raw('count(*) as ticket_count'),
                DB::raw('sum(pax) as customer_count'),
                DB::raw("sum(case when status != 'cancelled' then coalesce(price, 0) else 0 end) as revenue"),
                DB::raw("sum(case when status = 'completed' then coalesce(price, 0) else 0 end) as completed_revenue"),
                DB::raw("sum(case when status != 'cancelled' then coalesce(deposit_amount, 0) else 0 end) as deposit_total"),
                DB::raw('max(appointment_at) as last_ticket_at'),
            )
            ->groupBy('appointments.hotel_name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row): array => [
                'hotel_name' => (string) $row->hotel_name,
                'ticket_count' => (int) $row->ticket_count,
                'customer_count' => (int) $row->customer_count,
                'revenue' => round((float) $row->revenue, 2),
                'completed_revenue' => round((float) $row->completed_revenue, 2),
                'deposit_total' => round((float) $row->deposit_total, 2),
                'last_ticket_at' => $row->last_ticket_at,
            ])
            ->values();

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'data' => [
                'totals' => $totals,
                'items' => $items,
            ],
        ]);
    }

    public function oldCustomers(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole([UserRole::Admin, UserRole::Yonetici]), 403);

        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'studio_id'  => ['nullable', 'integer', 'exists:studios,id'],
            'search'     => ['nullable', 'string', 'max:255'],
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date'],
        ]);

        $studioIds = $this->scopedStudioIds(
            $user,
            $validated['company_id'] ?? null,
            $validated['studio_id'] ?? null,
        );

        $dateFrom = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : null;
        $dateTo = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : null;
        $search = mb_strtolower(trim((string) ($validated['search'] ?? '')));

        $appointments = Appointment::query()
            ->with('studio:id,name,company_id')
            ->whereIn('studio_id', $studioIds)
            ->where(function ($query): void {
                $query
                    ->where(function ($phoneQuery): void {
                        $phoneQuery
                            ->whereNotNull('phone_number')
                            ->where('phone_number', '!=', '');
                    })
                    ->orWhere(function ($nameQuery): void {
                        $nameQuery
                            ->whereNotNull('first_name')
                            ->where('first_name', '!=', '')
                            ->whereNotNull('last_name')
                            ->where('last_name', '!=', '');
                    });
            })
            ->get();

        $customers = $appointments
            ->groupBy(fn (Appointment $appointment): string => $this->oldCustomerGroupKey($appointment))
            ->filter(fn ($group): bool => $group->count() > 1)
            ->map(function ($group) use ($dateFrom, $dateTo, $search): ?array {
                if ($search !== '' && ! $group->contains(fn (Appointment $appointment): bool => $this->appointmentMatchesSearch($appointment, $search))) {
                    return null;
                }

                $periodAppointments = $group->filter(function (Appointment $appointment) use ($dateFrom, $dateTo): bool {
                    if ($dateFrom === null && $dateTo === null) {
                        return true;
                    }

                    if ($appointment->appointment_at === null) {
                        return false;
                    }

                    return ($dateFrom === null || $appointment->appointment_at->greaterThanOrEqualTo($dateFrom))
                        && ($dateTo === null || $appointment->appointment_at->lessThanOrEqualTo($dateTo));
                });

                if ($periodAppointments->isEmpty()) {
                    return null;
                }

                $first = $group->sortBy('appointment_at')->first();
                $last = $group->sortByDesc('appointment_at')->first();
                $periodRevenue = $periodAppointments
                    ->where('status', '!=', 'cancelled')
                    ->sum(fn (Appointment $appointment): float => (float) ($appointment->price ?? 0));

                return [
                    'id' => $last?->customer_id ?? $last?->id,
                    'name' => trim(($last?->first_name ?? '') . ' ' . ($last?->last_name ?? '')) ?: 'İsimsiz müşteri',
                    'phone' => trim(($last?->phone_country_code ?? '') . ' ' . ($last?->phone_number ?? '')),
                    'hotel_name' => $last?->hotel_name,
                    'room_number' => $last?->room_number,
                    'studio_name' => $last?->studio?->name,
                    'appointments_count' => $group->count(),
                    'period_appointment_count' => $periodAppointments->count(),
                    'period_revenue' => round((float) $periodRevenue, 2),
                    'first_appointment_at' => $first?->appointment_at?->toIso8601String(),
                    'last_appointment_at' => $last?->appointment_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->sortByDesc('last_appointment_at')
            ->take(100)
            ->values();

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'data' => [
                'totals' => [
                    'customer_count' => $customers->count(),
                    'appointment_count' => $customers->sum('period_appointment_count'),
                    'revenue' => round((float) $customers->sum('period_revenue'), 2),
                ],
                'items' => $customers,
            ],
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function scopedStudioIds($user, ?int $companyId = null, ?int $studioId = null): array
    {
        if (! $user->hasRole(UserRole::Admin)) {
            $companyIds = $this->managerScopedCompanyIds($user);
            $baseStudioIds = $this->managerScopedStudioIds($user);

            if ($companyId !== null && $companyId > 0 && ! in_array($companyId, $companyIds, true)) {
                abort(403);
            }

            if ($studioId !== null && $studioId > 0 && ! in_array($studioId, $baseStudioIds, true)) {
                abort(403);
            }
        }

        $query = Studio::query();

        if (! $user->hasRole(UserRole::Admin)) {
            $query->whereIn('id', $baseStudioIds ?? $this->managerScopedStudioIds($user));
        }

        if ($companyId !== null && $companyId > 0) {
            $query->where('company_id', $companyId);
        }

        if ($studioId !== null && $studioId > 0) {
            $query->whereKey($studioId);
        }

        $ids = $query->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

        return $ids;
    }

    private function ticketOnly($query): void
    {
        $query
            ->where('appointment_type', 'tattoo')
            ->orWhereNotNull('ticket_types')
            ->orWhereNotNull('tattoo_type')
            ->orWhereNotNull('deposit_amount')
            ->orWhereNotNull('payment_method');
    }

    private function oldCustomerGroupKey(Appointment $appointment): string
    {
        $phoneNumber = preg_replace('/\D+/', '', (string) $appointment->phone_number) ?? '';
        $phoneCountryCode = preg_replace('/\D+/', '', (string) $appointment->phone_country_code) ?? '';

        if ($phoneNumber !== '') {
            return implode('|', [
                $appointment->studio_id,
                'phone',
                $phoneCountryCode,
                $phoneNumber,
            ]);
        }

        return implode('|', [
            $appointment->studio_id,
            'name',
            mb_strtolower((string) $appointment->first_name),
            mb_strtolower((string) $appointment->last_name),
        ]);
    }

    private function appointmentMatchesSearch(Appointment $appointment, string $search): bool
    {
        $haystack = mb_strtolower(implode(' ', [
            $appointment->first_name,
            $appointment->last_name,
            $appointment->phone_country_code,
            $appointment->phone_number,
            $appointment->hotel_name,
            $appointment->room_number,
            $appointment->studio?->name,
        ]));

        return str_contains($haystack, $search);
    }

    /**
     * @return array<int, int>
     */
    private function managerScopedStudioIds($user): array
    {
        $ids = $user->staffScopeStudioIds();

        $ownedStudioIds = Studio::query()
            ->where('owner_user_id', $user->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $activeStudioIds = $user->studios()
            ->wherePivot('is_active', true)
            ->pluck('studios.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_values(array_unique(array_map('intval', [
            ...$ids,
            ...$ownedStudioIds,
            ...$activeStudioIds,
        ])));
    }

    /**
     * @return array<int, int>
     */
    private function managerScopedCompanyIds($user): array
    {
        $directCompanyIds = $user->managedCompanies()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $studioCompanyIds = Studio::query()
            ->whereIn('id', $this->managerScopedStudioIds($user))
            ->whereNotNull('company_id')
            ->pluck('company_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_values(array_unique([
            ...$directCompanyIds,
            ...$studioCompanyIds,
        ]));
    }
}
