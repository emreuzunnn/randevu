<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_all_studios_in_owned_company(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Yonetici]);
        $company = Company::query()->create([
            'name' => 'Managed Company',
            'manager_user_id' => $manager->id,
        ]);
        $firstStudio = Studio::factory()->create(['company_id' => $company->id]);
        $secondStudio = Studio::factory()->create(['company_id' => $company->id]);
        $outsideStudio = Studio::factory()->create();

        $response = $this->actingAs($manager)->getJson('/api/studios/overview');

        $response->assertOk()
            ->assertJsonFragment(['id' => $firstStudio->id])
            ->assertJsonFragment(['id' => $secondStudio->id])
            ->assertJsonMissing(['id' => $outsideStudio->id]);
    }

    public function test_admin_appointments_can_be_filtered_by_company_and_studio(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $company = Company::query()->create(['name' => 'Filter Company']);
        $studio = Studio::factory()->create(['company_id' => $company->id]);
        $outsideStudio = Studio::factory()->create();

        $appointment = Appointment::factory()->create(['studio_id' => $studio->id]);
        Appointment::factory()->create(['studio_id' => $outsideStudio->id]);

        $this->actingAs($admin)
            ->getJson("/api/admin/appointments?company_id={$company->id}&studio_id={$studio->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $appointment->id)
            ->assertJsonPath('data.0.studio.company.name', 'Filter Company');
    }

    public function test_supervisor_sees_staff_in_assigned_studio_only(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $assignedStudio = Studio::factory()->create();
        $outsideStudio = Studio::factory()->create();
        $assignedEmployee = User::factory()->create(['role' => UserRole::Calisan]);
        $outsideEmployee = User::factory()->create(['role' => UserRole::Calisan]);

        $this->attach($assignedStudio, $supervisor, UserRole::Supervisor);
        $this->attach($assignedStudio, $assignedEmployee, UserRole::Calisan);
        $this->attach($outsideStudio, $outsideEmployee, UserRole::Calisan);

        $this->actingAs($supervisor)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['id' => $assignedEmployee->id])
            ->assertJsonMissing(['id' => $outsideEmployee->id]);
    }

    private function attach(Studio $studio, User $user, UserRole $role): void
    {
        $studio->users()->attach($user->id, [
            'role' => $role->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }
}
