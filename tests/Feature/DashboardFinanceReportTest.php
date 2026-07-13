<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFinanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_home_includes_hotel_revenue_and_staff_earning_reports(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $staff = User::factory()->create(['role' => UserRole::Info]);
        $company = Company::query()->create([
            'name' => 'Test Company',
            'manager_user_id' => $admin->id,
            'is_active' => true,
            'max_studio_count' => 10,
        ]);
        $studio = Studio::factory()->create([
            'company_id' => $company->id,
            'owner_user_id' => $admin->id,
            'name' => 'Test Studio',
        ]);

        $studio->users()->attach($staff->id, [
            'role' => UserRole::Info->value,
            'work_status' => 'working',
            'commission_rate' => 10,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $staff->id,
            'appointment_type' => 'tattoo',
            'status' => 'completed',
            'hotel_name' => 'Demo Hotel',
            'pax' => 2,
            'price' => 5000,
            'appointment_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('data.hotel_sources.0.hotel_name', 'Demo Hotel')
            ->assertJsonPath('data.hotel_sources.0.customer_count', 2)
            ->assertJsonPath('data.studio_revenues.0.name', 'Test Studio')
            ->assertJsonPath('data.studio_revenues.0.revenue', 5000)
            ->assertJsonPath('data.company_revenues.0.name', 'Test Company')
            ->assertJsonPath('data.company_revenues.0.revenue', 5000)
            ->assertJsonPath('data.staff_earnings.0.user_id', $staff->id)
            ->assertJsonPath('data.staff_earnings.0.earning_amount', 500);
    }
}
