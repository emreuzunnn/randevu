<?php

namespace App\Services;

use App\Models\WhatsAppInboundMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookService
{
    public function verifySignature(Request $request): bool
    {
        $appSecret = (string) config('services.whatsapp.app_secret', '');
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if ($appSecret === '' || $signature === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{messages:int,statuses:int,errors:int,changes:int,types:array<int, string>}
     */
    public function inspectPayload(array $payload): array
    {
        $summary = [
            'messages' => 0,
            'statuses' => 0,
            'errors' => 0,
            'changes' => 0,
            'types' => [],
        ];

        foreach ((array) Arr::get($payload, 'entry', []) as $entry) {
            foreach ((array) Arr::get($entry, 'changes', []) as $change) {
                $summary['changes']++;
                $field = (string) Arr::get($change, 'field', 'unknown');
                $value = (array) Arr::get($change, 'value', []);

                foreach ((array) Arr::get($value, 'messages', []) as $message) {
                    $summary['messages']++;
                    $summary['types'][] = 'message:'.(string) Arr::get($message, 'type', 'unknown');
                }

                foreach ((array) Arr::get($value, 'statuses', []) as $status) {
                    $summary['statuses']++;
                    $summary['types'][] = 'status:'.(string) Arr::get($status, 'status', 'unknown');
                }

                foreach ((array) Arr::get($value, 'errors', []) as $error) {
                    $summary['errors']++;
                    $summary['types'][] = 'error:'.(string) Arr::get($error, 'code', 'unknown');
                }

                if ($field !== 'messages') {
                    $summary['types'][] = 'change:'.$field;
                }
            }
        }

        $summary['types'] = array_values(array_unique($summary['types']));

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function logWebhook(array $payload, array $context = []): void
    {
        Log::channel('whatsapp')->info('WhatsApp webhook received', [
            ...$context,
            'summary' => $this->inspectPayload($payload),
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{stored:int,replied:int,skipped:int,failed:int}
     */
    public function processInboundMessages(array $payload): array
    {
        $result = [
            'stored' => 0,
            'replied' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ((array) Arr::get($payload, 'entry', []) as $entry) {
            foreach ((array) Arr::get($entry, 'changes', []) as $change) {
                $value = (array) Arr::get($change, 'value', []);
                $contacts = collect((array) Arr::get($value, 'contacts', []))
                    ->keyBy(fn (array $contact): string => (string) Arr::get($contact, 'wa_id', ''));

                foreach ((array) Arr::get($value, 'messages', []) as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $messageId = (string) Arr::get($message, 'id', '');
                    if ($messageId === '') {
                        continue;
                    }

                    $from = (string) Arr::get($message, 'from', '');
                    $contact = (array) ($contacts->get($from) ?? []);
                    $stored = WhatsAppInboundMessage::query()->firstOrCreate(
                        ['message_id' => $messageId],
                        [
                            'from_phone' => $from !== '' ? $from : null,
                            'profile_name' => Arr::get($contact, 'profile.name'),
                            'message_type' => Arr::get($message, 'type'),
                            'message_body' => $this->messageBody($message),
                            'payload' => $message,
                            'received_at' => $this->receivedAt($message),
                        ],
                    );

                    if (! $stored->wasRecentlyCreated) {
                        $result['skipped']++;
                        continue;
                    }

                    $result['stored']++;

                    if ($this->sendAutoReply($stored)) {
                        $result['replied']++;
                    } elseif ($stored->auto_reply_status === 'failed') {
                        $result['failed']++;
                    } else {
                        $result['skipped']++;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function messageBody(array $message): ?string
    {
        $type = (string) Arr::get($message, 'type', '');

        return match ($type) {
            'text' => Arr::get($message, 'text.body'),
            'button' => Arr::get($message, 'button.text'),
            'interactive' => Arr::get($message, 'interactive.button_reply.title')
                ?? Arr::get($message, 'interactive.list_reply.title'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function receivedAt(array $message): Carbon
    {
        $timestamp = (string) Arr::get($message, 'timestamp', '');

        return ctype_digit($timestamp)
            ? Carbon::createFromTimestamp((int) $timestamp)
            : now();
    }

    private function sendAutoReply(WhatsAppInboundMessage $message): bool
    {
        if (! (bool) config('services.whatsapp.auto_reply_enabled', true)) {
            $message->forceFill(['auto_reply_status' => 'disabled'])->save();

            Log::channel('whatsapp')->info('WhatsApp auto reply disabled', [
                'inbound_message_id' => $message->id,
                'from' => $message->from_phone,
            ]);

            return false;
        }

        if (! is_string($message->from_phone) || $message->from_phone === '') {
            $message->forceFill([
                'auto_reply_status' => 'skipped',
                'auto_reply_error' => 'Gönderen telefon numarası yok.',
            ])->save();

            Log::channel('whatsapp')->warning('WhatsApp auto reply skipped: missing sender phone', [
                'inbound_message_id' => $message->id,
            ]);

            return false;
        }

        $accessToken = (string) config('services.whatsapp.access_token', '');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id', '');
        $version = (string) config('services.whatsapp.graph_version', 'v23.0');
        $replyText = (string) config(
            'services.whatsapp.auto_reply_message',
            'Bu numara otomatik bilgilendirme amaçlıdır. Lütfen bu mesaja cevap vermeyiniz.'
        );

        if ($accessToken === '' || $phoneNumberId === '') {
            $message->forceFill([
                'auto_reply_status' => 'skipped',
                'auto_reply_error' => 'WhatsApp API ayarları eksik.',
            ])->save();

            Log::channel('whatsapp')->warning('WhatsApp auto reply skipped: missing credentials', [
                'inbound_message_id' => $message->id,
                'access_token_configured' => $accessToken !== '',
                'phone_number_id_configured' => $phoneNumberId !== '',
            ]);

            return false;
        }

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $message->from_phone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $replyText,
                    ],
                ]);

            $payload = $response->json() ?? [];
            $messageId = is_string(Arr::get($payload, 'messages.0.id'))
                ? (string) Arr::get($payload, 'messages.0.id')
                : null;

            if ($response->successful()) {
                $message->forceFill([
                    'auto_reply_status' => 'sent',
                    'auto_reply_message_id' => $messageId,
                    'auto_reply_error' => null,
                    'auto_replied_at' => now(),
                ])->save();

                Log::channel('whatsapp')->info('WhatsApp auto reply sent', [
                    'inbound_message_id' => $message->id,
                    'to' => $message->from_phone,
                    'status' => $response->status(),
                    'whatsapp_message_id' => $messageId,
                ]);

                return true;
            }

            $message->forceFill([
                'auto_reply_status' => 'failed',
                'auto_reply_error' => (string) (Arr::get($payload, 'error.message') ?: $response->body()),
            ])->save();

            Log::channel('whatsapp')->warning('WhatsApp auto reply failed', [
                'inbound_message_id' => $message->id,
                'to' => $message->from_phone,
                'status' => $response->status(),
                'response' => $payload ?: $response->body(),
            ]);

            return false;
        } catch (Throwable $exception) {
            $message->forceFill([
                'auto_reply_status' => 'failed',
                'auto_reply_error' => $exception->getMessage(),
            ])->save();

            Log::channel('whatsapp')->error('WhatsApp auto reply exception', [
                'inbound_message_id' => $message->id,
                'from' => $message->from_phone,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
