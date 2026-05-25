<?php

namespace App\Services;

use App\Models\PushNotification;
use App\Models\PushNotificationDelivery;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FcmService
{
    /**
     * @var array{sent:int,failed:int,skipped:int,errors:array<int,array<string,mixed>>}
     */
    private array $deliveryReport = [
        'sent'    => 0,
        'failed'  => 0,
        'skipped' => 0,
        'errors'  => [],
    ];

    public function resetDeliveryReport(): void
    {
        $this->deliveryReport = [
            'sent'    => 0,
            'failed'  => 0,
            'skipped' => 0,
            'errors'  => [],
        ];
    }

    /**
     * @return array{sent:int,failed:int,skipped:int,errors:array<int,array<string,mixed>>}
     */
    public function lastDeliveryReport(): array
    {
        return $this->deliveryReport;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        string $type = 'general',
        array $data = []
    ): PushNotification {
        $notification = PushNotification::query()->create([
            'user_id' => $user->id,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);

        $pushTokens = $user->pushTokens()
            ->get(['id', 'user_id', 'token', 'token_hash', 'platform']);

        if ($pushTokens->isEmpty()) {
            $this->deliveryReport['skipped']++;
            $this->recordDelivery(
                $notification,
                null,
                null,
                'skipped',
                'NO_TOKEN',
                null,
                null,
                'Kullanıcının kayıtlı push tokenı yok.',
            );

            return $notification;
        }

        $this->sendToPushTokens($notification, $pushTokens, $title, $body, [
            ...$this->stringData($data),
            'notification_id' => (string) $notification->id,
            'type'            => $type,
        ]);

        return $notification;
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, string>  $data
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));

        $targets = array_map(
            fn (string $token): array => [
                'token'        => $token,
                'push_token'   => null,
                'notification' => null,
            ],
            $tokens,
        );

        $this->dispatchToTargets($targets, $title, $body, $data);
    }

    /**
     * @param  iterable<int, PushToken>  $pushTokens
     * @param  array<string, string>  $data
     */
    private function sendToPushTokens(
        PushNotification $notification,
        iterable $pushTokens,
        string $title,
        string $body,
        array $data = []
    ): void {
        $targets = [];

        foreach ($pushTokens as $pushToken) {
            if (! is_string($pushToken->token) || trim($pushToken->token) === '') {
                continue;
            }

            $targets[] = [
                'token'        => $pushToken->token,
                'push_token'   => $pushToken,
                'notification' => $notification,
            ];
        }

        $this->dispatchToTargets($targets, $title, $body, $data);
    }

    /**
     * @param  array<int, array{token:string,push_token:?PushToken,notification:?PushNotification}>  $targets
     * @param  array<string, string>  $data
     */
    private function dispatchToTargets(array $targets, string $title, string $body, array $data = []): void
    {
        if ($targets === []) {
            $this->deliveryReport['skipped']++;

            return;
        }

        if (! $this->isConfigured()) {
            foreach ($targets as $target) {
                $this->deliveryReport['skipped']++;
                $this->recordDelivery(
                    $target['notification'],
                    $target['push_token'],
                    $target['token'],
                    'skipped',
                    'NOT_CONFIGURED',
                    null,
                    null,
                    'Firebase service account ayarı eksik.',
                );
            }

            return;
        }

        try {
            $projectId = $this->projectId();
            $accessToken = $this->accessToken();
        } catch (RuntimeException $exception) {
            $this->deliveryReport['failed'] += count($targets);
            $this->deliveryReport['errors'][] = [
                'status'  => 'CONFIG_ERROR',
                'message' => $exception->getMessage(),
            ];

            Log::warning('FCM configuration failed.', [
                'message' => $exception->getMessage(),
            ]);

            foreach ($targets as $target) {
                $this->recordDelivery(
                    $target['notification'],
                    $target['push_token'],
                    $target['token'],
                    'failed',
                    'CONFIG_ERROR',
                    null,
                    null,
                    $exception->getMessage(),
                );
            }

            return;
        }

        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        foreach ($targets as $target) {
            $token = $target['token'];

            try {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->post($endpoint, [
                        'message' => [
                            'token'        => $token,
                            'notification' => [
                                'title' => $title,
                                'body'  => $body,
                            ],
                            'data' => $data,
                            'android' => [
                                'priority' => 'high',
                            ],
                            'apns' => [
                                'payload' => [
                                    'aps' => [
                                        'sound' => 'default',
                                    ],
                                ],
                            ],
                            'webpush' => [
                                'notification' => [
                                    'title' => $title,
                                    'body'  => $body,
                                    'icon'  => '/icons/Icon-192.png',
                                ],
                            ],
                        ],
                    ]);
            } catch (Throwable $exception) {
                $this->deliveryReport['failed']++;
                $this->addDeliveryError($token, 'REQUEST_FAILED', $exception->getMessage());
                $this->recordDelivery(
                    $target['notification'],
                    $target['push_token'],
                    $token,
                    'failed',
                    'REQUEST_FAILED',
                    null,
                    null,
                    $exception->getMessage(),
                );
                continue;
            }

            if ($response->failed()) {
                $payload = $response->json() ?? [];
                $status = (string) data_get($payload, 'error.status', 'UNKNOWN');
                $message = (string) data_get($payload, 'error.message', 'FCM request failed.');
                $errorCode = $this->firstFcmErrorCode($payload);
                $this->deliveryReport['failed']++;
                $this->addDeliveryError(
                    $token,
                    $status,
                    $message,
                    $errorCode,
                );
                $this->recordDelivery(
                    $target['notification'],
                    $target['push_token'],
                    $token,
                    'failed',
                    $status,
                    $errorCode,
                    null,
                    $message,
                    $payload,
                );
                $this->handleFailedToken($token, $payload);
                continue;
            }

            $payload = $response->json() ?? [];
            $this->deliveryReport['sent']++;
            $this->recordDelivery(
                $target['notification'],
                $target['push_token'],
                $token,
                'sent',
                'OK',
                null,
                is_string($payload['name'] ?? null) ? $payload['name'] : null,
                null,
                $payload,
            );
        }
    }

    public function isConfigured(): bool
    {
        try {
            $credentials = $this->credentials();

            return filled($this->projectId())
                && filled($credentials['client_email'] ?? null)
                && filled($credentials['private_key'] ?? null);
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $json = config('services.firebase.credentials_json');
        if (is_string($json) && trim($json) !== '') {
            $decoded = $this->decodeCredentialsJson($json);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        $path = config('services.firebase.credentials_path');
        if (is_string($path) && trim($path) !== '' && is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $clientEmail = config('services.firebase.client_email');
        $privateKey = config('services.firebase.private_key');

        if (is_string($clientEmail) && is_string($privateKey)) {
            return [
                'client_email' => $clientEmail,
                'private_key'  => str_replace('\\n', "\n", $privateKey),
            ];
        }

        throw new RuntimeException('Firebase service account credentials are missing.');
    }

    private function projectId(): string
    {
        return (string) config('services.firebase.project_id', 'tattoodesk-3390d');
    }

    private function accessToken(): string
    {
        return Cache::remember('firebase_access_token', now()->addMinutes(50), function (): string {
            $credentials = $this->credentials();
            $now = time();
            $jwt = $this->jwt([
                'iss'   => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ], (string) $credentials['private_key']);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Firebase access token could not be created.');
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function jwt(array $claims, string $privateKey): string
    {
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode($claims, JSON_THROW_ON_ERROR));
        $signingInput = "{$header}.{$payload}";

        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Firebase JWT could not be signed.');
        }

        return $signingInput . '.' . $this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringData(array $data): array
    {
        return collect($data)
            ->mapWithKeys(fn ($value, string $key): array => [$key => is_scalar($value) ? (string) $value : (string) json_encode($value)])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeCredentialsJson(string $json): ?array
    {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $base64 = base64_decode($json, true);
        if (! is_string($base64)) {
            return null;
        }

        $decoded = json_decode($base64, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function addDeliveryError(string $token, string $status, string $message, ?string $errorCode = null): void
    {
        $error = [
            'token'   => substr($token, 0, 12) . '...',
            'status'  => $status,
            'message' => $message,
        ];

        if ($errorCode !== null && $errorCode !== '') {
            $error['error_code'] = $errorCode;
        }

        $this->deliveryReport['errors'][] = $error;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function firstFcmErrorCode(array $payload): ?string
    {
        $details = data_get($payload, 'error.details');
        if (! is_array($details)) {
            return null;
        }

        foreach ($details as $detail) {
            if (is_array($detail) && is_string($detail['errorCode'] ?? null)) {
                return $detail['errorCode'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function recordDelivery(
        ?PushNotification $notification,
        ?PushToken $pushToken,
        ?string $token,
        string $status,
        ?string $fcmStatus = null,
        ?string $fcmErrorCode = null,
        ?string $fcmMessageId = null,
        ?string $errorMessage = null,
        ?array $response = null,
    ): void {
        try {
            PushNotificationDelivery::query()->create([
                'push_notification_id' => $notification?->id,
                'user_id'              => $notification?->user_id ?? $pushToken?->user_id,
                'push_token_id'        => $pushToken?->id,
                'platform'             => $pushToken?->platform,
                'token_hash'           => $pushToken?->token_hash ?? (is_string($token) && $token !== '' ? hash('sha256', $token) : null),
                'status'               => $status,
                'fcm_status'           => $fcmStatus,
                'fcm_error_code'       => $fcmErrorCode,
                'fcm_message_id'       => $fcmMessageId,
                'error_message'        => $errorMessage,
                'response'             => $response,
                'attempted_at'         => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Push notification delivery log could not be saved.', [
                'status'  => $status,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleFailedToken(string $token, array $payload): void
    {
        $status = data_get($payload, 'error.status');

        if (in_array($status, ['INVALID_ARGUMENT', 'NOT_FOUND', 'UNREGISTERED'], true)) {
            PushToken::query()->where('token_hash', hash('sha256', $token))->delete();
            return;
        }

        Log::warning('FCM notification failed.', [
            'status' => $status,
            'token'  => substr($token, 0, 12) . '...',
        ]);
    }
}
