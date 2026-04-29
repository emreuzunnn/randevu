<?php

namespace Tests\Feature;

use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:admin,yonetici'])
            ->get('/studios/{studio}/test-role-route', fn () => 'ok');
    }

    public function test_user_with_allowed_role_can_access_route(): void
    {
        $studio = Studio::factory()->create();
        $user = User::factory()->create();

        $studio->users()->attach($user->id, [
            'role' => 'admin',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/studios/{$studio->id}/test-role-route")
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_user_without_allowed_role_gets_forbidden(): void
    {
        $studio = Studio::factory()->create();
        $user = User::factory()->create();

        $studio->users()->attach($user->id, [
            'role' => 'calisan',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/studios/{$studio->id}/test-role-route")
            ->assertForbidden();
    }
}
