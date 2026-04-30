<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Services\AppointmentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, AppointmentReportService $appointmentReportService): JsonResponse
    {
        $user = $request->user();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $studioId = $request->integer('studio_id');
        $accessibleStudioIds = $user?->accessibleStudioIds() ?? [];

        $appointmentsQuery = \App\Models\Appointment::query();
        if (! $user?->hasRole(\App\Enums\UserRole::Admin)) {
            $appointmentsQuery->whereIn('studio_id', $accessibleStudioIds);
        }
        if ($studioId > 0) {
            $appointmentsQuery->where('studio_id', $studioId);
        }

        if ($dateFrom !== null) {
            $appointmentsQuery->whereDate('appointment_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $appointmentsQuery->whereDate('appointment_at', '<=', $dateTo);
        }

        $totalAppointments = (clone $appointmentsQuery)->count();
        $cancelledAppointments = (clone $appointmentsQuery)
            ->where('status', 'cancelled')
            ->count();
        $transferCount = (clone $appointmentsQuery)
            ->whereNotNull('assigned_driver_user_id')
            ->count();
        $activeStaffCount = $studioId > 0
            ? \App\Models\Studio::query()
                ->whereKey($studioId)
                ->withCount([
                    'users as active_staff_count' => fn ($query) => $query->where('studio_user.is_active', true),
                ])
                ->value('active_staff_count')
            : \App\Models\Studio::query()
                ->join('studio_user', 'studios.id', '=', 'studio_user.studio_id')
                ->when(
                    ! $user?->hasRole(\App\Enums\UserRole::Admin),
                    fn ($query) => $query->whereIn('studios.id', $accessibleStudioIds)
                )
                ->where('studio_user.is_active', true)
                ->count();

        $studios = Studio::query()
            ->when(
                ! $user?->hasRole(\App\Enums\UserRole::Admin),
                fn ($query) => $query->whereIn('id', $accessibleStudioIds)
            )
            ->withCount([
                'appointments',
                'users as active_staff_count' => fn ($query) => $query->where('studio_user.is_active', true),
            ])
            ->get();

        $todayAppointments = (clone $appointmentsQuery)
            ->with(['assignedDriver'])
            ->when($dateFrom === null && $dateTo === null, fn ($query) => $query->whereDate('appointment_at', now()->toDateString()))
            ->orderBy('appointment_at')
            ->take(12)
            ->get();

        $reports = $user !== null
            ? $appointmentReportService->buildPeriodReports($user, $studioId > 0 ? $studioId : null)
            : [];

        return response()->json([
            'data' => [
                'summary' => [
                    'total_appointments' => $totalAppointments,
                    'cancelled_appointments' => $cancelledAppointments,
                    'active_staff_count' => $activeStaffCount,
                    'transfer_count' => $transferCount,
                ],
                'reports' => $reports,
                'studios' => $studios->map(fn (Studio $studio): array => [
                    'id' => $studio->id,
                    'name' => $studio->name,
                    'location' => $studio->location,
                    'active_staff_count' => $studio->active_staff_count,
                    'appointments_count' => $studio->appointments_count,
                ])->values(),
                'today_appointments' => $todayAppointments->map(fn ($appointment): array => [
                    'id' => $appointment->id,
                    'customer' => [
                        'first_name' => $appointment->first_name,
                        'last_name' => $appointment->last_name,
                        'hotel_name' => $appointment->hotel_name,
                    ],
                    'pax' => $appointment->pax,
                    'appointment_at' => optional($appointment->appointment_at)->toIso8601String(),
                    'status' => $appointment->status,
                    'studio' => $appointment->studio?->name ?? Studio::query()->whereKey($appointment->studio_id)->value('name'),
                    'driver' => $appointment->assignedDriver ? [
                        'id' => $appointment->assignedDriver->id,
                        'name' => $appointment->assignedDriver->fullName(),
                    ] : null,
                ])->values(),
            ],
        ]);
    }
}
