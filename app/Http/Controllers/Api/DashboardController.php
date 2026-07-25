<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Studio;
use App\Services\AppointmentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    private const DISCOVERY_STUDIOS_ENABLED_KEY = 'discovery_studios_enabled';

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

        $baseAppointmentsQuery = \App\Models\Appointment::query();
        if (! $user?->hasRole(\App\Enums\UserRole::Admin)) {
            $baseAppointmentsQuery->whereIn('studio_id', $accessibleStudioIds);
        }
        if ($studioId > 0) {
            $baseAppointmentsQuery->where('studio_id', $studioId);
        }

        $appointmentsQuery = clone $baseAppointmentsQuery;
        if ($dateFrom !== null) {
            $appointmentsQuery->whereDate('appointment_at', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $appointmentsQuery->whereDate('appointment_at', '<=', $dateTo);
        }

        $totalAppointments = (clone $baseAppointmentsQuery)->count();
        $designAppointments = (clone $baseAppointmentsQuery)
            ->where('appointment_type', 'designer')
            ->count();
        $ticketAppointments = (clone $baseAppointmentsQuery)
            ->where('appointment_type', 'tattoo')
            ->count();
        $cancelledAppointments = (clone $baseAppointmentsQuery)
            ->where('status', 'cancelled')
            ->count();
        $transferCount = (clone $baseAppointmentsQuery)
            ->where('pickup_required', true)
            ->count();
        $activeStaffCount = DB::table('studio_user')
            ->when(
                ! $user?->hasRole(\App\Enums\UserRole::Admin),
                fn ($query) => $query->whereIn('studio_id', $accessibleStudioIds)
            )
            ->when($studioId > 0, fn ($query) => $query->where('studio_id', $studioId))
            ->where('is_active', true)
            ->distinct('user_id')
            ->count('user_id');

        $periodTotalAppointments = (clone $appointmentsQuery)->count();
        $periodDesignAppointments = (clone $appointmentsQuery)
            ->where('appointment_type', 'designer')
            ->count();
        $periodTicketAppointments = (clone $appointmentsQuery)
            ->where('appointment_type', 'tattoo')
            ->count();
        $periodCancelledAppointments = (clone $appointmentsQuery)
            ->where('status', 'cancelled')
            ->count();
        $periodTransferCount = (clone $appointmentsQuery)
            ->where('pickup_required', true)
            ->count();
        $studios = Studio::query()
            ->when(
                ! $user?->hasRole(\App\Enums\UserRole::Admin),
                fn ($query) => $query->whereIn('id', $accessibleStudioIds)
            )
            ->when($studioId > 0, fn ($query) => $query->whereKey($studioId))
            ->withCount([
                'appointments',
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
                    'design_appointments' => $designAppointments,
                    'designer_appointments' => $designAppointments,
                    'ticket_appointments' => $ticketAppointments,
                    'cancelled_appointments' => $cancelledAppointments,
                    'transfer_count' => $transferCount,
                    'active_staff_count' => $activeStaffCount,
                ],
                'period_summary' => [
                    'total_appointments' => $periodTotalAppointments,
                    'design_appointments' => $periodDesignAppointments,
                    'designer_appointments' => $periodDesignAppointments,
                    'ticket_appointments' => $periodTicketAppointments,
                    'cancelled_appointments' => $periodCancelledAppointments,
                    'transfer_count' => $periodTransferCount,
                    'active_staff_count' => $activeStaffCount,
                ],
                'reports' => $reports,
                'hotel_sources' => $currentReport['hotel_sources'] ?? [],
                'old_customers' => $currentReport['old_customers'] ?? [],
                'studio_revenues' => $currentReport['studio_revenues'] ?? [],
                'company_revenues' => $currentReport['company_revenues'] ?? [],
                'staff_earnings' => $currentReport['staff_earnings'] ?? [],
                'studios' => $studios->map(fn (Studio $studio): array => [
                    'id' => $studio->id,
                    'name' => $studio->name,
                    'location' => $studio->location,
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
        if (! AppSetting::boolean(self::DISCOVERY_STUDIOS_ENABLED_KEY, true)) {
            return response()->json([
                'status' => 'success',
                'type'   => 'discovery',
                'data'   => [
                    'studios' => [],
                ],
            ]);
        }

        $studios = Studio::query()
            ->with('company')
            ->when(Schema::hasColumn('studios', 'discovery_visible'), fn ($query) => $query->where('discovery_visible', true))
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
