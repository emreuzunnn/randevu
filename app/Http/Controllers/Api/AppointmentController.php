<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Studio;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function support(Studio $studio, Request $request): JsonResponse
    {
        abort_unless($request->user()?->canManageStudioAppointments($studio), 403);

        $drivers = $studio->users()
            ->wherePivot('role', \App\Enums\UserRole::Sofor->value)
            ->wherePivot('is_active', true)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.surname', 'users.phone']);

        return response()->json([
            'data' => [
                'drivers' => $drivers->map(fn ($driver): array => [
                    'id' => $driver->id,
                    'name' => $driver->fullName(),
                    'phone' => $driver->phone,
                ])->values(),
                'statuses' => ['pending', 'confirmed', 'completed', 'cancelled', 'rescheduled'],
            ],
        ]);
    }

    public function show(Studio $studio, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->studio_id !== $studio->id, 404);

        $appointment->load(['createdBy', 'assignedDriver']);

        return response()->json([
            'data' => [
                'id' => $appointment->id,
                'appointment_type' => $appointment->appointment_type,
                'full_name' => trim($appointment->first_name.' '.$appointment->last_name),
                'date' => optional($appointment->appointment_at)->format('Y-m-d'),
                'time' => optional($appointment->appointment_at)->format('H:i'),
                'place' => $appointment->place,
                'driver' => $appointment->assignedDriver ? [
                    'id' => $appointment->assignedDriver->id,
                    'name' => $appointment->assignedDriver->name,
                    'surname' => $appointment->assignedDriver->surname,
                ] : null,
                'created_by' => $appointment->createdBy ? [
                    'id' => $appointment->createdBy->id,
                    'name' => $appointment->createdBy->name,
                    'surname' => $appointment->createdBy->surname,
                ] : null,
                'status' => $appointment->status,
            ],
        ]);
    }

    public function checkCustomerStatus(
        Request $request,
        Studio $studio,
        AppointmentService $appointmentService
    ): JsonResponse {
        $validated = $request->validate([
            'customer.first_name' => ['required', 'string', 'max:255'],
            'customer.last_name' => ['required', 'string', 'max:255'],
            'customer.phone_country_code' => ['nullable', 'string', 'max:10'],
            'customer.phone_number' => ['nullable', 'string', 'max:30'],
            'customer.hotel_name' => ['nullable', 'string', 'max:255'],
        ]);

        $status = $appointmentService->checkCustomerStatus($studio, $validated['customer']);

        return response()->json([
            'data' => [
                'is_old_customer' => $status['is_old_customer'],
                'last_appointment_id' => $status['matched_appointment']?->id,
                'customer_notes' => $status['matched_appointment']?->customer_notes,
            ],
        ]);
    }

    public function index(Studio $studio): JsonResponse
    {
        $appointments = $studio->appointments()
            ->with(['createdBy', 'assignedDriver'])
            ->orderBy('appointment_at')
            ->get();

        return response()->json([
            'data' => $appointments->map(fn ($appointment): array => [
                'id' => $appointment->id,
                'customer' => [
                    'first_name' => $appointment->first_name,
                    'last_name' => $appointment->last_name,
                    'phone_country_code' => $appointment->phone_country_code,
                    'phone_number' => $appointment->phone_number,
                    'hotel_name' => $appointment->hotel_name,
                    'room_number' => $appointment->room_number,
                    'customer_notes' => $appointment->customer_notes,
                ],
                'pax' => $appointment->pax,
                'appointment_at' => optional($appointment->appointment_at)->toIso8601String(),
                'status' => $appointment->status,
                'notes' => $appointment->notes,
                'source_image_path' => $appointment->source_image_path,
                'assigned_driver_user_id' => $appointment->assigned_driver_user_id,
                'driver' => $appointment->assignedDriver ? [
                    'id' => $appointment->assignedDriver->id,
                    'name' => $appointment->assignedDriver->fullName(),
                    'phone' => $appointment->assignedDriver->phone,
                    'rating' => $appointment->assignedDriver->rating,
                ] : null,
                'studio' => $studio->name,
                'created_at' => optional($appointment->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(Request $request, Studio $studio, AppointmentService $appointmentService): JsonResponse
    {
        $validated = $request->validate([
            'slip_image_path' => ['nullable', 'string', 'max:2048'],
            'customer.first_name' => ['required', 'string', 'max:255'],
            'customer.last_name' => ['required', 'string', 'max:255'],
            'customer.phone_country_code' => ['nullable', 'string', 'max:10'],
            'customer.phone_number' => ['nullable', 'string', 'max:30'],
            'customer.hotel_name' => ['nullable', 'string', 'max:255'],
            'customer.room_number' => ['nullable', 'string', 'max:100'],
            'customer.customer_notes' => ['nullable', 'string'],
            'pax' => ['required', 'integer', 'min:1', 'max:50'],
            'appointment_at' => ['required', 'date'],
            'appointment_type' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'source_image_path' => ['nullable', 'string', 'max:2048'],
            'assigned_driver_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $appointment = $appointmentService->create($studio, $request->user(), [
            'customer' => [
                'first_name' => $validated['customer']['first_name'],
                'last_name' => $validated['customer']['last_name'],
                'phone_country_code' => $validated['customer']['phone_country_code'] ?? null,
                'phone_number' => $validated['customer']['phone_number'] ?? null,
                'hotel_name' => $validated['customer']['hotel_name'] ?? $studio->name,
                'room_number' => $validated['customer']['room_number'] ?? null,
                'place' => $validated['customer']['hotel_name'] ?? null,
                'photo_path' => $validated['slip_image_path'] ?? null,
                'customer_notes' => $validated['customer']['customer_notes'] ?? null,
            ],
            'assigned_driver_user_id' => $validated['assigned_driver_user_id'] ?? null,
            'appointment_type' => $validated['appointment_type'] ?? 'standard',
            'pax' => $validated['pax'],
            'appointment_at' => $validated['appointment_at'],
            'notes' => $validated['notes'] ?? null,
            'source_image_path' => $validated['source_image_path'] ?? $validated['slip_image_path'] ?? null,
        ]);

        return response()->json([
            'message' => 'Randevu olusturuldu.',
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
            ],
        ], 201);
    }

    public function update(
        Request $request,
        Studio $studio,
        Appointment $appointment,
        AppointmentService $appointmentService
    ): JsonResponse {
        $validated = $request->validate([
            'customer.first_name' => ['sometimes', 'string', 'max:255'],
            'customer.last_name' => ['sometimes', 'string', 'max:255'],
            'customer.phone_country_code' => ['nullable', 'string', 'max:10'],
            'customer.phone_number' => ['nullable', 'string', 'max:30'],
            'customer.hotel_name' => ['nullable', 'string', 'max:255'],
            'customer.room_number' => ['nullable', 'string', 'max:100'],
            'customer.customer_notes' => ['nullable', 'string'],
            'pax' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'appointment_at' => ['sometimes', 'date'],
            'appointment_type' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', 'string', 'in:pending,confirmed,completed,cancelled,rescheduled'],
            'notes' => ['nullable', 'string'],
            'source_image_path' => ['nullable', 'string', 'max:2048'],
            'assigned_driver_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $appointment = $appointmentService->update($studio, $appointment, $validated);

        return response()->json([
            'message' => 'Randevu guncellendi.',
            'data' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
            ],
        ]);
    }

    public function destroy(
        Studio $studio,
        Appointment $appointment,
        AppointmentService $appointmentService
    ): JsonResponse {
        $appointmentService->delete($studio, $appointment);

        return response()->json([
            'message' => 'Randevu silindi.',
        ]);
    }
}
