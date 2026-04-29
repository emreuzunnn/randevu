<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\Studio;
use App\Models\User;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_shop_for_manager(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $manager = User::factory()->create([
            'role' => UserRole::Yonetici,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/shops', [
                'name' => 'Sahil Dukkan',
                'location' => 'Antalya',
                'manager_user_id' => $manager->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sahil Dukkan')
            ->assertJsonPath('data.manager.id', $manager->id);
    }

    public function test_manager_can_update_studio_inside_owned_shop_without_direct_membership(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Yonetici,
        ]);

        $shop = Shop::factory()->create([
            'manager_user_id' => $manager->id,
        ]);

        $studio = Studio::factory()->create([
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($manager)
            ->patchJson("/api/studios/{$studio->id}", [
                'name' => 'Yonetici Studio',
                'location' => 'Bodrum',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Yonetici Studio')
            ->assertJsonPath('data.shop_id', $shop->id);
    }

    public function test_supervisor_cannot_update_studio_inside_owned_shop(): void
    {
        $supervisor = User::factory()->create([
            'role' => UserRole::Supervisor,
        ]);

        $shop = Shop::factory()->create([
            'manager_user_id' => $supervisor->id,
        ]);

        $studio = Studio::factory()->create([
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($supervisor)
            ->patchJson("/api/studios/{$studio->id}", [
                'name' => 'Blocked Studio',
            ])
            ->assertForbidden();
    }

    public function test_supervisor_can_manage_appointments_for_owned_shop_studio(): void
    {
        $supervisor = User::factory()->create([
            'role' => UserRole::Supervisor,
        ]);

        $shop = Shop::factory()->create([
            'manager_user_id' => $supervisor->id,
        ]);

        $studio = Studio::factory()->create([
            'shop_id' => $shop->id,
        ]);

        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'status' => 'pending',
        ]);

        $this->actingAs($supervisor)
            ->patchJson("/api/studios/{$studio->id}/appointments/{$appointment->id}", [
                'status' => 'confirmed',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }
}
