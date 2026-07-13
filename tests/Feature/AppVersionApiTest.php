<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppVersionApiTest extends TestCase
{
    public function test_it_requires_update_below_platform_minimum_version(): void
    {
        config([
            'app_update.ios.minimum_version' => '1.4.0',
            'app_update.ios.latest_version' => '1.6.0',
            'app_update.ios.store_url' => 'https://apps.apple.com/app/id123',
        ]);

        $this->getJson('/api/app-version?platform=ios&version=1.3.9&build=15')
            ->assertOk()
            ->assertJsonPath('data.force_update', true)
            ->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.minimum_version', '1.4.0')
            ->assertJsonPath('data.store_url', 'https://apps.apple.com/app/id123');
    }

    public function test_it_allows_a_compatible_version(): void
    {
        config([
            'app_update.android.minimum_version' => '2.0.0',
            'app_update.android.latest_version' => '2.1.0',
        ]);

        $this->getJson('/api/app-version?platform=android&version=2.0.0&build=20')
            ->assertOk()
            ->assertJsonPath('data.force_update', false)
            ->assertJsonPath('data.update_available', true);
    }

    public function test_it_rejects_unknown_platforms_and_invalid_versions(): void
    {
        $this->getJson('/api/app-version?platform=windows&version=one')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['platform', 'version']);
    }
}
