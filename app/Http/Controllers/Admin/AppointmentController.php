<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Studio;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
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

    public function store(Request $request, AppointmentService $appointmentService): RedirectResponse
    {
        $validated = $request->validate([
            'studio_id'        => ['required', 'exists:studios,id'],
            'slip_image_path'  => ['nullable', 'string', 'max:2048'],
            'first_name'       => ['required', 'string', 'max:255'],
            'last_name'        => ['required', 'string', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'room_number'      => ['nullable', 'string', 'max:100'],
            'pax'              => ['required', 'integer', 'min:1', 'max:50'],
            'date'             => ['required', 'date'],
            'time'             => ['required', 'date_format:H:i'],
            'appointment_type' => ['nullable', 'string', 'in:standard,designer,tattoo'],
            'place'            => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string'],
        ]);

        $studio = Studio::query()->findOrFail($validated['studio_id']);
        abort_unless($request->user()?->canManageStudioAppointments($studio), 403);

        $appointmentService->create($studio, $request->user(), [
            'customer' => [
                'first_name'         => $validated['first_name'],
                'last_name'          => $validated['last_name'],
                'phone_country_code' => null,
                'phone_number'       => $validated['phone'] ?? null,
                'hotel_name'         => $studio->name,
                'room_number'        => $validated['room_number'] ?? null,
                'place'              => $validated['place'] ?? null,
                'photo_path'         => $validated['slip_image_path'] ?? null,
                'customer_notes'     => null,
            ],
            'appointment_type'  => $validated['appointment_type'] ?? 'standard',
            'pax'               => $validated['pax'],
            'appointment_at'    => $validated['date'].' '.$validated['time'].':00',
            'notes'             => $validated['notes'] ?? null,
            'source_image_path' => $validated['slip_image_path'] ?? null,
        ]);

        return redirect()
            ->route('admin.appointments.index', ['studio_id' => $studio->id])
            ->with('status', 'Randevu olusturuldu.');
    }

    public function show(Appointment $appointment): View
    {
        $appointment->load(['assignedDriver', 'assignedArtist', 'createdBy', 'studio']);
        abort_unless(request()->user()?->canManageStudioAppointments($appointment->studio_id), 403);

        return view('admin.appointments.show', compact('appointment'));
    }
}
