<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoverySettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_discovery_studio_visibility(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Yonetici]);

        $this->actingAs($manager)
            ->getJson('/api/discovery/settings')
            ->assertForbidden();
    }

    public function test_admin_can_hide_entire_discovery_studio_section(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Studio::factory()->create(['name' => 'Visible Studio']);

        $this->getJson('/api/public/studios')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin)
            ->patchJson('/api/discovery/settings', [
                'studios_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.studios_enabled', false);

        $this->assertFalse(AppSetting::boolean('discovery_studios_enabled', true));

        $this->getJson('/api/public/studios')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_can_hide_single_studio_from_discovery(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $visibleStudio = Studio::factory()->create(['name' => 'Visible Studio']);
        $hiddenStudio = Studio::factory()->create(['name' => 'Hidden Studio']);

        $this->actingAs($admin)
            ->patchJson('/api/discovery/settings', [
                'studios_enabled' => true,
                'studios' => [
                    ['id' => $visibleStudio->id, 'discovery_visible' => true],
                    ['id' => $hiddenStudio->id, 'discovery_visible' => false],
                ],
            ])
            ->assertOk();

        $this->getJson('/api/public/studios')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleStudio->id);

        $this->getJson("/api/public/studios/{$hiddenStudio->id}")
            ->assertNotFound();
    }
}
