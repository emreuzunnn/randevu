<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\PushNotification;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Support\Collection;

class AppointmentNotificationService
{
    public function __construct(
        private readonly FcmService $fcmService,
    ) {}

    public function notifyBranchAppointmentCreated(Appointment $appointment, ?User $actor = null): int
    {
        $appointment->loadMissing(['studio.shop']);
        $studio = $appointment->studio;

        if (! $studio instanceof Studio) {
            return 0;
        }

        $recipients = $this->branchStaff($studio)
            ->when($actor instanceof User, fn (Collection $users) => $users
                ->reject(fn (User $user): bool => (int) $user->id === (int) $actor->id))
            ->values();

        foreach ($recipients as $recipient) {
            $this->fcmService->sendToUser(
                $recipient,
                'Yeni Randevu',
                "{$studio->name} için {$appointment->appointment_at?->format('d.m.Y H:i')} tarihli randevu oluşturuldu.",
                'appointment_created',
                $this->appointmentPayload($appointment),
            );
        }

        return $recipients->count();
    }

    public function sendDueReminders(int $minutes = 45): int
    {
        $now = now();
        $appointments = Appointment::query()
            ->with(['assignedArtist', 'studio.shop'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereBetween('appointment_at', [$now, $now->copy()->addMinutes($minutes)])
            ->get();

        $sent = 0;

        foreach ($appointments as $appointment) {
            foreach ($this->reminderRecipients($appointment) as $recipient) {
                if ($this->reminderAlreadySent($appointment, $recipient, $minutes)) {
                    continue;
                }

                $this->fcmService->sendToUser(
                    $recipient,
                    'Randevu Hatırlatması',
                    $this->reminderBody($appointment, $minutes),
                    'appointment_reminder',
                    [
                        ...$this->appointmentPayload($appointment),
                        'reminder_minutes' => (string) $minutes,
                    ],
                );

                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    private function reminderRecipients(Appointment $appointment): Collection
    {
        $recipients = collect();

        if ($appointment->assignedArtist instanceof User) {
            $recipients->push($appointment->assignedArtist);
        } elseif ($appointment->studio instanceof Studio) {
            $recipients = $recipients->merge(
                $this->branchStaff($appointment->studio, [$this->professionalRoleFor($appointment)])
            );
        }

        if ($appointment->pickup_required && $appointment->studio instanceof Studio) {
            $recipients = $recipients->merge(
                $this->branchStaff($appointment->studio, [UserRole::Sofor])
            );
        }

        return $recipients
            ->unique(fn (User $user): int => (int) $user->id)
            ->values();
    }

    /**
     * @param  array<int, UserRole>  $roles
     * @return Collection<int, User>
     */
    private function branchStaff(Studio $studio, ?array $roles = null): Collection
    {
        $studioIds = $this->branchStudioIds($studio);
        $roleValues = array_map(
            static fn (UserRole $role): string => $role->value,
            $roles ?? [
                UserRole::Yonetici,
                UserRole::Supervisor,
                UserRole::Designer,
                UserRole::Artist,
                UserRole::Info,
                UserRole::Sofor,
                UserRole::Calisan,
            ],
        );

        return User::query()
            ->whereHas('studios', fn ($query) => $query
                ->whereIn('studios.id', $studioIds)
                ->where('studio_user.is_active', true)
                ->whereIn('studio_user.role', $roleValues))
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function branchStudioIds(Studio $studio): array
    {
        if ($studio->shop_id === null) {
            return [(int) $studio->id];
        }

        return Studio::query()
            ->where('shop_id', $studio->shop_id)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function reminderAlreadySent(Appointment $appointment, User $recipient, int $minutes): bool
    {
        return PushNotification::query()
            ->where('user_id', $recipient->id)
            ->where('type', 'appointment_reminder')
            ->latest()
            ->limit(50)
            ->get()
            ->contains(function (PushNotification $notification) use ($appointment, $minutes): bool {
                return (string) data_get($notification->data, 'appointment_id') === (string) $appointment->id
                    && (string) data_get($notification->data, 'reminder_minutes') === (string) $minutes;
            });
    }

    private function professionalRoleFor(Appointment $appointment): UserRole
    {
        return $appointment->appointment_type === 'designer'
            ? UserRole::Designer
            : UserRole::Artist;
    }

    /**
     * @return array<string, string>
     */
    private function appointmentPayload(Appointment $appointment): array
    {
        return [
            'appointment_id' => (string) $appointment->id,
            'studio_id' => (string) $appointment->studio_id,
            'shop_id' => (string) ($appointment->studio?->shop_id ?? ''),
            'appointment_type' => (string) $appointment->appointment_type,
        ];
    }

    private function reminderBody(Appointment $appointment, int $minutes): string
    {
        $studioName = $appointment->studio?->name ?? 'Randevu';
        $typeLabel = $appointment->appointment_type === 'tattoo' ? 'dövme' : 'tasarım';

        return "{$studioName} için {$appointment->appointment_at?->format('H:i')} saatindeki {$typeLabel} randevusuna {$minutes} dakika kaldı.";
    }
}
