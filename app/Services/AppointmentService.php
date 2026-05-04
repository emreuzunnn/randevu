<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    /**
     * @param  array<string, mixed>  $customer
     * @return array{is_old_customer:bool,matched_appointment:?Appointment}
     */
    public function checkCustomerStatus(Studio $studio, array $customer): array
    {
        $matchedAppointment = $this->findExistingAppointment($studio, $customer);

        return [
            'is_old_customer' => $matchedAppointment !== null,
            'matched_appointment' => $matchedAppointment,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Studio $studio, User $user, array $attributes): Appointment
    {
        return DB::transaction(function () use ($studio, $user, $attributes): Appointment {
            $this->ensureStudioTimeslotIsAvailable($studio, $attributes['appointment_at']);

            if (isset($attributes['assigned_driver_user_id']) && $attributes['assigned_driver_user_id'] !== null) {
                $driver = User::query()->find($attributes['assigned_driver_user_id']);

                if ($driver === null || ! $driver->hasStudioRole($studio, [UserRole::Sofor])) {
                    throw ValidationException::withMessages([
                        'assigned_driver_user_id' => ['Secilen kullanici bu studyoda sofor degil.'],
                    ]);
                }
            }

            $status = $this->checkCustomerStatus($studio, $attributes['customer']);

            return Appointment::query()->create([
                'studio_id' => $studio->id,
                'created_by_user_id' => $user->id,
                'assigned_driver_user_id' => $attributes['assigned_driver_user_id'] ?? null,
                'appointment_type' => $attributes['appointment_type'] ?? 'standard',
                ...$attributes['customer'],
                'pax' => $attributes['pax'],
                'appointment_at' => $attributes['appointment_at'],
                'status' => 'pending',
                'is_old_customer' => $status['is_old_customer'],
                'notes' => $attributes['notes'] ?? null,
                'source_image_path' => $attributes['source_image_path'] ?? null,
            ]);
        })->load(['createdBy', 'assignedDriver']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Studio $studio, Appointment $appointment, array $attributes): Appointment
    {
        if ($appointment->studio_id !== $studio->id) {
            throw ValidationException::withMessages([
                'appointment' => ['Randevu bu studyoya ait degil.'],
            ]);
        }

        return DB::transaction(function () use ($studio, $appointment, $attributes): Appointment {
            $appointmentAt = $attributes['appointment_at'] ?? $appointment->appointment_at;

            $this->ensureStudioTimeslotIsAvailable($studio, $appointmentAt, $appointment);

            if (isset($attributes['assigned_driver_user_id']) && $attributes['assigned_driver_user_id'] !== null) {
                $driver = User::query()->find($attributes['assigned_driver_user_id']);

                if ($driver === null || ! $driver->hasStudioRole($studio, [UserRole::Sofor])) {
                    throw ValidationException::withMessages([
                        'assigned_driver_user_id' => ['Secilen kullanici bu studyoda sofor degil.'],
                    ]);
                }
            }

            $customerData = array_key_exists('customer', $attributes)
                ? array_merge($this->extractCustomerSnapshot($appointment), $attributes['customer'])
                : $this->extractCustomerSnapshot($appointment);

            $status = $this->checkCustomerStatus($studio, $customerData);

            if ($status['matched_appointment']?->id === $appointment->id) {
                $status['is_old_customer'] = false;
            }

            $appointment->fill([
                ...$customerData,
                'assigned_driver_user_id' => $attributes['assigned_driver_user_id'] ?? $appointment->assigned_driver_user_id,
                'appointment_type' => $attributes['appointment_type'] ?? $appointment->appointment_type,
                'pax' => $attributes['pax'] ?? $appointment->pax,
                'appointment_at' => $attributes['appointment_at'] ?? $appointment->appointment_at,
                'status' => $attributes['status'] ?? $appointment->status,
                'is_old_customer' => $status['is_old_customer'],
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $appointment->notes,
                'source_image_path' => $attributes['source_image_path'] ?? $appointment->source_image_path,
            ])->save();

            return $appointment->fresh(['createdBy', 'assignedDriver']);
        });
    }

    public function delete(Studio $studio, Appointment $appointment): void
    {
        if ($appointment->studio_id !== $studio->id) {
            throw ValidationException::withMessages([
                'appointment' => ['Randevu bu studyoya ait degil.'],
            ]);
        }

        $appointment->delete();
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function findExistingAppointment(Studio $studio, array $customer): ?Appointment
    {
        $query = Appointment::query()
            ->where('studio_id', $studio->id);

        $phoneCountryCode = $customer['phone_country_code'] ?? null;
        $phoneNumber = $customer['phone_number'] ?? null;

        if (filled($phoneNumber)) {
            return $query
                ->where('phone_number', $phoneNumber)
                ->when(
                    filled($phoneCountryCode),
                    fn ($innerQuery) => $innerQuery->where(function ($countryQuery) use ($phoneCountryCode) {
                        $countryQuery
                            ->where('phone_country_code', $phoneCountryCode)
                            ->orWhereNull('phone_country_code');
                    })
                )
                ->latest('appointment_at')
                ->first();
        }

        return $query
            ->where('first_name', $customer['first_name'])
            ->where('last_name', $customer['last_name'])
            ->where('hotel_name', $customer['hotel_name'] ?? null)
            ->latest('appointment_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCustomerSnapshot(Appointment $appointment): array
    {
        return [
            'first_name' => $appointment->first_name,
            'last_name' => $appointment->last_name,
            'phone_country_code' => $appointment->phone_country_code,
            'phone_number' => $appointment->phone_number,
            'hotel_name' => $appointment->hotel_name,
            'room_number' => $appointment->room_number,
            'place' => $appointment->place,
            'photo_path' => $appointment->photo_path,
            'customer_notes' => $appointment->customer_notes,
        ];
    }

    private function ensureStudioTimeslotIsAvailable(Studio $studio, mixed $appointmentAt, ?Appointment $ignoreAppointment = null): void
    {
        $query = Appointment::query()
            ->where('studio_id', $studio->id)
            ->where('appointment_at', $appointmentAt);

        if ($ignoreAppointment !== null) {
            $query->whereKeyNot($ignoreAppointment->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'appointment_at' => ['Bu studyoda ayni tarih ve saatte baska bir randevu zaten bulunuyor.'],
            ]);
        }
    }
}
