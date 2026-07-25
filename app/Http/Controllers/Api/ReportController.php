<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Studio;
use App\Services\AppointmentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->where('appointment_type', 'tattoo')
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

        $items = (clone $query)
            ->select(
                DB::raw("COALESCE(NULLIF(TRIM(hotel_name), ''), 'Belirtilmeyen') as hotel_name"),
                DB::raw('count(*) as ticket_count'),
                DB::raw('sum(pax) as customer_count'),
                DB::raw("sum(case when status != 'cancelled' then coalesce(price, 0) else 0 end) as revenue"),
                DB::raw("sum(case when status = 'completed' then coalesce(price, 0) else 0 end) as completed_revenue"),
                DB::raw("sum(case when status != 'cancelled' then coalesce(deposit_amount, 0) else 0 end) as deposit_total"),
                DB::raw('max(appointment_at) as last_ticket_at'),
            )
            ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(hotel_name), ''), 'Belirtilmeyen')"))
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

        $customers = Customer::query()
            ->with('studio:id,name,company_id')
            ->whereIn('studio_id', $studioIds)
            ->where('appointments_count', '>', 1)
            ->whereHas('appointments', function ($query) use ($validated): void {
                $query
                    ->when($validated['date_from'] ?? null, fn ($builder, $date) => $builder->whereDate('appointment_at', '>=', $date))
                    ->when($validated['date_to'] ?? null, fn ($builder, $date) => $builder->whereDate('appointment_at', '<=', $date));
            })
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('hotel_name', 'like', "%{$search}%")
                        ->orWhere('room_number', 'like', "%{$search}%");
                });
            })
            ->withCount([
                'appointments as period_appointment_count' => fn ($query) => $query
                    ->when($validated['date_from'] ?? null, fn ($builder, $date) => $builder->whereDate('appointment_at', '>=', $date))
                    ->when($validated['date_to'] ?? null, fn ($builder, $date) => $builder->whereDate('appointment_at', '<=', $date)),
            ])
            ->withSum([
                'appointments as period_revenue' => fn ($query) => $query
                    ->where('status', '!=', 'cancelled')
                    ->when($validated['date_from'] ?? null, fn ($builder, $date) => $builder->whereDate('appointment_at', '>=', $date))
                    ->when($validated['date_to'] ?? null, fn ($builder, $date) => $builder->whereDate('appointment_at', '<=', $date)),
            ], 'price')
            ->orderByDesc('last_appointment_at')
            ->limit(100)
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
            if ($companyId !== null && $companyId > 0 && ! $user->managedCompanies()->whereKey($companyId)->exists()) {
                abort(403);
            }

            if ($studioId !== null && $studioId > 0 && ! $user->canManageStudio($studioId)) {
                abort(403);
            }
        }

        $query = Studio::query();

        if (! $user->hasRole(UserRole::Admin)) {
            $query->whereIn('id', $user->staffScopeStudioIds());
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
}
