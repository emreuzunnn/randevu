<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppointmentSlipOcrApiTest extends TestCase
{
    public function test_api_returns_parsed_values(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai_vision.model', 'gpt-4.1-mini');

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'firstname' => 'Fabian',
                    'lastname' => 'Uzun',
                    'hotel' => 'Ramada',
                    'room_number' => '3211',
                    'pax' => 3,
                    'date' => '11.04.2026',
                    'time' => '17:00',
                ]),
            ], 200),
        ]);

        $response = $this->postJson('/api/ocr/appointment-slip', [
            'image' => UploadedFile::fake()->createWithContent(
                'slip.jpg',
                'fake-image-content'
            ),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.firstname', 'Fabian')
            ->assertJsonPath('data.time', '17:00');
    }

    public function test_api_requires_image_file(): void
    {
        $response = $this->postJson('/api/ocr/appointment-slip', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }
}
