<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\WhatsAppMessageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppNotificationService
{
    public function sendAppointmentCreated(Appointment $appointment): bool
    {
        $appointment->loadMissing('studio.company');

        return $this->sendTemplate(
            $appointment,
            'appointment_created',
            (string) config('services.whatsapp.templates.appointment_created', 'musteri_hatirlatma_tr'),
            [
                [
                    'type' => 'body',
                    'parameters' => [
                        $this->namedText('company_name', $appointment->studio?->company?->name ?? $appointment->studio?->name ?? 'Tattodesk'),
                        $this->namedText('customer_name', $this->customerName($appointment)),
                        $this->namedText('hotel_name', (string) ($appointment->hotel_name ?: '-')),
                        $this->namedText('room_number', (string) ($appointment->room_number ?: '-')),
                        $this->namedText('appointment_datetime', $appointment->appointment_at?->format('d.m.Y H:i') ?? '-'),
                    ],
                ],
            ],
        );
    }

    public function sendAppointmentReminder(Appointment $appointment, int $minutes): bool
    {
        return $this->sendTemplate(
            $appointment,
            "appointment_reminder:{$minutes}",
            (string) config('services.whatsapp.templates.appointment_reminder', 'mteri_randevu_hatrlatma'),
            [
                [
                    'type' => 'body',
                    'parameters' => [
                        $this->text($this->customerName($appointment)),
                        $this->text((string) $minutes),
                    ],
                ],
            ],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    private function sendTemplate(
        Appointment $appointment,
        string $eventType,
        string $templateName,
        array $components
    ): bool {
        $phone = $this->appointmentPhone($appointment);
        $languageCode = (string) config('services.whatsapp.template_language', 'tr');

        if ($phone === null) {
            $this->record($appointment, $eventType, '', $templateName, $languageCode, 'skipped', null, 'Müşteri telefon numarası yok.');

            return false;
        }

        if ($this->alreadySent($appointment, $eventType, $phone)) {
            return false;
        }

        $accessToken = (string) config('services.whatsapp.access_token', '');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id', '');

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
                'components' => $components,
            ],
        ];

        if ($accessToken === '' || $phoneNumberId === '') {
            $this->record($appointment, $eventType, $phone, $templateName, $languageCode, 'skipped', $payload, 'WhatsApp API ayarları eksik.');

            return false;
        }

        try {
            $version = (string) config('services.whatsapp.graph_version', 'v23.0');
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", $payload);

            $responsePayload = $response->json() ?? [];
            $messageId = is_string(data_get($responsePayload, 'messages.0.id'))
                ? (string) data_get($responsePayload, 'messages.0.id')
                : null;

            if ($response->successful()) {
                $this->record($appointment, $eventType, $phone, $templateName, $languageCode, 'sent', $payload, null, $responsePayload, $messageId);

                return true;
            }

            $error = (string) (data_get($responsePayload, 'error.message') ?: $response->body());
            $this->record($appointment, $eventType, $phone, $templateName, $languageCode, 'failed', $payload, $error, $responsePayload);

            Log::channel('whatsapp')->warning('WhatsApp template message failed', [
                'appointment_id' => $appointment->id,
                'event_type' => $eventType,
                'to' => $phone,
                'template' => $templateName,
                'status' => $response->status(),
                'response' => $responsePayload,
            ]);

            return false;
        } catch (Throwable $exception) {
            $this->record($appointment, $eventType, $phone, $templateName, $languageCode, 'failed', $payload, $exception->getMessage());

            Log::channel('whatsapp')->error('WhatsApp template message exception', [
                'appointment_id' => $appointment->id,
                'event_type' => $eventType,
                'to' => $phone,
                'template' => $templateName,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function appointmentPhone(Appointment $appointment): ?string
    {
        $country = preg_replace('/\D+/', '', (string) $appointment->phone_country_code);
        $number = preg_replace('/\D+/', '', (string) $appointment->phone_number);

        if ($number === '') {
            return null;
        }

        if (str_starts_with($number, '00')) {
            $number = substr($number, 2);
        }

        if ($country !== '' && ! str_starts_with($number, $country)) {
            $number = $country.$number;
        }

        return strlen($number) >= 8 ? $number : null;
    }

    private function customerName(Appointment $appointment): string
    {
        $name = trim("{$appointment->first_name} {$appointment->last_name}");

        return $name !== '' ? $name : 'Müşterimiz';
    }

    /**
     * Named parameters are accepted by Meta's current template editor.
     *
     * @return array<string, string>
     */
    private function namedText(string $parameterName, string $text): array
    {
        return [
            'type' => 'text',
            'parameter_name' => $parameterName,
            'text' => $text,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function text(string $text): array
    {
        return [
            'type' => 'text',
            'text' => $text,
        ];
    }

    private function alreadySent(Appointment $appointment, string $eventType, string $phone): bool
    {
        return WhatsAppMessageLog::query()
            ->where('appointment_id', $appointment->id)
            ->where('event_type', $eventType)
            ->where('recipient_phone', $phone)
            ->where('status', 'sent')
            ->exists();
    }

    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     */
    private function record(
        Appointment $appointment,
        string $eventType,
        string $phone,
        string $templateName,
        string $languageCode,
        string $status,
        ?array $requestPayload = null,
        ?string $error = null,
        ?array $responsePayload = null,
        ?string $messageId = null
    ): void {
        WhatsAppMessageLog::query()->create([
            'appointment_id' => $appointment->id,
            'event_type' => $eventType,
            'recipient_phone' => $phone,
            'template_name' => $templateName,
            'language_code' => $languageCode,
            'status' => $status,
            'whatsapp_message_id' => $messageId,
            'error_message' => $error,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
