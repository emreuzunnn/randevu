<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_studio_settings(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $studio = Studio::factory()->create([
            'owner_user_id' => $admin->id,
            'name' => 'Old Studio',
            'notification_lead_minutes' => 30,
        ]);

        $studio->users()->attach($admin->id, [
            'role' => UserRole::Admin->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/studios/{$studio->id}", [
            'name' => 'New Studio',
            'logo_path' => 'logos/studio.png',
            'notification_lead_minutes' => 45,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Studio')
            ->assertJsonPath('data.notification_lead_minutes', 45);
    }
}
