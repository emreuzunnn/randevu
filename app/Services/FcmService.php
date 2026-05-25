<?php

namespace App\Services;

use App\Models\PushNotification;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FcmService
{
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

        $tokens = $user->pushTokens()
            ->pluck('token')
            ->filter()
            ->values()
            ->all();

        if ($tokens !== []) {
            $this->sendToTokens($tokens, $title, $body, [
                ...$this->stringData($data),
                'notification_id' => (string) $notification->id,
                'type'            => $type,
            ]);
        }

        return $notification;
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  array<string, string>  $data
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        if ($tokens === [] || ! $this->isConfigured()) {
            return;
        }

        $projectId = $this->projectId();
        $accessToken = $this->accessToken();
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        foreach (array_unique($tokens) as $token) {
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

            if ($response->failed()) {
                $this->handleFailedToken($token, $response->json() ?? []);
            }
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
