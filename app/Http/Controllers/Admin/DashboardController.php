<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Studio;
use App\Models\User;
use App\Services\AppointmentReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AppointmentReportService $appointmentReportService): View
    {
        $user = $request->user();
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $accessibleStudioIds = $user?->accessibleStudioIds() ?? [];

        $appointments = Appointment::query();
        if (! $user?->hasRole(\App\Enums\UserRole::Admin)) {
            $appointments->whereIn('studio_id', $accessibleStudioIds);
        }

        if ($dateFrom) {
            $appointments->whereDate('appointment_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $appointments->whereDate('appointment_at', '<=', $dateTo);
        }

        $summary = [
            'total_appointments' => (clone $appointments)->count(),
            'cancelled_appointments' => (clone $appointments)->where('status', 'cancelled')->count(),
            'employee_count' => $user?->hasRole(\App\Enums\UserRole::Admin)
                ? User::query()->count()
                : User::query()
                    ->join('studio_user', 'users.id', '=', 'studio_user.user_id')
                    ->whereIn('studio_user.studio_id', $accessibleStudioIds)
                    ->distinct('users.id')
                    ->count('users.id'),
            'transfer_count' => (clone $appointments)->whereNotNull('assigned_driver_user_id')->count(),
        ];

        $studios = Studio::query()
            ->with('shop')
            ->when(
                ! $user?->hasRole(\App\Enums\UserRole::Admin),
                fn ($query) => $query->whereIn('id', $accessibleStudioIds)
            )
            ->withCount([
                'appointments',
                'users as active_staff_count' => fn ($query) => $query->where('studio_user.is_active', true),
            ])
            ->orderBy('name')
            ->get();

        $recentAppointments = Appointment::query()
            ->with(['assignedDriver', 'createdBy', 'studio'])
            ->when(
                ! $user?->hasRole(\App\Enums\UserRole::Admin),
                fn ($query) => $query->whereIn('studio_id', $accessibleStudioIds)
            )
            ->latest('appointment_at')
            ->take(8)
            ->get();

        $reports = $user !== null
            ? $appointmentReportService->buildPeriodReports($user)
            : [];

        return view('admin.dashboard', compact('summary', 'studios', 'recentAppointments', 'reports'));
    }
}
