<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Studio;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
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
            'transfer_count' => (clone $appointments)->where('pickup_required', true)->count(),
        ];

        $now = CarbonImmutable::now();
        $periodReports = collect([
            [
                'label' => 'Günlük',
                'start' => $now->startOfDay(),
                'end' => $now->endOfDay(),
            ],
            [
                'label' => 'Aylık',
                'start' => $now->startOfMonth(),
                'end' => $now->endOfMonth(),
            ],
            [
                'label' => 'Yıllık',
                'start' => $now->startOfYear(),
                'end' => $now->endOfYear(),
            ],
        ])->map(function (array $period) use ($appointments): array {
            $query = (clone $appointments)->whereBetween('appointment_at', [$period['start'], $period['end']]);

            return [
                'label' => $period['label'],
                'date_from' => $period['start']->format('d.m.Y'),
                'date_to' => $period['end']->format('d.m.Y'),
                'total' => (clone $query)->count(),
                'completed' => (clone $query)->where('status', 'completed')->count(),
                'active' => (clone $query)->where('status', 'confirmed')->count(),
                'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            ];
        })->values();

        $studios = Studio::query()
            ->with('company')
            ->when(
                ! $user?->hasRole(\App\Enums\UserRole::Admin),
                fn ($query) => $query->whereIn('id', $accessibleStudioIds)
            )
            ->withCount('appointments')
            ->orderBy('name')
            ->get();

        $recentAppointments = Appointment::query()
            ->with(['createdBy', 'studio'])
            ->when(
                ! $user?->hasRole(\App\Enums\UserRole::Admin),
                fn ($query) => $query->whereIn('studio_id', $accessibleStudioIds)
            )
            ->latest('appointment_at')
            ->take(8)
            ->get();

        return view('admin.dashboard', compact('summary', 'periodReports', 'studios', 'recentAppointments'));
    }
}
