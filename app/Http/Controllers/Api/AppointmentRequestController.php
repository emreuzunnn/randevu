<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PushNotification;
use App\Models\Studio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentRequestController extends Controller
{
    /**
     * Kullanıcı (müşteri) belirli bir artist'e randevu talebi gönderir.
     * Stüdyo: studio_id verilmişse o, yoksa artistin aktif ilk stüdyosu.
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();
        abort_if($authUser === null, 401);

        $validated = $request->validate([
            'studio_id'      => ['nullable', 'integer', 'exists:studios,id'],
            'artist_id'      => ['required', 'integer', 'exists:users,id'],
            'preferred_date' => ['required', 'date_format:Y-m-d', 'after:today'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'type'           => ['nullable', 'string', 'in:standard,designer,tattoo'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'phone_country_code' => ['nullable', 'string', 'max:10'],
            'phone_number'   => ['nullable', 'string', 'max:30'],
        ]);

        $artist = User::query()->findOrFail($validated['artist_id']);
        abort_unless(
            $artist->hasAnyRole([UserRole::Artist, UserRole::KullaniciRol]),
            422,
            'Seçilen kullanıcı bir artist değil.'
        );

        // Stüdyo: parametreden gelen ya da artistin aktif ilk stüdyosu
        if (! empty($validated['studio_id'])) {
            $studio = Studio::query()->findOrFail($validated['studio_id']);
        } else {
            $studio = $artist->studios()
                ->wherePivot('is_active', true)
                ->first();

            if ($studio === null) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Bu artist aktif bir stüdyoya bağlı değil.',
                ], 422);
            }
        }

        // Tarih + saat birleştir
        $time          = $validated['preferred_time'] ?? '09:00';
        $appointmentAt = Carbon::createFromFormat('Y-m-d H:i', $validated['preferred_date'] . ' ' . $time);

        $appointment = Appointment::query()->create([
            'studio_id'               => $studio->id,
            'created_by_user_id'      => $authUser->id,
            'assigned_artist_user_id' => $artist->id,
            'appointment_type'        => $validated['type'] ?? 'standard',
            'first_name'              => $authUser->name ?: '-',
            'last_name'               => $authUser->surname ?? '-',
            'phone_country_code'      => $validated['phone_country_code'] ?? null,
            'phone_number'            => $validated['phone_number'] ?? null,
            'appointment_at'          => $appointmentAt,
            'status'                  => 'pending',
            'artist_status'           => 'pending',
            'customer_notes'          => $validated['notes'] ?? null,
            'is_old_customer'         => false,
            'pax'                     => 1,
        ]);

        $this->notifyArtist($artist, $authUser, $appointment, $studio);
        $this->notifySupervisors($studio, $authUser, $appointment, $artist);

        return response()->json([
            'message' => 'Randevu talebiniz alındı.',
            'data'    => [
                'appointment_id'   => $appointment->id,
                'studio_id'        => $studio->id,
                'studio_name'      => $studio->name,
                'artist_id'        => $artist->id,
                'artist_name'      => $artist->fullName(),
                'preferred_date'   => $appointment->appointment_at?->toDateString(),
                'preferred_time'   => $appointment->appointment_at?->format('H:i'),
                'type'             => $appointment->appointment_type,
                'status'           => $appointment->status,
                'artist_status'    => $appointment->artist_status,
                'notes'            => $appointment->customer_notes,
            ],
        ], 201);
    }

    private function notifyArtist(User $artist, User $requester, Appointment $appointment, Studio $studio): void
    {
        PushNotification::query()->create([
            'user_id' => $artist->id,
            'type'    => 'appointment_request',
            'title'   => 'Yeni Randevu Talebi',
            'body'    => "{$requester->fullName()} sizinle {$appointment->appointment_at?->format('d.m.Y H:i')} tarihinde randevu almak istiyor.",
            'data'    => [
                'appointment_id' => $appointment->id,
                'studio_id'      => $studio->id,
                'requester_id'   => $requester->id,
            ],
        ]);
    }

    private function notifySupervisors(Studio $studio, User $requester, Appointment $appointment, User $artist): void
    {
        $supervisors = $studio->users()
            ->wherePivotIn('role', [UserRole::Supervisor->value, UserRole::Yonetici->value])
            ->wherePivot('is_active', true)
            ->get(['users.id']);

        foreach ($supervisors as $supervisor) {
            PushNotification::query()->create([
                'user_id' => $supervisor->id,
                'type'    => 'appointment_request',
                'title'   => 'Yeni Randevu Talebi',
                'body'    => "{$requester->fullName()}, {$artist->fullName()} için {$appointment->appointment_at?->format('d.m.Y H:i')} tarihinde randevu talep etti.",
                'data'    => [
                    'appointment_id' => $appointment->id,
                    'studio_id'      => $studio->id,
                    'artist_id'      => $artist->id,
                    'requester_id'   => $requester->id,
                ],
            ]);
        }
    }
}
