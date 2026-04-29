<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AppointmentSlipOcrService
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $imagePath): array
    {
        if (! is_file($imagePath)) {
            throw new RuntimeException('Gorsel dosyasi bulunamadi.');
        }

        $apiKey = config('services.openai.api_key');
        $model = config('services.openai_vision.model', 'gpt-4.1-mini');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY ayari eksik.');
        }

        $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';
        $base64Image = base64_encode((string) file_get_contents($imagePath));

        try {
            $response = Http::timeout(90)
                ->acceptJson()
                ->withToken($apiKey)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $model,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => [
                                [
                                    'type' => 'input_text',
                                    'text' => 'Extract appointment slip data from the uploaded image. Return null for unreadable fields.',
                                ],
                            ],
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'input_text',
                                    'text' => 'Read only these fields from the appointment slip image: firstname, lastname, hotel, room_number, pax, date, time. Return JSON only.',
                                ],
                                [
                                    'type' => 'input_image',
                                    'image_url' => sprintf('data:%s;base64,%s', $mimeType, $base64Image),
                                    'detail' => 'high',
                                ],
                            ],
                        ],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'appointment_slip',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'firstname' => ['type' => ['string', 'null']],
                                    'lastname' => ['type' => ['string', 'null']],
                                    'hotel' => ['type' => ['string', 'null']],
                                    'room_number' => ['type' => ['string', 'null']],
                                    'pax' => ['type' => ['integer', 'null']],
                                    'date' => ['type' => ['string', 'null']],
                                    'time' => ['type' => ['string', 'null']],
                                ],
                                'required' => ['firstname', 'lastname', 'hotel', 'room_number', 'pax', 'date', 'time'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                ])
                ->throw();
        } catch (RequestException $exception) {
            $message = $exception->response?->json('error.message')
                ?? $exception->getMessage();

            throw new RuntimeException($message, previous: $exception);
        }

        $content = $response->json('output.0.content.0.text');

        if (! is_string($content) || trim($content) === '') {
            $content = $response->json('output_text');
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            throw new RuntimeException('OCR cevabi gecersiz JSON dondu.');
        }

        return [
            'first_name' => $decoded['firstname'] ?? null,
            'last_name' => $decoded['lastname'] ?? null,
            'hotel_name' => $decoded['hotel'] ?? null,
            'room_number' => $decoded['room_number'] ?? null,
            'pax' => $decoded['pax'] ?? null,
            'date' => $decoded['date'] ?? null,
            'time' => $decoded['time'] ?? null,
        ];
    }
}
