<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    public function test_whatsapp_webhook_verification_returns_challenge_for_valid_token(): void
    {
        Config::set('services.whatsapp.verify_token', 'test-token');

        $this->get('/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=test-token&hub.challenge=abc123')
            ->assertOk()
            ->assertSee('abc123', false);
    }

    public function test_whatsapp_webhook_verification_rejects_invalid_token(): void
    {
        Config::set('services.whatsapp.verify_token', 'test-token');

        $this->get('/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123')
            ->assertForbidden();
    }

    public function test_whatsapp_webhook_post_accepts_payload(): void
    {
        Config::set('services.whatsapp.validate_signature', false);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messages' => [[
                            'from' => '905551112233',
                            'id' => 'wamid.test',
                            'timestamp' => '1710000000',
                            'type' => 'text',
                            'text' => ['body' => 'Merhaba'],
                        ]],
                        'statuses' => [[
                            'id' => 'wamid.test',
                            'status' => 'delivered',
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/webhook/whatsapp', $payload)
            ->assertOk()
            ->assertSee('OK', false);
    }

    public function test_whatsapp_test_endpoint_sends_message_with_cloud_api(): void
    {
        Config::set('services.whatsapp.access_token', 'test-access-token');
        Config::set('services.whatsapp.phone_number_id', '123456789');

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.test']],
            ], 200),
        ]);

        $this->getJson('/test-whatsapp')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('response.messages.0.id', 'wamid.test');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://graph.facebook.com/v20.0/123456789/messages'
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && $request['to'] === '905326693002'
                && $request['type'] === 'text'
                && $request['text']['body'] === 'Merhaba! Tattodesk WhatsApp API başarıyla çalışıyor 🎉';
        });
    }

    public function test_whatsapp_test_endpoint_requires_credentials(): void
    {
        Config::set('services.whatsapp.access_token', '');
        Config::set('services.whatsapp.phone_number_id', '');

        $this->getJson('/test-whatsapp')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
