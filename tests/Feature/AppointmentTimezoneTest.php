<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Studio;
use App\Models\User;
use App\Services\AppointmentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppointmentTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.timezone' => 'Europe/Istanbul',
            'services.whatsapp.access_token' => '',
            'services.firebase.credentials_path' => '',
        ]);
        date_default_timezone_set('Europe/Istanbul');
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 9, 24)->setTime(16, 40));
    }

    public function test_create_and_update_preserve_web_utc_time_in_database_and_detail(): void
    {
        [$supervisor, $studio] = $this->supervisor();
        $id = $this->actingAs($supervisor)
            ->postJson("/api/studios/{$studio->id}/appointments", [
                'customer' => ['first_name' => 'Time', 'last_name' => 'Test'],
                'pax' => 1,
                'appointment_type' => 'designer',
                'appointment_at' => '2026-09-24T14:00:00.000Z',
            ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('appointments', ['id' => $id, 'appointment_at' => '2026-09-24 17:00:00']);
        $this->getJson("/api/appointments/{$id}")->assertOk()->assertJsonPath('data.time', '17:00');

        $this->patchJson("/api/studios/{$studio->id}/appointments/{$id}", [
            'appointment_at' => '2026-09-24T15:30:00Z',
        ])->assertOk();
        $this->assertDatabaseHas('appointments', ['id' => $id, 'appointment_at' => '2026-09-24 18:30:00']);
        $this->getJson("/api/appointments/{$id}")->assertOk()->assertJsonPath('data.time', '18:30');
    }

    public function test_accepting_request_keeps_the_selected_time(): void
    {
        [$supervisor, $studio] = $this->supervisor();
        $designer = User::factory()->create(['role' => UserRole::Designer]);
        $studio->users()->attach($designer->id, [
            'role' => UserRole::Designer->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);
        $customer = User::factory()->create(['role' => UserRole::Kullanici]);
        $id = $this->actingAs($customer)->postJson('/api/appointments/request', [
            'studio_id' => $studio->id,
            'requested_at' => '2026-09-24T14:00:00Z',
            'type' => 'designer',
            'first_name' => 'Time',
            'last_name' => 'Test',
            'phone_country_code' => '+90',
            'phone_number' => '5551112233',
            'hotel_name' => 'Test Hotel',
            'room_number' => '101',
            'place' => 'Lobby',
            'pax' => 1,
            'image_path' => 'requests/customer.jpg',
        ])->assertCreated()->json('data.id');

        $this->assertSame('17:00', AppointmentRequest::findOrFail($id)->requested_at->format('H:i'));
        $appointmentId = $this->actingAs($supervisor)
            ->patchJson("/api/appointment-requests/{$id}/accept", [])
            ->assertOk()->json('data.appointment.id');
        $this->assertDatabaseHas('appointments', ['id' => $appointmentId, 'appointment_at' => '2026-09-24 17:00:00']);
    }

    public function test_released_mobile_can_create_list_and_edit_without_a_time_shift(): void
    {
        [$supervisor, $studio] = $this->supervisor();
        $this->withHeader('User-Agent', 'Dart/3.10 (dart:io)');
        $id = $this->actingAs($supervisor)
            ->postJson("/api/studios/{$studio->id}/appointments", [
                'customer' => ['first_name' => 'Mobile', 'last_name' => 'Test'],
                'pax' => 1,
                'appointment_type' => 'designer',
                'appointment_at' => '2026-09-24T17:00:00.000',
            ])->assertCreated()->json('data.id');

        $list = $this->getJson("/api/studios/{$studio->id}/appointments")
            ->assertOk()
            ->assertJsonPath('data.0.appointment_at', '2026-09-24T17:00:00');
        $this->assertContains('User-Agent', $list->baseResponse->getVary());
        $this->getJson('/api/home')->assertOk()
            ->assertJsonPath('data.today_appointments.0.appointment_at', '2026-09-24T17:00:00');
        $this->getJson("/api/appointments/{$id}")->assertOk()->assertJsonPath('data.time', '17:00');

        $this->patchJson("/api/studios/{$studio->id}/appointments/{$id}", [
            'appointment_at' => $list->json('data.0.appointment_at'),
        ])->assertOk();
        $this->assertDatabaseHas('appointments', ['id' => $id, 'appointment_at' => '2026-09-24 17:00:00']);

        $web = $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->getJson("/api/studios/{$studio->id}/appointments")
            ->assertOk()
            ->assertJsonPath('data.0.appointment_at', '2026-09-24T17:00:00+03:00');
        $this->assertContains('User-Agent', $web->baseResponse->getVary());
    }

    public function test_driver_receives_local_calendar_date_and_time_near_midnight(): void
    {
        [$supervisor, $studio] = $this->supervisor();
        $driver = User::factory()->create(['role' => UserRole::Sofor]);
        $studio->users()->attach($driver->id, [
            'role' => UserRole::Sofor->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);
        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $supervisor->id,
            'appointment_type' => 'designer',
            'appointment_at' => '2026-09-24T22:30:00Z',
            'pickup_required' => true,
            'status' => 'confirmed',
        ]);

        $this->actingAs($driver)->withHeader('User-Agent', 'Dart/3.10 (dart:io)')
            ->getJson('/api/my-appointments')->assertOk()
            ->assertJsonPath('data.0.appointment_at', '2026-09-25T01:30:00');
    }

    public function test_reminder_window_uses_local_time_for_utc_input(): void
    {
        [$supervisor, $studio] = $this->supervisor();
        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $supervisor->id,
            'assigned_artist_user_id' => null,
            'appointment_type' => 'designer',
            'appointment_at' => '2026-09-24T14:00:00Z',
            'status' => 'confirmed',
            'phone_number' => null,
        ]);
        $service = app(AppointmentNotificationService::class);
        $this->assertSame(0, $service->sendDueReminders(15));
        $this->travelTo(now()->setTime(16, 45));
        $this->assertSame(1, $service->sendDueReminders(15));
        $this->assertSame(0, $service->sendDueReminders(15));
        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $supervisor->id,
            'type' => 'appointment_reminder',
        ]);
        $this->assertSame('17:00', $appointment->fresh()->appointment_at->format('H:i'));
    }

    private function supervisor(): array
    {
        $studio = Studio::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Supervisor]);
        $studio->users()->attach($user->id, [
            'role' => UserRole::Supervisor->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return [$user, $studio];
    }
}
