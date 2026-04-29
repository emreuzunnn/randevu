<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioManagerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_manager_for_own_studio(): void
    {
        [$admin, $studio] = $this->createStudioMember(UserRole::Admin);

        $response = $this->actingAs($admin)->postJson("/api/studios/{$studio->id}/managers", [
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'manager@example.com')
            ->assertJsonPath('data.studio_role', 'yonetici')
            ->assertJsonPath('data.action', 'created_new_user');

        $this->assertDatabaseHas('users', [
            'email' => 'manager@example.com',
        ]);

        $manager = User::query()->where('email', 'manager@example.com')->firstOrFail();

        $this->assertDatabaseHas('studio_user', [
            'studio_id' => $studio->id,
            'user_id' => $manager->id,
            'role' => UserRole::Yonetici->value,
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_attach_existing_user_as_manager(): void
    {
        [$admin, $studio] = $this->createStudioMember(UserRole::Admin);
        $existingUser = User::factory()->create([
            'name' => 'Existing Employee',
            'email' => 'existing@example.com',
            'role' => UserRole::Calisan,
        ]);

        $response = $this->actingAs($admin)->postJson("/api/studios/{$studio->id}/managers", [
            'name' => 'Existing Employee',
            'email' => 'existing@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'existing@example.com')
            ->assertJsonPath('data.action', 'attached_existing_user');

        $this->assertDatabaseHas('studio_user', [
            'studio_id' => $studio->id,
            'user_id' => $existingUser->id,
            'role' => UserRole::Yonetici->value,
        ]);
    }

    public function test_non_admin_cannot_create_manager(): void
    {
        [$supervisor, $studio] = $this->createStudioMember(UserRole::Supervisor);

        $response = $this->actingAs($supervisor)->postJson("/api/studios/{$studio->id}/managers", [
            'name' => 'Blocked Manager',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertForbidden();
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
