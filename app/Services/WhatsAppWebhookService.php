<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

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
}
