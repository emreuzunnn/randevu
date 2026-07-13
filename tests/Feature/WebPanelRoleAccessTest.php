<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebPanelRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_mobile_roles_can_login_to_web_panel(): void
    {
        foreach ([
            UserRole::Admin,
            UserRole::Yonetici,
            UserRole::Supervisor,
            UserRole::Artist,
            UserRole::Designer,
            UserRole::Info,
            UserRole::Sofor,
            UserRole::Calisan,
            UserRole::KullaniciRol,
            UserRole::Kullanici,
        ] as $role) {
            $user = User::factory()->create([
                'email' => "{$role->value}@panel.test",
                'password' => Hash::make('123456'),
                'role' => $role,
            ]);

            $this->post('/admin/login', [
                'email' => $user->email,
                'password' => '123456',
            ])->assertRedirect(route('admin.dashboard'));

            $this->get('/admin')->assertOk();
            $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        }
    }

    public function test_management_pages_are_forbidden_to_non_manager_roles(): void
    {
        foreach ([UserRole::Sofor, UserRole::Artist, UserRole::Designer, UserRole::Kullanici] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/admin/companies')->assertForbidden();
            $this->actingAs($user)->get('/admin/studios')->assertForbidden();
            $this->actingAs($user)->get('/admin/users')->assertForbidden();
        }
    }

    public function test_appointment_detail_is_available_to_assigned_artist_studio_driver_and_creator(): void
    {
        $studio = Studio::factory()->create();
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $driver = User::factory()->create(['role' => UserRole::Sofor]);
        $creator = User::factory()->create(['role' => UserRole::Kullanici]);
        $outside = User::factory()->create(['role' => UserRole::Kullanici]);

        $studio->users()->attach($artist->id, [
            'role' => UserRole::Artist->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);
        $studio->users()->attach($driver->id, [
            'role' => UserRole::Sofor->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $creator->id,
            'assigned_artist_user_id' => $artist->id,
            'appointment_type' => 'tattoo',
            'status' => 'confirmed',
            'pickup_required' => true,
        ]);

        $this->actingAs($artist)->get("/admin/appointments/{$appointment->id}")->assertOk();
        $this->actingAs($driver)->get("/admin/appointments/{$appointment->id}")->assertOk();
        $this->actingAs($creator)->get("/admin/appointments/{$appointment->id}")->assertOk();
        $this->actingAs($outside)->get("/admin/appointments/{$appointment->id}")->assertForbidden();
    }

    public function test_shared_mobile_parity_pages_are_available_on_web_panel(): void
    {
        $user = User::factory()->create(['role' => UserRole::Kullanici]);
        $studio = Studio::factory()->create();
        $artist = User::factory()->create([
            'role' => UserRole::Artist,
            'requested_staff_role' => UserRole::Artist,
        ]);

        $this->actingAs($user)->get('/admin/discovery')->assertOk();
        $this->actingAs($user)->get("/admin/discovery/studios/{$studio->id}")->assertOk();
        $this->actingAs($user)->get("/admin/discovery/artists/{$artist->id}")->assertOk();
        $this->actingAs($user)->get('/admin/profile')->assertOk();
        $this->actingAs($user)->get('/admin/profile/appointments')->assertOk();
        $this->actingAs($user)->get('/admin/settings')->assertOk();
        $this->actingAs($user)->get('/admin/appointment-requests')->assertOk();
        $this->actingAs($user)->get('/admin/my-notifications')->assertOk();
    }
}
