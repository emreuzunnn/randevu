<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppWebhookController extends Controller
{
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
        } catch (Throwable $exception) {
            Log::channel('whatsapp')->error('WhatsApp webhook processing failed', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'raw_body' => $request->getContent(),
            ]);
        }

        return response('OK', 200);
    }
}
