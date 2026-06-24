<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
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
}
