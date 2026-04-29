<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
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
        ]);

        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $admin->id,
            'status' => 'cancelled',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('data.summary.total_appointments', 2)
            ->assertJsonPath('data.summary.cancelled_appointments', 1)
            ->assertJsonPath('data.summary.active_staff_count', 1);
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

    public function test_appointment_detail_returns_requested_fields(): void
    {
        [$employee, $studio] = $this->createStudioMember(UserRole::Calisan);
        $driver = User::factory()->create([
            'name' => 'Sofor',
            'surname' => 'Bir',
            'role' => UserRole::Sofor,
        ]);

        $studio->users()->attach($driver->id, [
            'role' => UserRole::Sofor->value,
            'work_status' => 'transfer',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $employee->id,
            'assigned_driver_user_id' => $driver->id,
            'appointment_type' => 'vip',
            'first_name' => 'Detay',
            'last_name' => 'Musteri',
            'place' => 'Ramada',
            'status' => 'confirmed',
        ]);

        $this->actingAs($employee)
            ->getJson("/api/studios/{$studio->id}/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('data.appointment_type', 'vip')
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
}
