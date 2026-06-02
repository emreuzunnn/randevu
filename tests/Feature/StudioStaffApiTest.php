<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\StudioStaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioStaffApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_registered_supervisor(): void
    {
        [$admin, $studio] = $this->createStudioMember(UserRole::Admin);
        $candidate = User::factory()->create([
            'role' => UserRole::KullaniciRol,
            'requested_staff_role' => UserRole::Supervisor,
            'email' => 'supervisor@example.com',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/studios/{$studio->id}/supervisors", [
            'name' => 'Supervisor User',
            'email' => $candidate->email,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.studio_role', 'supervisor');

        $invitation = StudioStaffInvitation::query()->where('user_id', $candidate->id)->firstOrFail();

        $this->actingAs($candidate)
            ->patchJson("/api/staff-invitations/{$invitation->id}/accept")
            ->assertOk();

        $this->assertDatabaseHas('studio_user', [
            'studio_id' => $studio->id,
            'user_id' => $candidate->id,
            'role' => UserRole::Supervisor->value,
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $candidate->id,
            'role' => UserRole::Supervisor->value,
        ]);
    }

    public function test_manager_can_invite_registered_driver(): void
    {
        [$manager, $studio] = $this->createStudioMember(UserRole::Yonetici);
        $candidate = User::factory()->create([
            'role' => UserRole::KullaniciRol,
            'requested_staff_role' => UserRole::Sofor,
            'email' => 'driver@example.com',
        ]);

        $response = $this->actingAs($manager)->postJson("/api/studios/{$studio->id}/drivers", [
            'name' => 'Driver User',
            'email' => $candidate->email,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.studio_role', 'sofor');
    }

    public function test_manager_can_update_employee(): void
    {
        [$manager, $studio] = $this->createStudioMember(UserRole::Yonetici);
        $employee = User::factory()->create([
            'role' => UserRole::Calisan,
            'email' => 'employee@example.com',
        ]);

        $studio->users()->attach($employee->id, [
            'role' => UserRole::Calisan->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($manager)->patchJson("/api/studios/{$studio->id}/employees/{$employee->id}", [
            'name' => 'Updated Employee',
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Employee');

        $this->assertDatabaseHas('studio_user', [
            'studio_id' => $studio->id,
            'user_id' => $employee->id,
            'role' => UserRole::Calisan->value,
            'is_active' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'role' => UserRole::Kullanici->value,
        ]);
    }

    public function test_manager_can_list_employees(): void
    {
        [$manager, $studio] = $this->createStudioMember(UserRole::Yonetici);
        $employee = User::factory()->create([
            'role' => UserRole::Calisan,
            'name' => 'Visible Employee',
        ]);

        $studio->users()->attach($employee->id, [
            'role' => UserRole::Calisan->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($manager)->getJson("/api/studios/{$studio->id}/employees");

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Visible Employee',
                'studio_role' => 'calisan',
            ]);
    }

    public function test_manager_can_deactivate_driver(): void
    {
        [$manager, $studio] = $this->createStudioMember(UserRole::Yonetici);
        $driver = User::factory()->create([
            'role' => UserRole::Sofor,
        ]);

        $studio->users()->attach($driver->id, [
            'role' => UserRole::Sofor->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($manager)->deleteJson("/api/studios/{$studio->id}/drivers/{$driver->id}");

        $response->assertOk();

        $this->assertDatabaseHas('studio_user', [
            'studio_id' => $studio->id,
            'user_id' => $driver->id,
            'role' => UserRole::Sofor->value,
            'is_active' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $driver->id,
            'role' => UserRole::Kullanici->value,
        ]);
    }

    public function test_supervisor_can_deactivate_employee_in_assigned_branch(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $shop = \App\Models\Shop::factory()->create([
            'manager_user_id' => null,
            'supervisor_user_id' => $supervisor->id,
        ]);
        $studio = Studio::factory()->create(['shop_id' => $shop->id]);
        $employee = User::factory()->create(['role' => UserRole::Calisan]);

        $studio->users()->attach($employee->id, [
            'role' => UserRole::Calisan->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->actingAs($supervisor)
            ->deleteJson("/api/studios/{$studio->id}/employees/{$employee->id}")
            ->assertOk();

        $this->assertDatabaseHas('studio_user', [
            'studio_id' => $studio->id,
            'user_id' => $employee->id,
            'is_active' => 0,
        ]);
    }

    public function test_legacy_freelancer_can_receive_artist_invitation(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $shop = \App\Models\Shop::factory()->create([
            'manager_user_id' => null,
            'supervisor_user_id' => $supervisor->id,
        ]);
        $studio = Studio::factory()->create(['shop_id' => $shop->id]);
        $freelancer = User::factory()->create([
            'role' => UserRole::KullaniciRol,
            'requested_staff_role' => null,
            'email' => 'legacy.freelancer@example.com',
        ]);

        $this->actingAs($supervisor)
            ->postJson("/api/studios/{$studio->id}/artists", [
                'name' => 'Legacy Freelancer',
                'email' => $freelancer->email,
            ])
            ->assertAccepted()
            ->assertJsonPath('data.action', 'invited_existing_freelancer');

        $this->assertDatabaseHas('studio_staff_invitations', [
            'studio_id' => $studio->id,
            'user_id' => $freelancer->id,
            'role' => UserRole::Artist->value,
            'status' => 'pending',
        ]);
    }

    /**
     * @return array{0:User,1:Studio}
     */
    private function createStudioMember(UserRole $role): array
    {
        $user = User::factory()->create([
            'role' => $role,
        ]);

        $studio = Studio::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $studio->users()->attach($user->id, [
            'role' => $role->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return [$user, $studio];
    }
}
