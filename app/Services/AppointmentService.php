<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    /**
     * @param  array<string, mixed>  $customer
     * @return array{is_old_customer:bool,matched_appointment:?Appointment,appointments:Collection<int, Appointment>}
     */
    public function checkCustomerStatus(Studio $studio, array $customer): array
    {
        $appointments = $this->findCustomerAppointments($studio, $customer);
        $matchedAppointment = $appointments->first();

        return [
            'is_old_customer' => $matchedAppointment !== null,
            'matched_appointment' => $matchedAppointment,
            'appointments' => $appointments,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Studio $studio, User $user, array $attributes): Appointment
    {
        return DB::transaction(function () use ($studio, $user, $attributes): Appointment {
            $this->ensureStudioTimeslotIsAvailable($studio, $attributes['appointment_at']);

            $status = $this->checkCustomerStatus($studio, $attributes['customer']);

            return Appointment::query()->create([
                'studio_id'               => $studio->id,
                'created_by_user_id'      => $user->id,
                'assigned_artist_user_id' => null,
                'artist_status'           => null,
                'appointment_type'        => $attributes['appointment_type'] ?? 'designer',
                ...$attributes['customer'],
                'pax'               => $attributes['pax'],
                'price'             => $attributes['price'] ?? null,
                'appointment_at'    => $attributes['appointment_at'],
                'status'            => 'confirmed',
                'is_old_customer'   => $status['is_old_customer'],
                'notes'             => $attributes['notes'] ?? null,
                'source_image_path' => $attributes['source_image_path'] ?? null,
                'tattoo_image_paths' => $attributes['tattoo_image_paths'] ?? [],
                'pickup_required'   => $attributes['pickup_required'] ?? false,
            ]);
        })->load(['createdBy']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Studio $studio, Appointment $appointment, array $attributes): Appointment
    {
        if ((int) $appointment->studio_id !== (int) $studio->id) {
            throw ValidationException::withMessages([
                'appointment' => ['Randevu bu stüdyoya ait değil.'],
            ]);
        }

        return DB::transaction(function () use ($studio, $appointment, $attributes): Appointment {
            $appointmentAt = $attributes['appointment_at'] ?? $appointment->appointment_at;

            $this->ensureStudioTimeslotIsAvailable($studio, $appointmentAt, $appointment);

            $customerData = array_key_exists('customer', $attributes)
                ? array_merge($this->extractCustomerSnapshot($appointment), $attributes['customer'])
                : $this->extractCustomerSnapshot($appointment);

            $status = $this->checkCustomerStatus($studio, $customerData);
            if ($status['matched_appointment']?->id === $appointment->id) {
                $status['is_old_customer'] = false;
            }

            $appointment->fill([
                ...$customerData,
                'appointment_type'  => $attributes['appointment_type'] ?? $appointment->appointment_type,
                'pax'               => $attributes['pax'] ?? $appointment->pax,
                'price'             => array_key_exists('price', $attributes) ? $attributes['price'] : $appointment->price,
                'appointment_at'    => $attributes['appointment_at'] ?? $appointment->appointment_at,
                'status'            => $attributes['status'] ?? $appointment->status,
                'is_old_customer'   => $status['is_old_customer'],
                'notes'             => array_key_exists('notes', $attributes) ? $attributes['notes'] : $appointment->notes,
                'source_image_path' => $attributes['source_image_path'] ?? $appointment->source_image_path,
                'tattoo_image_paths' => array_key_exists('tattoo_image_paths', $attributes)
                    ? $attributes['tattoo_image_paths']
                    : $appointment->tattoo_image_paths,
                'pickup_required'   => array_key_exists('pickup_required', $attributes)
                    ? $attributes['pickup_required']
                    : $appointment->pickup_required,
            ])->save();

            return $appointment->fresh(['createdBy', 'assignedArtist']);
        });
    }

    /** Supervisor+ tarafından randevuya artist atar. */
    public function assignArtist(Studio $studio, Appointment $appointment, ?int $artistUserId): Appointment
    {
        if ((int) $appointment->studio_id !== (int) $studio->id) {
            throw ValidationException::withMessages([
                'appointment' => ['Randevu bu stüdyoya ait değil.'],
            ]);
        }

        if ($artistUserId !== null) {
            $artist = User::query()->find($artistUserId);
            if ($artist === null) {
                throw ValidationException::withMessages([
                    'assigned_artist_user_id' => ['Kullanıcı bulunamadı.'],
                ]);
            }
            if ($artist->banned_at !== null) {
                throw ValidationException::withMessages([
                    'assigned_artist_user_id' => ['Banlı kullanıcı randevuya atanamaz.'],
                ]);
            }

            $assignableRoles = $appointment->appointment_type === 'designer'
                ? [UserRole::Designer]
                : [UserRole::Artist];

            // Kişi randevu türüne uygun stüdyo rolünde veya bağımsız profesyonel olabilir.
            $isStudioArtist = $artist->hasStudioRole($studio, $assignableRoles);
            $isIndependent  = $artist->isIndependentProfessionalFor($assignableRoles[0]);

            if (! $isStudioArtist && ! $isIndependent) {
                throw ValidationException::withMessages([
                    'assigned_artist_user_id' => ['Seçilen kullanıcı bu randevu türü için uygun değil.'],
                ]);
            }
        }

        $appointment->fill([
            'assigned_artist_user_id' => $artistUserId,
            'artist_status'           => null,
        ])->save();

        return $appointment->fresh(['assignedArtist']);
    }

    public function delete(Studio $studio, Appointment $appointment): void
    {
        if ((int) $appointment->studio_id !== (int) $studio->id) {
            throw ValidationException::withMessages([
                'appointment' => ['Randevu bu stüdyoya ait değil.'],
            ]);
        }
        $appointment->delete();
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function findExistingAppointment(Studio $studio, array $customer): ?Appointment
    {
        return $this->findCustomerAppointments($studio, $customer)->first();
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return Collection<int, Appointment>
     */
    private function findCustomerAppointments(Studio $studio, array $customer): Collection
    {
        $query = Appointment::query()
            ->where('studio_id', $studio->id)
            ->latest('appointment_at');

        $phoneCountryCode = trim((string) ($customer['phone_country_code'] ?? ''));
        $phoneNumber = trim((string) ($customer['phone_number'] ?? ''));
        $normalizedPhoneNumber = $this->normalizePhoneNumber($phoneNumber);
        $phoneTail = mb_strlen($normalizedPhoneNumber) >= 10
            ? mb_substr($normalizedPhoneNumber, -10)
            : null;
        $firstName = trim((string) ($customer['first_name'] ?? ''));
        $lastName = trim((string) ($customer['last_name'] ?? ''));

        $hasPhone = filled($normalizedPhoneNumber);
        $hasFullName = filled($firstName) && filled($lastName);
        if (! $hasPhone && ! $hasFullName) {
            return new Collection();
        }

        $customerIds = app(CustomerService::class)
            ->findMatchingCustomers($studio->id, $customer)
            ->pluck('id')
            ->all();

        return $query
            ->where(function ($customerQuery) use ($customerIds, $hasPhone, $normalizedPhoneNumber, $phoneTail, $phoneCountryCode, $hasFullName, $firstName, $lastName): void {
                if ($customerIds !== []) {
                    $customerQuery->whereIn('customer_id', $customerIds);
                }

                if ($hasPhone) {
                    $method = $customerIds !== [] ? 'orWhere' : 'where';
                    $customerQuery->{$method}(function ($phoneQuery) use ($normalizedPhoneNumber, $phoneTail, $phoneCountryCode): void {
                        $phoneExpression = $this->phoneComparableExpression('phone_number');
                        $phoneQuery
                            ->where(function ($normalizedPhoneQuery) use ($phoneExpression, $normalizedPhoneNumber, $phoneTail): void {
                                $normalizedPhoneQuery->whereRaw("{$phoneExpression} = ?", [$normalizedPhoneNumber]);

                                if ($phoneTail !== null) {
                                    $normalizedPhoneQuery->orWhereRaw("substr({$phoneExpression}, -10) = ?", [$phoneTail]);
                                }
                            })
                            ->when(
                                filled($phoneCountryCode),
                                fn ($phoneWithCountryQuery) => $phoneWithCountryQuery
                                    ->where(function ($countryQuery) use ($phoneCountryCode): void {
                                        $countryQuery
                                            ->where('phone_country_code', $phoneCountryCode)
                                            ->orWhereNull('phone_country_code');
                                    })
                            );
                    });
                }

                if ($hasFullName) {
                    $method = ($hasPhone || $customerIds !== []) ? 'orWhere' : 'where';
                    $customerQuery->{$method}(function ($nameQuery) use ($firstName, $lastName): void {
                        $nameQuery
                            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
                            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)]);
                    });
                }
            })
            ->limit(20)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCustomerSnapshot(Appointment $appointment): array
    {
        return [
            'first_name'        => $appointment->first_name,
            'last_name'         => $appointment->last_name,
            'phone_country_code' => $appointment->phone_country_code,
            'phone_number'      => $appointment->phone_number,
            'hotel_name'        => $appointment->hotel_name,
            'room_number'       => $appointment->room_number,
            'place'             => $appointment->place,
            'photo_path'        => $appointment->photo_path,
            'customer_notes'    => $appointment->customer_notes,
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
                'appointment_at' => ['Bu stüdyoda aynı tarih ve saatte başka bir randevu zaten bulunuyor.'],
            ]);
        }
    }

    private function normalizePhoneNumber(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function phoneComparableExpression(string $column): string
    {
        return "replace(replace(replace(replace(replace(replace({$column}, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '')";
    }
}
