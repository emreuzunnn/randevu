<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\StaffEarning;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffEarningService
{
    public function __construct(
        private readonly FcmService $fcmService,
    ) {}

    public function syncForAppointment(Appointment $appointment): ?StaffEarning
    {
        $existing = StaffEarning::query()
            ->where('appointment_id', $appointment->id)
            ->first();

        if ($existing?->status === 'paid') {
            return $existing;
        }

        if (
            $appointment->appointment_type !== 'tattoo'
            || $appointment->status !== 'completed'
            || $appointment->studio_id === null
            || $appointment->created_by_user_id === null
            || $appointment->price === null
            || (float) $appointment->price <= 0
        ) {
            $existing?->delete();

            return null;
        }

        $membership = $appointment->studio
            ?->users()
            ->where('users.id', $appointment->created_by_user_id)
            ->wherePivot('is_active', true)
            ->first();

        if ($membership === null) {
            $existing?->delete();

            return null;
        }

        $role = UserRole::fromValue($membership->pivot->role);
        $rate = (float) ($membership->pivot->commission_rate ?? 0);

        if (! in_array($role, UserRole::studioRoles(), true) || $rate <= 0) {
            $existing?->delete();

            return null;
        }

        $grossAmount = (float) $appointment->price;
        $earningAmount = round($grossAmount * $rate / 100, 2);

        return StaffEarning::query()->updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'studio_id' => $appointment->studio_id,
                'user_id' => $appointment->created_by_user_id,
                'role' => $role->value,
                'commission_rate' => $rate,
                'gross_amount' => $grossAmount,
                'earning_amount' => $earningAmount,
                'status' => 'pending',
                'paid_at' => null,
                'paid_by_user_id' => null,
            ],
        );
    }

    public function markAsPaid(StaffEarning $earning, User $paidBy): StaffEarning
    {
        return DB::transaction(function () use ($earning, $paidBy): StaffEarning {
            $earning = StaffEarning::query()
                ->with(['user', 'studio', 'appointment'])
                ->lockForUpdate()
                ->findOrFail($earning->id);

            if ($earning->status === 'paid') {
                throw ValidationException::withMessages([
                    'earning' => ['Bu hakediş daha önce ödendi olarak işaretlenmiş.'],
                ]);
            }

            $earning->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by_user_id' => $paidBy->id,
            ])->save();

            $this->fcmService->sendToUser(
                $earning->user,
                'Hakedişiniz ödendi',
                number_format((float) $earning->earning_amount, 2, ',', '.')
                    .' TL tutarındaki hakedişiniz ödendi olarak işaretlendi.',
                'earning_paid',
                [
                    'earning_id' => (string) $earning->id,
                    'appointment_id' => (string) $earning->appointment_id,
                    'studio_id' => (string) $earning->studio_id,
                ],
            );

            return $earning->fresh(['user', 'studio', 'appointment', 'paidBy']);
        });
    }
}
