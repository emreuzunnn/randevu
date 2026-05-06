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
    /** Şoförün kendine atanmış tüm randevuları (stüdyodan bağımsız) */
    public function myAppointments(Request $request): JsonResponse
    {
        $user = $request->user();

        $appointments = Appointment::query()
            ->with(['studio', 'createdBy'])
            ->where('assigned_driver_user_id', $user->id)
            ->orderBy('appointment_at')
            ->get();

        return response()->json([
            'data' => $appointments->map(fn ($appointment): array => [
                'id'     => $appointment->id,
                'studio' => $appointment->studio ? [
                    'id'   => $appointment->studio->id,
                    'name' => $appointment->studio->name,
                ] : null,
                'customer'      => $this->formatCustomer($appointment),
                'place'         => $appointment->place,
                'pax'           => $appointment->pax,
                'appointment_at' => optional($appointment->appointment_at)->toIso8601String(),
                'status'        => $appointment->status,
                'driver_status' => $appointment->driver_status,
                'notes'         => $appointment->notes,
                'created_by'    => $appointment->createdBy ? [
                    'id'   => $appointment->createdBy->id,
                    'name' => $appointment->createdBy->fullName(),
                ] : null,
                'created_at' => optional($appointment->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    /** Artistin kendine atanmış randevuları (stüdyodan bağımsız) */
    public function myArtistAppointments(Request $request): JsonResponse
    {
        $user = $request->user();

        $appointments = Appointment::query()
            ->with(['studio', 'createdBy'])
            ->where('assigned_artist_user_id', $user->id)
            ->whereNotIn('artist_status', ['rejected'])
            ->orderBy('appointment_at')
            ->get();

        return response()->json([
            'data' => $appointments->map(fn ($appointment): array => [
                'id'     => $appointment->id,
                'studio' => $appointment->studio ? [
                    'id'   => $appointment->studio->id,
                    'name' => $appointment->studio->name,
                ] : null,
                'customer'      => $this->formatCustomer($appointment),
                'place'         => $appointment->place,
                'pax'           => $appointment->pax,
                'appointment_at' => optional($appointment->appointment_at)->toIso8601String(),
                'status'        => $appointment->status,
                'artist_status' => $appointment->artist_status,
                'notes'         => $appointment->notes,
                'created_at'    => optional($appointment->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function support(Studio $studio, Request $request): JsonResponse
    {
        abort_unless($request->user()?->canManageStudioAppointments($studio), 403);

        $drivers = $studio->users()
            ->wherePivot('role', \App\Enums\UserRole::Sofor->value)
            ->wherePivot('is_active', true)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.surname', 'users.phone']);

        $artists = $studio->users()
            ->wherePivot('role', \App\Enums\UserRole::Artist->value)
            ->wherePivot('is_active', true)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.surname', 'users.phone', 'users.profile_image', 'users.rating']);

        return response()->json([
            'data' => [
                'drivers' => $drivers->map(fn ($d): array => [
                    'id'    => $d->id,
                    'name'  => $d->fullName(),
                    'phone' => $d->phone,
                ])->values(),
                'artists' => $artists->map(fn ($a): array => [
                    'id'            => $a->id,
                    'name'          => $a->fullName(),
                    'phone'         => $a->phone,
                    'profile_image' => $a->profile_image,
                    'rating'        => $a->rating,
                ])->values(),
                'statuses' => ['pending', 'confirmed', 'completed', 'cancelled', 'rescheduled'],
            ],
        ]);
    }

    public function show(Studio $studio, Appointment $appointment): JsonResponse
    {
        abort_if((int) $appointment->studio_id !== (int) $studio->id, 404);

        $appointment->load(['createdBy', 'assignedDriver', 'assignedArtist']);

        return response()->json([
            'data' => [
                'id'               => $appointment->id,
                'appointment_type' => $appointment->appointment_type,
                'full_name'        => trim($appointment->first_name.' '.$appointment->last_name),
                'customer'         => $this->formatCustomer($appointment),
                'date'             => optional($appointment->appointment_at)->format('Y-m-d'),
                'time'             => optional($appointment->appointment_at)->format('H:i'),
                'place'            => $appointment->place,
                'pax'              => $appointment->pax,
                'status'           => $appointment->status,
                'driver_status'    => $appointment->driver_status,
                'artist_status'    => $appointment->artist_status,
                'notes'            => $appointment->notes,
                'is_old_customer'  => $appointment->is_old_customer,
                'driver'           => $appointment->assignedDriver ? [
                    'id'      => $appointment->assignedDriver->id,
                    'name'    => $appointment->assignedDriver->fullName(),
                    'phone'   => $appointment->assignedDriver->phone,
                ] : null,
                'artist'           => $appointment->assignedArtist ? [
                    'id'            => $appointment->assignedArtist->id,
                    'name'          => $appointment->assignedArtist->fullName(),
                    'profile_image' => $appointment->assignedArtist->profile_image,
                    'rating'        => $appointment->assignedArtist->rating,
                ] : null,
                'created_by' => $appointment->createdBy ? [
                    'id'   => $appointment->createdBy->id,
                    'name' => $appointment->createdBy->fullName(),
                ] : null,
                'created_at' => optional($appointment->created_at)->toIso8601String(),
            ],
        ]);
    }

    public function checkCustomerStatus(
        Request $request,
        Studio $studio,
        AppointmentService $appointmentService
    ): JsonResponse {
        $validated = $request->validate([
            'customer.first_name'        => ['required', 'string', 'max:255'],
            'customer.last_name'         => ['required', 'string', 'max:255'],
            'customer.phone_country_code' => ['nullable', 'string', 'max:10'],
            'customer.phone_number'      => ['nullable', 'string', 'max:30'],
            'customer.hotel_name'        => ['nullable', 'string', 'max:255'],
        ]);

        $status = $appointmentService->checkCustomerStatus($studio, $validated['customer']);

        return response()->json([
            'data' => [
                'is_old_customer'    => $status['is_old_customer'],
                'last_appointment_id' => $status['matched_appointment']?->id,
                'customer_notes'     => $status['matched_appointment']?->customer_notes,
            ],
        ]);
    }

    public function index(Request $request, Studio $studio): JsonResponse
    {
        $user = $request->user();

        $appointments = $studio->appointments()
            ->with(['createdBy', 'assignedDriver', 'assignedArtist'])
            ->when(
                $user?->hasStudioRole($studio, [\App\Enums\UserRole::Sofor]) && ! $user->canManageStudioAppointments($studio),
                fn ($q) => $q->where('assigned_driver_user_id', $user->id)
            )
            ->when(
                $user?->hasStudioRole($studio, [\App\Enums\UserRole::Artist]) && ! $user->canManageStudioAppointments($studio),
                fn ($q) => $q->where('assigned_artist_user_id', $user->id)
            )
            ->orderBy('appointment_at')
            ->get();

        return response()->json([
            'data' => $appointments->map(fn ($appointment): array => [
                'id'         => $appointment->id,
                'customer'   => $this->formatCustomer($appointment),
                'pax'        => $appointment->pax,
                'appointment_at'  => optional($appointment->appointment_at)->toIso8601String(),
                'status'          => $appointment->status,
                'driver_status'   => $appointment->driver_status,
                'artist_status'   => $appointment->artist_status,
                'notes'           => $appointment->notes,
                'source_image_path' => $appointment->source_image_path,
                'assigned_driver_user_id' => $appointment->assigned_driver_user_id,
                'assigned_artist_user_id' => $appointment->assigned_artist_user_id,
                'driver' => $appointment->assignedDriver ? [
                    'id'     => $appointment->assignedDriver->id,
                    'name'   => $appointment->assignedDriver->fullName(),
                    'phone'  => $appointment->assignedDriver->phone,
                    'rating' => $appointment->assignedDriver->rating,
                ] : null,
                'artist' => $appointment->assignedArtist ? [
                    'id'            => $appointment->assignedArtist->id,
                    'name'          => $appointment->assignedArtist->fullName(),
                    'profile_image' => $appointment->assignedArtist->profile_image,
                    'rating'        => $appointment->assignedArtist->rating,
                ] : null,
                'studio'     => $studio->name,
                'created_at' => optional($appointment->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function driverAction(Request $request, Studio $studio, Appointment $appointment): JsonResponse
    {
        abort_if($appointment->studio_id !== $studio->id, 404);
        abort_unless($appointment->assigned_driver_user_id === $request->user()?->id, 403);

        $validated = $request->validate([
            'driver_status' => ['required', 'string', 'in:picked_up,dropped_off,cancelled'],
        ]);

        $appointment->driver_status = $validated['driver_status'];

        if ($validated['driver_status'] === 'dropped_off') {
            $appointment->status = 'completed';
        } elseif ($validated['driver_status'] === 'cancelled') {
            $appointment->status = 'cancelled';
        }

        $appointment->save();

        return response()->json([
            'message' => 'Durum güncellendi.',
            'data'    => [
                'id'            => $appointment->id,
                'status'        => $appointment->status,
                'driver_status' => $appointment->driver_status,
            ],
        ]);
    }

    /** Supervisor+ tarafından randevuya artist/freelancer atar */
    public function assignArtist(
        Request $request,
        Studio $studio,
        Appointment $appointment,
        AppointmentService $appointmentService
    ): JsonResponse {
        abort_if((int) $appointment->studio_id !== (int) $studio->id, 404);
        abort_unless($request->user()?->canAssignArtist($studio), 403);

        $validated = $request->validate([
            'assigned_artist_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $appointment = $appointmentService->assignArtist(
            $studio,
            $appointment,
            $validated['assigned_artist_user_id'] !== null ? (int) $validated['assigned_artist_user_id'] : null
        );

        return response()->json([
            'message' => 'Artist atandı.',
            'data'    => [
                'id'                      => $appointment->id,
                'assigned_artist_user_id' => $appointment->assigned_artist_user_id,
                'artist_status'           => $appointment->artist_status,
            ],
        ]);
    }

    /** Artist randevuyu kabul veya reddeder */
    public function artistResponse(
        Request $request,
        Studio $studio,
        Appointment $appointment,
        AppointmentService $appointmentService
    ): JsonResponse {
        abort_if((int) $appointment->studio_id !== (int) $studio->id, 404);
        abort_unless((int) $appointment->assigned_artist_user_id === $request->user()?->id, 403);

        $validated = $request->validate([
            'artist_status' => ['required', 'string', 'in:accepted,rejected'],
        ]);

        $appointment = $appointmentService->artistRespond($appointment, $validated['artist_status']);

        return response()->json([
            'message' => $validated['artist_status'] === 'accepted' ? 'Randevu kabul edildi.' : 'Randevu reddedildi.',
            'data'    => [
                'id'            => $appointment->id,
                'artist_status' => $appointment->artist_status,
            ],
        ]);
    }

    public function store(Request $request, Studio $studio, AppointmentService $appointmentService): JsonResponse
    {
        $validated = $request->validate([
            'slip_image_path'            => ['nullable', 'string', 'max:2048'],
            'customer.first_name'        => ['required', 'string', 'max:255'],
            'customer.last_name'         => ['required', 'string', 'max:255'],
            'customer.phone_country_code' => ['nullable', 'string', 'max:10'],
            'customer.phone_number'      => ['nullable', 'string', 'max:30'],
            'customer.hotel_name'        => ['nullable', 'string', 'max:255'],
            'customer.room_number'       => ['nullable', 'string', 'max:100'],
            'customer.customer_notes'    => ['nullable', 'string'],
            'pax'                        => ['required', 'integer', 'min:1', 'max:50'],
            'appointment_at'             => ['required', 'date'],
            'appointment_type'           => ['nullable', 'string', 'max:50'],
            'notes'                      => ['nullable', 'string'],
            'source_image_path'          => ['nullable', 'string', 'max:2048'],
            'assigned_driver_user_id'    => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $appointment = $appointmentService->create($studio, $request->user(), [
            'customer' => [
                'first_name'        => $validated['customer']['first_name'],
                'last_name'         => $validated['customer']['last_name'],
                'phone_country_code' => $validated['customer']['phone_country_code'] ?? null,
                'phone_number'      => $validated['customer']['phone_number'] ?? null,
                'hotel_name'        => $validated['customer']['hotel_name'] ?? $studio->name,
                'room_number'       => $validated['customer']['room_number'] ?? null,
                'place'             => $validated['customer']['hotel_name'] ?? null,
                'photo_path'        => $validated['slip_image_path'] ?? null,
                'customer_notes'    => $validated['customer']['customer_notes'] ?? null,
            ],
            'assigned_driver_user_id' => $validated['assigned_driver_user_id'] ?? null,
            'appointment_type'        => $validated['appointment_type'] ?? 'standard',
            'pax'                     => $validated['pax'],
            'appointment_at'          => $validated['appointment_at'],
            'notes'                   => $validated['notes'] ?? null,
            'source_image_path'       => $validated['source_image_path'] ?? $validated['slip_image_path'] ?? null,
        ]);

        return response()->json([
            'message' => 'Randevu oluşturuldu.',
            'data'    => ['id' => $appointment->id, 'status' => $appointment->status],
        ], 201);
    }

    public function update(
        Request $request,
        Studio $studio,
        Appointment $appointment,
        AppointmentService $appointmentService
    ): JsonResponse {
        $validated = $request->validate([
            'customer.first_name'        => ['sometimes', 'string', 'max:255'],
            'customer.last_name'         => ['sometimes', 'string', 'max:255'],
            'customer.phone_country_code' => ['nullable', 'string', 'max:10'],
            'customer.phone_number'      => ['nullable', 'string', 'max:30'],
            'customer.hotel_name'        => ['nullable', 'string', 'max:255'],
            'customer.room_number'       => ['nullable', 'string', 'max:100'],
            'customer.customer_notes'    => ['nullable', 'string'],
            'pax'                        => ['sometimes', 'integer', 'min:1', 'max:50'],
            'appointment_at'             => ['sometimes', 'date'],
            'appointment_type'           => ['sometimes', 'string', 'max:50'],
            'status'                     => ['sometimes', 'string', 'in:pending,confirmed,completed,cancelled,rescheduled'],
            'notes'                      => ['nullable', 'string'],
            'source_image_path'          => ['nullable', 'string', 'max:2048'],
            'assigned_driver_user_id'    => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $appointment = $appointmentService->update($studio, $appointment, $validated);

        return response()->json([
            'message' => 'Randevu güncellendi.',
            'data'    => ['id' => $appointment->id, 'status' => $appointment->status],
        ]);
    }

    public function destroy(
        Studio $studio,
        Appointment $appointment,
        AppointmentService $appointmentService
    ): JsonResponse {
        $appointment = $studio->appointments()
            ->whereKey($appointment->id)
            ->firstOrFail();

        $appointmentService->delete($studio, $appointment);

        return response()->json(['message' => 'Randevu silindi.']);
    }

    private function formatCustomer(Appointment $appointment): array
    {
        return [
            'first_name'        => $appointment->first_name,
            'last_name'         => $appointment->last_name,
            'phone_country_code' => $appointment->phone_country_code,
            'phone_number'      => $appointment->phone_number,
            'hotel_name'        => $appointment->hotel_name,
            'room_number'       => $appointment->room_number,
            'customer_notes'    => $appointment->customer_notes,
        ];
    }
}
