<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    private const TEST_RECIPIENT = '905326693002';
    private const TEST_MESSAGE = 'Merhaba! Tattodesk WhatsApp API başarıyla çalışıyor 🎉';

    public function verify(Request $request): Response
    {
        $mode = $request->query('hub.mode', $request->query('hub_mode'));
        $token = $request->query('hub.verify_token', $request->query('hub_verify_token'));
        $challenge = $request->query('hub.challenge', $request->query('hub_challenge'));
        $verifyToken = (string) config('services.whatsapp.verify_token', '');

        if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, (string) $token)) {
            Log::channel('whatsapp')->info('WhatsApp webhook verification succeeded', [
                'mode' => $mode,
                'remote_ip' => $request->ip(),
            ]);

            return response((string) $challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::channel('whatsapp')->warning('WhatsApp webhook verification failed', [
            'mode' => $mode,
            'has_token' => $token !== null,
            'verify_token_configured' => $verifyToken !== '',
            'remote_ip' => $request->ip(),
        ]);

        return response('Forbidden', 403);
    }

    public function receive(Request $request, WhatsAppWebhookService $webhookService): Response
    {
        try {
            $signatureValidationEnabled = (bool) config('services.whatsapp.validate_signature', false);
            $signatureValid = $webhookService->verifySignature($request);

            if ($signatureValidationEnabled && ! $signatureValid) {
                Log::channel('whatsapp')->warning('WhatsApp webhook signature rejected', [
                    'remote_ip' => $request->ip(),
                    'has_signature' => $request->hasHeader('X-Hub-Signature-256'),
                ]);

                return response('Forbidden', 403);
            }

            $payload = $request->json()->all();
            if (! is_array($payload) || $payload === []) {
                $payload = json_decode($request->getContent(), true) ?: [];
            }

            $webhookService->logWebhook($payload, [
                'remote_ip' => $request->ip(),
                'signature_checked' => $signatureValidationEnabled,
                'signature_valid' => $signatureValid,
                'phone_number_id' => config('services.whatsapp.phone_number_id'),
            ]);

            $inboundSummary = $webhookService->processInboundMessages($payload);
            Log::channel('whatsapp')->info('WhatsApp inbound messages processed', $inboundSummary);
        } catch (Throwable $exception) {
            Log::channel('whatsapp')->error('WhatsApp webhook processing failed', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'raw_body' => $request->getContent(),
            ]);
        }

        return response('OK', 200);
    }

    public function sendTestMessage(): JsonResponse
    {
        $accessToken = (string) config('services.whatsapp.access_token', '');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id', '');

        if ($accessToken === '' || $phoneNumberId === '') {
            Log::channel('whatsapp')->warning('WhatsApp test message skipped: missing credentials', [
                'access_token_configured' => $accessToken !== '',
                'phone_number_id_configured' => $phoneNumberId !== '',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'WHATSAPP_ACCESS_TOKEN ve WHATSAPP_PHONE_NUMBER_ID .env içinde tanımlı olmalı.',
            ], 422);
        }

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => self::TEST_RECIPIENT,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => self::TEST_MESSAGE,
                    ],
                ]);

            $body = $response->json();

            if ($response->successful()) {
                Log::channel('whatsapp')->info('WhatsApp test message sent', [
                    'to' => self::TEST_RECIPIENT,
                    'status' => $response->status(),
                    'response' => $body,
                ]);

                return response()->json([
                    'success' => true,
                    'response' => $body,
                ]);
            }

            Log::channel('whatsapp')->warning('WhatsApp test message failed', [
                'to' => self::TEST_RECIPIENT,
                'status' => $response->status(),
                'response' => $body,
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Meta WhatsApp API isteği başarısız oldu.',
                'status' => $response->status(),
                'response' => $body ?: $response->body(),
            ], 502);
        } catch (Throwable $exception) {
            Log::channel('whatsapp')->error('WhatsApp test message exception', [
                'to' => self::TEST_RECIPIENT,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 500);
        }
    }
}
