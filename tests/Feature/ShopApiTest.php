<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Shop;
use App\Models\Studio;
use App\Models\User;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_shop_for_supervisor(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $company = Company::query()->create(['name' => 'Sahil Sirket']);
        $supervisor = User::factory()->create([
            'role' => UserRole::Supervisor,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/shops', [
                'company_id' => $company->id,
                'name' => 'Sahil Dukkan',
                'location' => 'Antalya',
                'supervisor_user_id' => $supervisor->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sahil Dukkan')
            ->assertJsonPath('data.supervisor.id', $supervisor->id);
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

    public function test_manager_can_create_shop_in_owned_company_only(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Yonetici]);
        $ownedCompany = Company::query()->create([
            'name' => 'Owned Company',
            'manager_user_id' => $manager->id,
        ]);
        $otherCompany = Company::query()->create(['name' => 'Other Company']);

        $this->actingAs($manager)
            ->postJson('/api/shops', [
                'name' => 'Owned Branch',
                'location' => 'Ankara',
            ])
            ->assertCreated()
            ->assertJsonPath('data.company_id', $ownedCompany->id);

        $this->actingAs($manager)
            ->postJson('/api/shops', [
                'company_id' => $otherCompany->id,
                'name' => 'Blocked Branch',
                'location' => 'Istanbul',
            ])
            ->assertCreated()
            ->assertJsonPath('data.company_id', $ownedCompany->id);
    }

    public function test_partial_shop_update_preserves_assigned_supervisor(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $shop = Shop::factory()->create([
            'manager_user_id' => null,
            'supervisor_user_id' => $supervisor->id,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/shops/{$shop->id}", ['name' => 'Updated Branch'])
            ->assertOk()
            ->assertJsonPath('data.supervisor.id', $supervisor->id);

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'supervisor_user_id' => $supervisor->id,
        ]);
    }

    public function test_admin_can_assign_company_manager_and_manager_can_list_company(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Yonetici]);

        $companyId = $this->actingAs($admin)
            ->postJson('/api/companies', [
                'name' => 'Managed Company',
                'manager_user_id' => $manager->id,
                'max_shop_count' => 0,
                'max_studio_count' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('data.manager.id', $manager->id)
            ->json('data.id');

        $this->actingAs($manager)
            ->getJson('/api/companies')
            ->assertOk()
            ->assertJsonPath('data.0.id', $companyId);
    }

    public function test_admin_can_see_registered_supervisor_applicant_in_options(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $applicant = User::factory()->create([
            'role' => UserRole::KullaniciRol,
            'requested_staff_role' => UserRole::Supervisor,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/users/options?roles=supervisor')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $applicant->id,
                'role' => UserRole::Supervisor->value,
            ]);
    }

    public function test_supervisor_can_update_studio_inside_assigned_shop(): void
    {
        $supervisor = User::factory()->create([
            'role' => UserRole::Supervisor,
        ]);

        $shop = Shop::factory()->create([
            'manager_user_id' => null,
            'supervisor_user_id' => $supervisor->id,
        ]);

        $studio = Studio::factory()->create([
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($supervisor)
            ->patchJson("/api/studios/{$studio->id}", [
                'name' => 'Blocked Studio',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Blocked Studio');
    }

    public function test_supervisor_can_manage_appointments_for_owned_shop_studio(): void
    {
        $supervisor = User::factory()->create([
            'role' => UserRole::Supervisor,
        ]);

        $shop = Shop::factory()->create([
            'manager_user_id' => null,
            'supervisor_user_id' => $supervisor->id,
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
