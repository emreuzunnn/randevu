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

        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $admin->id,
            'appointment_type' => 'tattoo',
            'status' => 'completed',
            'first_name' => 'Eski',
            'last_name' => 'Müşteri',
            'phone_country_code' => '+90',
            'phone_number' => '5551112233',
            'hotel_name' => 'Old Hotel',
            'pax' => 1,
            'price' => 200,
            'appointment_at' => now()->subMonth(),
        ]);

        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $admin->id,
            'appointment_type' => 'tattoo',
            'status' => 'completed',
            'first_name' => 'Eski',
            'last_name' => 'Müşteri',
            'phone_country_code' => '+90',
            'phone_number' => '5551112233',
            'hotel_name' => 'Old Hotel',
            'pax' => 1,
            'price' => 300,
            'appointment_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('data.hotel_sources.0.hotel_name', 'Demo Hotel')
            ->assertJsonPath('data.hotel_sources.0.customer_count', 2)
            ->assertJsonPath('data.studio_revenues.0.name', 'Test Studio')
            ->assertJsonPath('data.studio_revenues.0.revenue', 5300)
            ->assertJsonPath('data.company_revenues.0.name', 'Test Company')
            ->assertJsonPath('data.company_revenues.0.revenue', 5300)
            ->assertJsonPath('data.old_customers.0.name', 'Eski Müşteri')
            ->assertJsonPath('data.old_customers.0.period_revenue', 300)
            ->assertJsonPath('data.staff_earnings.0.user_id', $staff->id)
            ->assertJsonPath('data.staff_earnings.0.earning_amount', 500);

        $this->actingAs($admin)
            ->getJson('/api/reports/hotel-revenues?search=Old')
            ->assertOk()
            ->assertJsonPath('data.totals.ticket_count', 2)
            ->assertJsonPath('data.totals.revenue', 500)
            ->assertJsonPath('data.items.0.hotel_name', 'Old Hotel');

        $this->actingAs($admin)
            ->getJson('/api/reports/old-customers?search=5551112233')
            ->assertOk()
            ->assertJsonPath('data.totals.customer_count', 1)
            ->assertJsonPath('data.items.0.name', 'Eski Müşteri');

        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

        $this->actingAs($supervisor)
            ->getJson('/api/reports/hotel-revenues')
            ->assertForbidden();
    }
}
