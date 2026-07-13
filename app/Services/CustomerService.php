<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Studio;
use Illuminate\Database\Eloquent\Collection;

class CustomerService
{
    public function syncForAppointment(Appointment $appointment): ?Customer
    {
        if ($appointment->studio_id === null) {
            return null;
        }

        $snapshot = $this->snapshotFromAppointment($appointment);
        if (! $this->hasSearchableIdentity($snapshot)) {
            return null;
        }

        $customer = $this->findMatchingCustomers((int) $appointment->studio_id, $snapshot)->first();

        if ($customer === null) {
            $customer = new Customer([
                'studio_id' => $appointment->studio_id,
                'first_appointment_at' => $appointment->appointment_at,
            ]);
        }

        $customer->fill([
            ...$snapshot,
            'last_appointment_at' => $appointment->appointment_at,
        ]);
        $customer->save();

        $this->attachMatchingAppointments($customer);

        if ((int) $appointment->customer_id !== (int) $customer->id) {
            $appointment->forceFill(['customer_id' => $customer->id])->saveQuietly();
        }

        return $customer->refresh();
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return Collection<int, Customer>
     */
    public function findMatchingCustomers(int $studioId, array $customer): Collection
    {
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

        return Customer::query()
            ->where('studio_id', $studioId)
            ->where(function ($query) use ($hasPhone, $normalizedPhoneNumber, $phoneTail, $phoneCountryCode, $hasFullName, $firstName, $lastName): void {
                if ($hasPhone) {
                    $query->where(function ($phoneQuery) use ($normalizedPhoneNumber, $phoneTail, $phoneCountryCode): void {
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
                    $method = $hasPhone ? 'orWhere' : 'where';
                    $query->{$method}(function ($nameQuery) use ($firstName, $lastName): void {
                        $nameQuery
                            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
                            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)]);
                    });
                }
            })
            ->latest('last_appointment_at')
            ->limit(5)
            ->get();
    }

    private function attachMatchingAppointments(Customer $customer): void
    {
        $appointments = Appointment::query()
            ->where('studio_id', $customer->studio_id)
            ->where(function ($query) use ($customer): void {
                $normalizedPhoneNumber = $this->normalizePhoneNumber((string) $customer->phone_number);
                $phoneTail = mb_strlen($normalizedPhoneNumber) >= 10
                    ? mb_substr($normalizedPhoneNumber, -10)
                    : null;

                if (filled($normalizedPhoneNumber)) {
                    $query->where(function ($phoneQuery) use ($customer, $normalizedPhoneNumber, $phoneTail): void {
                        $phoneExpression = $this->phoneComparableExpression('phone_number');
                        $phoneQuery
                            ->where(function ($normalizedPhoneQuery) use ($phoneExpression, $normalizedPhoneNumber, $phoneTail): void {
                                $normalizedPhoneQuery->whereRaw("{$phoneExpression} = ?", [$normalizedPhoneNumber]);

                                if ($phoneTail !== null) {
                                    $normalizedPhoneQuery->orWhereRaw("substr({$phoneExpression}, -10) = ?", [$phoneTail]);
                                }
                            })
                            ->when(
                                filled($customer->phone_country_code),
                                fn ($phoneWithCountryQuery) => $phoneWithCountryQuery
                                    ->where(function ($countryQuery) use ($customer): void {
                                        $countryQuery
                                            ->where('phone_country_code', $customer->phone_country_code)
                                            ->orWhereNull('phone_country_code');
                                    })
                            );
                    });
                }

                if (filled($customer->first_name) && filled($customer->last_name)) {
                    $method = filled($customer->phone_number) ? 'orWhere' : 'where';
                    $query->{$method}(function ($nameQuery) use ($customer): void {
                        $nameQuery
                            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($customer->first_name)])
                            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($customer->last_name)]);
                    });
                }
            })
            ->orderBy('appointment_at')
            ->get();

        foreach ($appointments as $appointment) {
            if ((int) $appointment->customer_id !== (int) $customer->id) {
                $appointment->forceFill(['customer_id' => $customer->id])->saveQuietly();
            }
        }

        $customer->forceFill([
            'first_appointment_at' => $appointments->first()?->appointment_at ?? $customer->first_appointment_at,
            'last_appointment_at' => $appointments->last()?->appointment_at ?? $customer->last_appointment_at,
            'appointments_count' => $appointments->count(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFromAppointment(Appointment $appointment): array
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

    /**
     * @param  array<string, mixed>  $customer
     */
    private function hasSearchableIdentity(array $customer): bool
    {
        return filled($customer['phone_number'] ?? null)
            || (filled($customer['first_name'] ?? null) && filled($customer['last_name'] ?? null));
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
