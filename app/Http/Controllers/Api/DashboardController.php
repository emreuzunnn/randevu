<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
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

        // Normal kullanıcı için stüdyo keşif sayfası döndür
        if ($user?->hasRole(UserRole::Kullanici)) {
            return $this->discoveryResponse();
        }
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $studioId = $request->integer('studio_id');
        $accessibleStudioIds = $user?->accessibleStudioIds() ?? [];
        if (
            $studioId > 0
            && ! $user?->hasRole(UserRole::Admin)
            && ! in_array($studioId, $accessibleStudioIds, true)
        ) {
            abort(403);
        }

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
            ->where('pickup_required', true)
            ->count();
        $activeStaffCount = $studioId > 0
            ? \App\Models\Studio::query()
                ->whereKey($studioId)
                ->when(
                    ! $user?->hasRole(\App\Enums\UserRole::Admin),
                    fn ($query) => $query->whereIn('id', $accessibleStudioIds)
                )
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
            ->when($studioId > 0, fn ($query) => $query->whereKey($studioId))
            ->withCount([
                'appointments',
                'users as total_staff_count',
                'users as active_staff_count' => fn ($query) => $query->where('studio_user.is_active', true),
            ])
            ->get();

        $todayAppointments = (clone $appointmentsQuery)
            ->when($dateFrom === null && $dateTo === null, fn ($query) => $query->whereDate('appointment_at', now()->toDateString()))
            ->orderBy('appointment_at')
            ->take(12)
            ->get();

        $reportStudioId = $studioId > 0 ? $studioId : null;
        $reports = $user !== null
            ? $appointmentReportService->buildPeriodReports($user, $reportStudioId)
            : [];
        $currentReport = $user !== null
            ? $appointmentReportService->buildReport($user, 'monthly', $reportStudioId)
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
                'staff_reports' => $currentReport['staff_reports'] ?? [],
                'hotel_sources' => $currentReport['hotel_sources'] ?? [],
                'studio_revenues' => $currentReport['studio_revenues'] ?? [],
                'company_revenues' => $currentReport['company_revenues'] ?? [],
                'staff_earnings' => $currentReport['staff_earnings'] ?? [],
                'studios' => $studios->map(fn (Studio $studio): array => [
                    'id' => $studio->id,
                    'name' => $studio->name,
                    'location' => $studio->location,
                    'total_staff_count' => $studio->total_staff_count,
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
                    'pickup_required' => (bool) $appointment->pickup_required,
                ])->values(),
            ],
        ]);
    }

    private function discoveryResponse(): JsonResponse
    {
        $studios = Studio::query()
            ->with('company')
            ->where(fn ($query) => $query
                ->whereHas('company', fn ($companyQuery) => $companyQuery->where('is_active', true))
                ->orWhereNull('company_id'))
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'type'   => 'discovery',
            'data'   => [
                'studios' => $studios->map(fn (Studio $studio): array => [
                    'id'             => $studio->id,
                    'name'           => $studio->name,
                    'slug'           => $studio->slug,
                    'location'       => $studio->location,
                    'about'          => $studio->about,
                    'logo_path'      => $studio->logo_path,
                    'opening_time'   => $studio->opening_time,
                    'closing_time'   => $studio->closing_time,
                    'gallery_images' => $studio->gallery_images ?? [],
                    'company'        => $studio->company ? [
                        'id'        => $studio->company->id,
                        'name'      => $studio->company->name,
                        'logo_path' => $studio->company->logo_path,
                    ] : null,
                ])->values(),
            ],
        ]);
    }
}
