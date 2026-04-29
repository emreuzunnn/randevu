<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_token(): void
    {
        $user = User::factory()->create([
            'name' => 'Boss',
            'surname' => 'User',
            'phone' => '5551112233',
            'email' => 'boss@example.com',
            'password' => 'password123',
            'role' => UserRole::Admin,
        ]);

        $studio = Studio::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $studio->users()->attach($user->id, [
            'role' => UserRole::Admin->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'boss@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'boss@example.com')
            ->assertJsonPath('data.studio_id', $studio->id)
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.user.status', 'working');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }

    public function test_authenticated_user_can_fetch_profile_with_token(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $studio = Studio::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        $studio->users()->attach($user->id, [
            'role' => UserRole::Admin->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $token = $user->issueApiToken();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.location', $studio->location)
            ->assertJsonPath('data.status', 'working');
    }

    public function test_authenticated_user_can_logout_and_revoke_token(): void
    {
        $user = User::factory()->create();
        $token = $user->issueApiToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'api_token' => null,
        ]);
    }
}
