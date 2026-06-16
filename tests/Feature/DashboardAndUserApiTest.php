<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Shop;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_endpoint_returns_summary_and_studio_details(): void
    {
        [$admin, $studio] = $this->createStudioMember(UserRole::Admin);

        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $admin->id,
            'status' => 'pending',
            'appointment_at' => now()->subDay(),
        ]);

        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $admin->id,
            'status' => 'cancelled',
            'appointment_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('data.summary.total_appointments', 2)
            ->assertJsonPath('data.summary.cancelled_appointments', 1)
            ->assertJsonPath('data.summary.active_staff_count', 1)
            ->assertJsonPath('data.reports.daily.total_appointments', 0)
            ->assertJsonPath('data.reports.monthly.total_appointments', 2)
            ->assertJsonPath('data.reports.monthly.cancelled_appointments', 1);
    }

    public function test_manager_only_sees_reports_for_owned_shop(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Yonetici,
        ]);

        $shop = Shop::factory()->create([
            'manager_user_id' => $manager->id,
        ]);

        $ownedStudio = Studio::factory()->create([
            'shop_id' => $shop->id,
        ]);

        $otherStudio = Studio::factory()->create();

        Appointment::factory()->create([
            'studio_id' => $ownedStudio->id,
            'status' => 'completed',
            'appointment_at' => now(),
        ]);

        Appointment::factory()->create([
            'studio_id' => $ownedStudio->id,
            'status' => 'cancelled',
            'appointment_at' => now()->subMonth(),
        ]);

        Appointment::factory()->create([
            'studio_id' => $otherStudio->id,
            'status' => 'completed',
            'appointment_at' => now()->subDay(),
        ]);

        $this->actingAs($manager)
            ->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('data.reports.daily.total_appointments', 1)
            ->assertJsonPath('data.reports.daily.completed_appointments', 1)
            ->assertJsonPath('data.reports.monthly.total_appointments', 1)
            ->assertJsonPath('data.reports.monthly.cancelled_appointments', 0)
            ->assertJsonPath('data.reports.quarterly.total_appointments', 2);
    }

    public function test_admin_appointments_can_be_filtered_by_company_shop_and_studio(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $company = Company::query()->create([
            'name' => 'Filtre Sirket',
            'max_shop_count' => 0,
            'max_studio_count' => 0,
        ]);
        $shop = Shop::factory()->create([
            'company_id' => $company->id,
            'name' => 'Filtre Sube',
        ]);
        $studio = Studio::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Filtre Studio',
        ]);
        $otherStudio = Studio::factory()->create();

        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $admin->id,
            'status' => 'completed',
            'appointment_at' => now()->subDay(),
        ]);
        Appointment::factory()->create([
            'studio_id' => $otherStudio->id,
            'created_by_user_id' => $admin->id,
            'status' => 'completed',
            'appointment_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->getJson("/api/admin/appointments?company_id={$company->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.studio.name', 'Filtre Studio')
            ->assertJsonPath('data.0.studio.shop.name', 'Filtre Sube')
            ->assertJsonPath('data.0.studio.shop.company.name', 'Filtre Sirket');

        $this->actingAs($admin)
            ->getJson("/api/admin/appointments?shop_id={$shop->id}&studio_id={$studio->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_create_user_with_studio_and_role(): void
    {
        [$admin, $studio] = $this->createStudioMember(UserRole::Admin);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Yeni',
                'surname' => 'Calisan',
                'phone' => '5550001122',
                'role' => 'calisan',
                'studio_id' => $studio->id,
                'email' => 'yenicalisan@example.com',
                'password' => '123456',
                'password_confirmation' => '123456',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'calisan')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_profile_returns_name_role_email_and_studio(): void
    {
        [$admin, $studio] = $this->createStudioMember(UserRole::Admin);

        $this->actingAs($admin)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.location', $studio->location);
    }

    public function test_users_by_studio_returns_name_role_and_work_status(): void
    {
        [$admin, $studio] = $this->createStudioMember(UserRole::Admin);
        $employee = User::factory()->create([
            'name' => 'Mola',
            'surname' => 'Kisi',
            'role' => UserRole::Calisan,
        ]);

        $studio->users()->attach($employee->id, [
            'role' => UserRole::Calisan->value,
            'work_status' => 'break',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson("/api/studios/{$studio->id}/users")
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'Mola Kisi',
                'role' => 'calisan',
                'status' => 'break',
            ]);
    }

    public function test_supervisor_sees_staff_in_own_branch_only(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $shop = Shop::factory()->create([
            'manager_user_id' => null,
            'supervisor_user_id' => $supervisor->id,
        ]);
        $ownStudio = Studio::factory()->create(['shop_id' => $shop->id]);
        $siblingStudio = Studio::factory()->create(['shop_id' => $shop->id]);
        $outsideStudio = Studio::factory()->create();

        $branchEmployee = User::factory()->create([
            'name' => 'Sube',
            'surname' => 'Calisani',
            'role' => UserRole::Calisan,
        ]);
        $outsideEmployee = User::factory()->create([
            'name' => 'Baska',
            'surname' => 'Sube',
            'role' => UserRole::Calisan,
        ]);
        $this->attachStudioMember($siblingStudio, $branchEmployee, UserRole::Calisan);
        $this->attachStudioMember($outsideStudio, $outsideEmployee, UserRole::Calisan);

        $this->actingAs($supervisor)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Sube Calisani'])
            ->assertJsonMissing(['name' => 'Baska Sube']);

        $this->actingAs($supervisor)
            ->getJson("/api/studios/{$siblingStudio->id}/users")
            ->assertOk();

        $this->actingAs($supervisor)
            ->getJson("/api/studios/{$outsideStudio->id}/users")
            ->assertForbidden();
    }

    public function test_supervisor_cannot_see_or_manage_higher_roles_in_branch(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $shop = Shop::factory()->create([
            'manager_user_id' => null,
            'supervisor_user_id' => $supervisor->id,
        ]);
        $studio = Studio::factory()->create(['shop_id' => $shop->id]);
        $manager = User::factory()->create([
            'name' => 'Hidden',
            'surname' => 'Manager',
            'role' => UserRole::Yonetici,
        ]);
        $otherSupervisor = User::factory()->create([
            'name' => 'Hidden',
            'surname' => 'Supervisor',
            'role' => UserRole::Supervisor,
        ]);
        $employee = User::factory()->create([
            'name' => 'Visible',
            'surname' => 'Employee',
            'role' => UserRole::Calisan,
        ]);

        $this->attachStudioMember($studio, $manager, UserRole::Yonetici);
        $this->attachStudioMember($studio, $otherSupervisor, UserRole::Supervisor);
        $this->attachStudioMember($studio, $employee, UserRole::Calisan);

        $this->actingAs($supervisor)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Visible Employee'])
            ->assertJsonMissing(['name' => 'Hidden Manager'])
            ->assertJsonMissing(['name' => 'Hidden Supervisor']);

        $this->actingAs($supervisor)
            ->getJson("/api/studios/{$studio->id}/users")
            ->assertOk()
            ->assertJsonMissing(['name' => 'Hidden Manager'])
            ->assertJsonMissing(['name' => 'Hidden Supervisor']);

        $this->actingAs($supervisor)
            ->getJson("/api/users/{$manager->id}")
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->patchJson("/api/studios/{$studio->id}/users/{$manager->id}", [
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->getJson("/api/studios/{$studio->id}/supervisors")
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->getJson('/api/users/options?roles=supervisor')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_manager_sees_staff_in_own_company_only(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Yonetici]);
        $company = Company::query()->create([
            'name' => 'Test Sirketi',
            'manager_user_id' => $manager->id,
        ]);
        $managedShop = Shop::factory()->create([
            'company_id' => $company->id,
            'manager_user_id' => null,
        ]);
        $siblingShop = Shop::factory()->create(['company_id' => $company->id]);
        $managedStudio = Studio::factory()->create(['shop_id' => $managedShop->id]);
        $siblingStudio = Studio::factory()->create(['shop_id' => $siblingShop->id]);
        $outsideStudio = Studio::factory()->create();

        $companyEmployee = User::factory()->create([
            'name' => 'Sirket',
            'surname' => 'Calisani',
            'role' => UserRole::Calisan,
        ]);
        $outsideEmployee = User::factory()->create([
            'name' => 'Dis',
            'surname' => 'Calisan',
            'role' => UserRole::Calisan,
        ]);
        $this->attachStudioMember($siblingStudio, $companyEmployee, UserRole::Calisan);
        $this->attachStudioMember($outsideStudio, $outsideEmployee, UserRole::Calisan);

        $this->actingAs($manager)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Sirket Calisani'])
            ->assertJsonMissing(['name' => 'Dis Calisan']);

        $this->actingAs($manager)
            ->getJson("/api/studios/{$siblingStudio->id}/users")
            ->assertOk();

        $this->actingAs($manager)
            ->getJson("/api/studios/{$outsideStudio->id}/users")
            ->assertForbidden();

        $this->assertNotNull($managedStudio->id);
    }

    public function test_manager_cannot_see_or_manage_another_manager(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Yonetici]);
        $company = Company::query()->create([
            'name' => 'Managed Company',
            'manager_user_id' => $manager->id,
        ]);
        $shop = Shop::factory()->create([
            'company_id' => $company->id,
            'manager_user_id' => null,
        ]);
        $studio = Studio::factory()->create(['shop_id' => $shop->id]);
        $otherManager = User::factory()->create([
            'name' => 'Hidden',
            'surname' => 'Owner',
            'role' => UserRole::Yonetici,
        ]);
        $supervisor = User::factory()->create([
            'name' => 'Visible',
            'surname' => 'Supervisor',
            'role' => UserRole::Supervisor,
        ]);

        $this->attachStudioMember($studio, $otherManager, UserRole::Yonetici);
        $this->attachStudioMember($studio, $supervisor, UserRole::Supervisor);

        $this->actingAs($manager)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Visible Supervisor'])
            ->assertJsonMissing(['name' => 'Hidden Owner']);

        $this->actingAs($manager)
            ->getJson("/api/users/{$otherManager->id}")
            ->assertForbidden();

        $this->actingAs($manager)
            ->patchJson("/api/studios/{$studio->id}/users/{$otherManager->id}", [
                'is_active' => false,
            ])
            ->assertForbidden();

        $this->actingAs($manager)
            ->getJson('/api/users/options?roles=yonetici')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_appointment_detail_returns_requested_fields(): void
    {
        [$employee, $studio] = $this->createStudioMember(UserRole::Calisan);

        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $employee->id,
            'appointment_type' => 'designer',
            'first_name' => 'Detay',
            'last_name' => 'Musteri',
            'place' => 'Ramada',
            'status' => 'confirmed',
        ]);

        $this->actingAs($employee)
            ->getJson("/api/studios/{$studio->id}/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('data.appointment_type', 'designer')
            ->assertJsonPath('data.place', 'Ramada')
            ->assertJsonPath('data.status', 'confirmed');
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

    private function attachStudioMember(Studio $studio, User $user, UserRole $role): void
    {
        $studio->users()->attach($user->id, [
            'role' => $role->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }
}
