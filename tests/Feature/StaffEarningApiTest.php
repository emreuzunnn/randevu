<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\StaffEarning;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffEarningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_tattoo_creates_earning_for_staff_who_entered_appointment(): void
    {
        $studio = Studio::factory()->create();
        $info = $this->attach($studio, UserRole::Info, 10);

        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $info->id,
            'appointment_type' => 'tattoo',
            'status' => 'completed',
            'price' => 10000,
        ]);

        $this->assertDatabaseHas('staff_earnings', [
            'appointment_id' => $appointment->id,
            'user_id' => $info->id,
            'commission_rate' => 10,
            'gross_amount' => 10000,
            'earning_amount' => 1000,
            'status' => 'pending',
        ]);
    }

    public function test_earning_is_created_when_tattoo_is_completed(): void
    {
        $studio = Studio::factory()->create();
        $employee = $this->attach($studio, UserRole::Calisan, 8);
        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $employee->id,
            'appointment_type' => 'tattoo',
            'status' => 'confirmed',
            'price' => 5000,
        ]);

        $this->assertDatabaseMissing('staff_earnings', [
            'appointment_id' => $appointment->id,
        ]);

        $appointment->update(['status' => 'completed']);

        $this->assertDatabaseHas('staff_earnings', [
            'appointment_id' => $appointment->id,
            'earning_amount' => 400,
        ]);
    }

    public function test_supervisor_can_set_staff_commission_but_not_own_rate(): void
    {
        $studio = Studio::factory()->create();
        $supervisor = $this->attach($studio, UserRole::Supervisor, 12);
        $info = $this->attach($studio, UserRole::Info, 10);

        $this->actingAs($supervisor)
            ->patchJson("/api/studios/{$studio->id}/users/{$info->id}/commission", [
                'commission_rate' => 15.5,
            ])
            ->assertOk()
            ->assertJsonPath('data.commission_rate', 15.5);

        $this->assertDatabaseHas('studio_user', [
            'studio_id' => $studio->id,
            'user_id' => $info->id,
            'commission_rate' => 15.5,
        ]);

        $this->actingAs($supervisor)
            ->patchJson("/api/studios/{$studio->id}/users/{$supervisor->id}/commission", [
                'commission_rate' => 30,
            ])
            ->assertUnprocessable();
    }

    public function test_supervisor_marks_earning_paid_and_user_is_notified(): void
    {
        $studio = Studio::factory()->create();
        $supervisor = $this->attach($studio, UserRole::Supervisor, 12);
        $info = $this->attach($studio, UserRole::Info, 10);
        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $info->id,
            'appointment_type' => 'tattoo',
            'status' => 'completed',
            'price' => 7000,
        ]);
        $earning = StaffEarning::query()->firstOrFail();

        $this->actingAs($supervisor)
            ->patchJson("/api/studios/{$studio->id}/earnings/{$earning->id}/paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('staff_earnings', [
            'id' => $earning->id,
            'status' => 'paid',
            'paid_by_user_id' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $info->id,
            'type' => 'earning_paid',
        ]);
    }

    public function test_staff_and_supervisor_can_view_earning_details(): void
    {
        $studio = Studio::factory()->create();
        $supervisor = $this->attach($studio, UserRole::Supervisor, 12);
        $info = $this->attach($studio, UserRole::Info, 10);
        $supervisor->update(['name' => 'Z Supervisor']);
        $info->update(['name' => 'A Info']);
        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $info->id,
            'appointment_type' => 'tattoo',
            'status' => 'completed',
            'price' => 9000,
        ]);

        $this->actingAs($info)
            ->getJson('/api/earnings/me')
            ->assertOk()
            ->assertJsonPath('data.summary.pending_total', 900)
            ->assertJsonPath('data.earnings.0.commission_rate', 10)
            ->assertJsonPath('data.earnings.0.earning_amount', 900);

        $this->actingAs($supervisor)
            ->getJson("/api/studios/{$studio->id}/earnings")
            ->assertOk()
            ->assertJsonPath('data.summary.pending_total', 900)
            ->assertJsonPath('data.staff.0.commission_rate', 10)
            ->assertJsonPath('data.earnings.0.user_id', $info->id);
    }

    private function attach(Studio $studio, UserRole $role, float $commissionRate): User
    {
        $user = User::factory()->create(['role' => $role]);
        $studio->users()->attach($user->id, [
            'role' => $role->value,
            'work_status' => 'working',
            'commission_rate' => $commissionRate,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return $user;
    }
}
