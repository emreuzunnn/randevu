<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyStudioHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_studio_directly_under_owned_company(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Yonetici]);
        $company = Company::query()->create([
            'name' => 'Owned Company',
            'manager_user_id' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->postJson('/api/studios', [
                'company_id' => $company->id,
                'name' => 'Merkez Studio',
                'location' => 'Istanbul',
            ])
            ->assertCreated()
            ->assertJsonPath('data.company_id', $company->id);

        $this->assertDatabaseHas('studios', [
            'company_id' => $company->id,
            'name' => 'Merkez Studio',
        ]);
    }

    public function test_manager_cannot_create_studio_for_another_company(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Yonetici]);
        Company::query()->create([
            'name' => 'Owned Company',
            'manager_user_id' => $manager->id,
        ]);
        $otherCompany = Company::query()->create(['name' => 'Other Company']);

        $this->actingAs($manager)
            ->postJson('/api/studios', [
                'company_id' => $otherCompany->id,
                'name' => 'Blocked Studio',
            ])
            ->assertForbidden();
    }

    public function test_supervisor_scope_contains_only_assigned_studio(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $assignedStudio = Studio::factory()->create();
        $otherStudio = Studio::factory()->create();

        $assignedStudio->users()->attach($supervisor->id, [
            'role' => UserRole::Supervisor->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->assertSame([$assignedStudio->id], $supervisor->staffScopeStudioIds());
        $this->assertTrue($supervisor->canManageStudio($assignedStudio));
        $this->assertFalse($supervisor->canManageStudio($otherStudio));
    }
}
