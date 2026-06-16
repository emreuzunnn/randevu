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
        $appointment->load(['assignedArtist', 'createdBy', 'studio.shop']);
        $user = request()->user();
        $canAccess = false;

        if ($user !== null) {
            $canAccess =
                ($appointment->studio_id !== null && $user->canManageStudioAppointments($appointment->studio_id)) ||
                (int) $appointment->created_by_user_id === (int) $user->id ||
                (int) $appointment->assigned_artist_user_id === (int) $user->id;

            if (! $canAccess && $user->hasRole(UserRole::Sofor) && $appointment->pickup_required && $appointment->studio?->shop_id !== null) {
                $driverShopIds = Studio::query()
                    ->whereIn('id', $user->studios()->pluck('studios.id'))
                    ->pluck('shop_id')
                    ->filter()
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                $canAccess = in_array((int) $appointment->studio->shop_id, $driverShopIds, true);
            }
        }

        abort_unless($canAccess, 403);

        $limitedView = $user?->hasRole(UserRole::Artist) === true
            && ! $user->isIndependentProfessional()
            && $appointment->appointment_type === 'tattoo';
        $canSeePrice = $appointment->status === 'completed'
            || $user?->hasAnyRole([UserRole::Admin, UserRole::Yonetici, UserRole::Supervisor]) === true;

        return view('admin.appointments.show', compact('appointment', 'limitedView', 'canSeePrice'));
    }
}
