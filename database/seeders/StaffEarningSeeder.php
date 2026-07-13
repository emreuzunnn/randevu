<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\PushNotification;
use App\Models\StaffEarning;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffEarningSeeder extends Seeder
{
    public function run(): void
    {
        $earningIndex = 0;

        Appointment::query()
            ->with('studio')
            ->where('appointment_type', 'tattoo')
            ->where('status', 'completed')
            ->whereNotNull('studio_id')
            ->whereNotNull('created_by_user_id')
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->orderBy('id')
            ->each(function (Appointment $appointment) use (&$earningIndex): void {
                $membership = $appointment->studio
                    ?->users()
                    ->where('users.id', $appointment->created_by_user_id)
                    ->wherePivot('is_active', true)
                    ->first();

                if ($membership === null) {
                    return;
                }

                $role = UserRole::fromValue($membership->pivot->role);
                $rate = (float) ($membership->pivot->commission_rate ?? 0);

                if (! in_array($role, UserRole::studioRoles(), true) || $rate <= 0) {
                    return;
                }

                $userId = (int) $appointment->created_by_user_id;
                $isPaid = $earningIndex % 2 === 1;
                $earningIndex++;
                $grossAmount = (float) $appointment->price;
                $earningAmount = round($grossAmount * $rate / 100, 2);
                $paidBy = $isPaid ? $this->supervisorFor($appointment) : null;

                $earning = StaffEarning::query()->updateOrCreate(
                    ['appointment_id' => $appointment->id],
                    [
                        'studio_id' => $appointment->studio_id,
                        'user_id' => $userId,
                        'role' => $role->value,
                        'commission_rate' => $rate,
                        'gross_amount' => $grossAmount,
                        'earning_amount' => $earningAmount,
                        'status' => $isPaid ? 'paid' : 'pending',
                        'paid_at' => $isPaid ? now()->subDays(2) : null,
                        'paid_by_user_id' => $paidBy?->id,
                    ],
                );

                if ($isPaid) {
                    PushNotification::query()->firstOrCreate(
                        [
                            'user_id' => $userId,
                            'type' => 'earning_paid',
                            'title' => 'Hakedişiniz ödendi',
                            'body' => number_format($earningAmount, 2, ',', '.')
                                .' TL tutarındaki test hakedişiniz ödendi.',
                        ],
                        [
                            'data' => [
                                'earning_id' => (string) $earning->id,
                                'appointment_id' => (string) $appointment->id,
                                'studio_id' => (string) $appointment->studio_id,
                            ],
                        ],
                    );
                }
            });
    }

    private function supervisorFor(Appointment $appointment): ?User
    {
        return $appointment->studio
            ?->users()
            ->wherePivot('role', UserRole::Supervisor->value)
            ->wherePivot('is_active', true)
            ->first();
    }
}
