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
        private readonly WhatsAppNotificationService $whatsAppNotificationService,
    ) {}

    public function notifyStudioAppointmentCreated(Appointment $appointment, ?User $actor = null): int
    {
        $appointment->loadMissing(['studio.company.manager', 'createdBy', 'assignedArtist']);

        if (! $appointment->studio instanceof Studio) {
            return 0;
        }

        if ($appointment->appointment_type === 'designer') {
            $this->whatsAppNotificationService->sendAppointmentCreated($appointment);

            return $this->send(
                $this->designRecipients($appointment),
                'Yeni Tasarım Rezervasyonu',
                "{$appointment->studio->name} için {$appointment->appointment_at?->format('d.m.Y H:i')} tarihli tasarım rezervasyonu oluşturuldu.",
                'design_reservation_created',
                $this->appointmentPayload($appointment),
            );
        }

        return $this->send(
            $this->saleRecipients($appointment),
            'Yeni Satış',
            "{$appointment->studio->name} için dövme/piercing satışı gerçekleşti. Randevu: {$appointment->appointment_at?->format('d.m.Y H:i')}.",
            'sale_created',
            $this->appointmentPayload($appointment),
        );
    }

    public function notifyAppointmentUpdated(
        Appointment $appointment,
        string $event = 'updated',
        ?User $actor = null
    ): int {
        $appointment->loadMissing(['studio.company.manager', 'createdBy', 'assignedArtist']);
        $studioName = $appointment->studio?->name ?? 'Randevu';
        [$title, $body, $type] = match ($event) {
            'cancelled' => [
                'Randevu İptal Edildi',
                "{$studioName} için {$appointment->appointment_at?->format('d.m.Y H:i')} tarihli randevu iptal edildi.",
                'appointment_cancelled',
            ],
            'started' => [
                'Dövme Randevusu Başladı',
                "{$studioName} için dövme/piercing randevusu başladı.",
                'appointment_started',
            ],
            'completed' => [
                'Randevu Tamamlandı',
                "{$studioName} için dövme/piercing randevusu tamamlandı.",
                'appointment_completed',
            ],
            default => [
                'Randevu Güncellendi',
                "{$studioName} için {$appointment->appointment_at?->format('d.m.Y H:i')} tarihli randevu bilgileri güncellendi.",
                'appointment_updated',
            ],
        };

        return $this->send(
            $this->allRelevantRecipients($appointment),
            $title,
            $body,
            $type,
            [
                ...$this->appointmentPayload($appointment),
                'status' => (string) $appointment->status,
                'actor_user_id' => (string) ($actor?->id ?? ''),
            ],
        );
    }

    public function notifyDriverAction(Appointment $appointment, string $statusLabel, ?User $actor = null): int
    {
        $appointment->loadMissing(['studio.company.manager', 'createdBy', 'assignedArtist']);

        return $this->send(
            $this->allRelevantRecipients($appointment),
            'Şoför Hareketi',
            $statusLabel,
            'driver_action',
            [
                ...$this->appointmentPayload($appointment),
                'driver_status' => (string) $appointment->driver_status,
                'status' => (string) $appointment->status,
                'actor_user_id' => (string) ($actor?->id ?? ''),
            ],
        );
    }

    public function notifyArtistResponse(Appointment $appointment, User $artist): int
    {
        $appointment->loadMissing(['studio.company.manager', 'createdBy', 'assignedArtist']);
        $accepted = $appointment->artist_status === 'accepted';
        $isTicket = $appointment->appointment_type === 'tattoo';
        $assigneeLabel = $isTicket ? 'Artist' : 'Designer';
        $recordLabel = $isTicket ? 'bilet' : 'tasarım randevusu';
        $recipients = $this->allRelevantRecipients($appointment)
            ->reject(fn (User $user): bool => (int) $user->id === (int) $artist->id)
            ->values();

        return $this->send(
            $recipients,
            $accepted ? "{$assigneeLabel} Atamayı Kabul Etti" : "{$assigneeLabel} Atamayı Reddetti",
            "{$artist->fullName()}, {$appointment->appointment_at?->format('d.m.Y H:i')} tarihli {$recordLabel} kaydını "
                .($accepted ? 'kabul etti.' : 'reddetti.'),
            'artist_response',
            [
                ...$this->appointmentPayload($appointment),
                'artist_id' => (string) $artist->id,
                'artist_status' => (string) $appointment->artist_status,
            ],
        );
    }

    public function sendDueReminders(int $minutes = 15): int
    {
        $now = now();
        $appointments = Appointment::query()
            ->with(['assignedArtist', 'createdBy', 'studio.company.manager'])
            ->whereIn('appointment_type', ['designer', 'tattoo'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereBetween('appointment_at', [$now, $now->copy()->addMinutes($minutes)])
            ->get();

        $sent = 0;

        foreach ($appointments as $appointment) {
            if ($appointment->appointment_type === 'designer') {
                $this->whatsAppNotificationService->sendAppointmentReminder($appointment, $minutes);
            }

            $recipients = $appointment->appointment_type === 'tattoo'
                ? $this->ticketReminderRecipients($appointment)
                : $this->designRecipients($appointment);

            foreach ($recipients as $recipient) {
                if ($this->reminderAlreadySent($appointment, $recipient, $minutes)) {
                    continue;
                }

                $isTicket = $appointment->appointment_type === 'tattoo';
                $this->fcmService->sendToUser(
                    $recipient,
                    $isTicket ? 'Dövme Bileti Hatırlatması' : 'Tasarım Rezervasyonu Hatırlatması',
                    "{$appointment->appointment_at?->format('H:i')} saatindeki "
                        .($isTicket ? 'dövme/piercing biletine' : 'tasarım rezervasyonuna')
                        ." {$minutes} dakika kaldı.",
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
    private function ticketReminderRecipients(Appointment $appointment): Collection
    {
        $collections = [
            collect([$appointment->assignedArtist])
                ->filter(fn ($user): bool => $user instanceof User && $user->hasRole(UserRole::Artist)),
        ];

        if ($appointment->studio instanceof Studio) {
            $collections[] = $this->studioStaff($appointment->studio, [
                UserRole::Supervisor,
            ]);
        }

        return $this->mergeRecipients(...$collections);
    }

    /**
     * @return Collection<int, User>
     */
    private function designRecipients(Appointment $appointment): Collection
    {
        if (! $appointment->studio instanceof Studio) {
            return collect();
        }

        return $this->mergeRecipients(
            $this->studioStaff($appointment->studio, [
                UserRole::Admin,
                UserRole::Yonetici,
                UserRole::Supervisor,
                UserRole::Designer,
                UserRole::Info,
                UserRole::Sofor,
                UserRole::Calisan,
            ]),
            $this->managementUsers($appointment->studio),
            collect([$appointment->assignedArtist])
                ->filter(fn ($user): bool => $user instanceof User && ! $user->hasRole(UserRole::Artist)),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function saleRecipients(Appointment $appointment): Collection
    {
        if (! $appointment->studio instanceof Studio) {
            return collect([$appointment->createdBy, $appointment->assignedArtist])
                ->filter()
                ->unique('id')
                ->values();
        }

        return $this->mergeRecipients(
            $this->studioStaff($appointment->studio, [
                UserRole::Admin,
                UserRole::Yonetici,
                UserRole::Supervisor,
                UserRole::Info,
                UserRole::Designer,
            ]),
            $this->managementUsers($appointment->studio),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function allRelevantRecipients(Appointment $appointment): Collection
    {
        $collections = [
            collect([$appointment->createdBy, $appointment->assignedArtist])->filter(),
        ];

        if ($appointment->studio instanceof Studio) {
            $collections[] = $this->studioStaff($appointment->studio);
            $collections[] = $this->managementUsers($appointment->studio);
        }

        return $this->mergeRecipients(...$collections);
    }

    /**
     * @param  array<int, UserRole>|null  $roles
     * @return Collection<int, User>
     */
    private function studioStaff(Studio $studio, ?array $roles = null): Collection
    {
        $roleValues = array_map(
            static fn (UserRole $role): string => $role->value,
            $roles ?? UserRole::studioRoles(),
        );

        return User::query()
            ->whereHas('studios', fn ($query) => $query
                ->where('studios.id', $studio->id)
                ->where('studio_user.is_active', true))
            ->where(function ($query) use ($studio, $roleValues): void {
                $query
                    ->whereIn('role', $roleValues)
                    ->orWhereHas('studios', fn ($studioQuery) => $studioQuery
                        ->where('studios.id', $studio->id)
                        ->where('studio_user.is_active', true)
                        ->whereIn('studio_user.role', $roleValues));
            })
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function managementUsers(Studio $studio): Collection
    {
        $admins = User::query()
            ->where('role', UserRole::Admin->value)
            ->whereNull('banned_at')
            ->get();
        $manager = $studio->company?->manager;

        return $this->mergeRecipients(
            $admins,
            collect([$manager])->filter(),
        );
    }

    /**
     * @param  Collection<int, User>  ...$collections
     * @return Collection<int, User>
     */
    private function mergeRecipients(Collection ...$collections): Collection
    {
        return collect($collections)
            ->flatten(1)
            ->filter(fn ($user): bool => $user instanceof User && $user->banned_at === null)
            ->unique(fn (User $user): int => (int) $user->id)
            ->values();
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<string, string>  $payload
     */
    private function send(
        Collection $recipients,
        string $title,
        string $body,
        string $type,
        array $payload
    ): int {
        foreach ($recipients as $recipient) {
            $this->fcmService->sendToUser($recipient, $title, $body, $type, $payload);
        }

        return $recipients->count();
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

    /**
     * @return array<string, string>
     */
    private function appointmentPayload(Appointment $appointment): array
    {
        return [
            'appointment_id' => (string) $appointment->id,
            'studio_id' => (string) ($appointment->studio_id ?? ''),
            'company_id' => (string) ($appointment->studio?->company_id ?? ''),
            'appointment_type' => (string) $appointment->appointment_type,
        ];
    }
}
