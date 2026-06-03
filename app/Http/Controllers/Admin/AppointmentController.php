<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $studioId = $request->integer('studio_id');
        $studios = Studio::query()
            ->with('shop')
            ->when(
                ! $user?->hasRole(UserRole::Admin),
                fn ($query) => $query->whereIn('id', $user?->accessibleStudioIds() ?? [])
            )
            ->orderBy('name')
            ->get();
        $selectedStudio = $studioId > 0 ? $studios->firstWhere('id', $studioId) : $studios->first();

        $appointments = collect();

        if ($selectedStudio !== null) {
            $appointments = $selectedStudio->appointments()
                ->with(['createdBy', 'assignedArtist'])
                ->latest('appointment_at')
                ->get();
        }

        $appointmentTypes = \App\Http\Controllers\Api\AppointmentController::APPOINTMENT_TYPES;

        return view('admin.appointments.index', compact('studios', 'selectedStudio', 'appointments', 'appointmentTypes'));
    }

    public function show(Appointment $appointment): View
    {
        $appointment->load(['assignedDriver', 'assignedArtist', 'createdBy', 'studio']);
        abort_unless(request()->user()?->canManageStudioAppointments($appointment->studio_id), 403);

        return view('admin.appointments.show', compact('appointment'));
    }
}
