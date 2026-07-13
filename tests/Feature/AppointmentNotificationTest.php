<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\Studio;
use App\Models\User;
use App\Services\AppointmentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_design_reservation_notifies_every_relevant_person_except_artist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Yonetici]);
        $studio = $this->studioWithManager($manager);
        $creator = $this->attachUserToStudio($studio, UserRole::Info);
        $designer = $this->attachUserToStudio($studio, UserRole::Designer);
        $artist = $this->attachUserToStudio($studio, UserRole::Artist);
        $driver = $this->attachUserToStudio($studio, UserRole::Sofor);
        $inactive = $this->attachUserToStudio($studio, UserRole::Calisan, false);

        $this->actingAs($creator)
            ->postJson("/api/studios/{$studio->id}/appointments", [
                'customer' => [
                    'first_name' => 'Tasarım',
                    'last_name' => 'Müşteri',
                ],
                'pax' => 1,
                'appointment_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'appointment_type' => 'designer',
            ])
            ->assertCreated();

        foreach ([$admin, $manager, $creator, $designer, $driver] as $recipient) {
            $this->assertDatabaseHas('push_notifications', [
                'user_id' => $recipient->id,
                'type' => 'design_reservation_created',
            ]);
        }
        foreach ([$artist, $inactive] as $recipient) {
            $this->assertDatabaseMissing('push_notifications', [
                'user_id' => $recipient->id,
                'type' => 'design_reservation_created',
            ]);
        }
    }

    public function test_tattoo_sale_notifies_info_designer_and_management_but_not_artist_or_driver(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Yonetici]);
        $studio = $this->studioWithManager($manager);
        $info = $this->attachUserToStudio($studio, UserRole::Info);
        $designer = $this->attachUserToStudio($studio, UserRole::Designer);
        $supervisor = $this->attachUserToStudio($studio, UserRole::Supervisor);
        $artist = $this->attachUserToStudio($studio, UserRole::Artist);
        $driver = $this->attachUserToStudio($studio, UserRole::Sofor);

        $response = $this->actingAs($supervisor)
            ->postJson("/api/studios/{$studio->id}/appointments", [
                'customer' => [
                    'first_name' => 'Satış',
                    'last_name' => 'Müşteri',
                ],
                'pax' => 1,
                'price' => 12000,
                'appointment_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'appointment_type' => 'tattoo',
            ])
            ->assertCreated();
        $appointmentId = $response->json('data.id');

        foreach ([$admin, $manager, $supervisor, $info, $designer] as $recipient) {
            $this->assertDatabaseHas('push_notifications', [
                'user_id' => $recipient->id,
                'type' => 'sale_created',
            ]);
        }
        foreach ([$artist, $driver] as $recipient) {
            $this->assertDatabaseMissing('push_notifications', [
                'user_id' => $recipient->id,
                'type' => 'sale_created',
            ]);
        }

        $this->actingAs($info)
            ->getJson("/api/appointments/{$appointmentId}")
            ->assertOk()
            ->assertJsonPath('data.price', '12000.00');
        $this->actingAs($designer)
            ->getJson("/api/appointments/{$appointmentId}")
            ->assertOk()
            ->assertJsonPath('data.price', '12000.00');
    }

    public function test_design_reminder_is_sent_at_fifteen_minutes_once(): void
    {
        $studio = Studio::factory()->create();
        $creator = $this->attachUserToStudio($studio, UserRole::Info);
        $designer = $this->attachUserToStudio($studio, UserRole::Designer);
        $artist = $this->attachUserToStudio($studio, UserRole::Artist);
        $driver = $this->attachUserToStudio($studio, UserRole::Sofor);
        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $creator->id,
            'assigned_artist_user_id' => $designer->id,
            'appointment_type' => 'designer',
            'appointment_at' => now()->addMinutes(10),
            'status' => 'confirmed',
        ]);

        $service = app(AppointmentNotificationService::class);

        $this->assertSame(3, $service->sendDueReminders(15));
        $this->assertSame(0, $service->sendDueReminders(15));
        foreach ([$creator, $designer, $driver] as $recipient) {
            $this->assertDatabaseHas('push_notifications', [
                'user_id' => $recipient->id,
                'type' => 'appointment_reminder',
            ]);
        }
        $this->assertDatabaseMissing('push_notifications', [
            'user_id' => $artist->id,
            'type' => 'appointment_reminder',
        ]);
        $this->assertSame(
            3,
            PushNotification::query()
                ->where('type', 'appointment_reminder')
                ->get()
                ->filter(fn (PushNotification $notification): bool => (string) data_get(
                    $notification->data,
                    'appointment_id'
                ) === (string) $appointment->id)
                ->count()
        );
    }

    public function test_update_start_cancel_and_complete_events_notify_all_relevant_people(): void
    {
        $studio = Studio::factory()->create();
        $creator = $this->attachUserToStudio($studio, UserRole::Info);
        $artist = $this->attachUserToStudio($studio, UserRole::Artist);
        $supervisor = $this->attachUserToStudio($studio, UserRole::Supervisor);
        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $creator->id,
            'assigned_artist_user_id' => $artist->id,
            'appointment_type' => 'tattoo',
            'status' => 'confirmed',
        ]);

        $service = app(AppointmentNotificationService::class);
        $service->notifyAppointmentUpdated($appointment, 'updated', $supervisor);
        $service->notifyAppointmentUpdated($appointment, 'started', $supervisor);
        $service->notifyAppointmentUpdated($appointment, 'cancelled', $supervisor);
        $service->notifyAppointmentUpdated($appointment, 'completed', $supervisor);

        foreach ([$creator, $artist, $supervisor] as $recipient) {
            foreach ([
                'appointment_updated',
                'appointment_started',
                'appointment_cancelled',
                'appointment_completed',
            ] as $type) {
                $this->assertDatabaseHas('push_notifications', [
                    'user_id' => $recipient->id,
                    'type' => $type,
                ]);
            }
        }
    }

    public function test_api_status_changes_emit_started_updated_and_cancelled_notifications(): void
    {
        $studio = Studio::factory()->create();
        $supervisor = $this->attachUserToStudio($studio, UserRole::Supervisor);
        $info = $this->attachUserToStudio($studio, UserRole::Info);
        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $info->id,
            'appointment_type' => 'tattoo',
            'status' => 'confirmed',
        ]);

        $this->actingAs($supervisor)
            ->patchJson("/api/studios/{$studio->id}/appointments/{$appointment->id}", [
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
        $this->actingAs($supervisor)
            ->patchJson("/api/studios/{$studio->id}/appointments/{$appointment->id}", [
                'notes' => 'Müşteri yeni motif istedi.',
            ])
            ->assertOk();
        $this->actingAs($supervisor)
            ->patchJson("/api/studios/{$studio->id}/appointments/{$appointment->id}", [
                'status' => 'cancelled',
            ])
            ->assertOk();

        foreach (['appointment_started', 'appointment_updated', 'appointment_cancelled'] as $type) {
            $this->assertDatabaseHas('push_notifications', [
                'user_id' => $info->id,
                'type' => $type,
            ]);
        }
    }

    public function test_driver_action_notifies_all_relevant_people(): void
    {
        $studio = Studio::factory()->create();
        $creator = $this->attachUserToStudio($studio, UserRole::Info);
        $artist = $this->attachUserToStudio($studio, UserRole::Artist);
        $driver = $this->attachUserToStudio($studio, UserRole::Sofor);
        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $creator->id,
            'assigned_artist_user_id' => $artist->id,
            'appointment_type' => 'tattoo',
            'status' => 'confirmed',
            'pickup_required' => true,
        ]);

        $this->actingAs($driver)
            ->patchJson("/api/studios/{$studio->id}/appointments/{$appointment->id}/driver-action", [
                'driver_status' => 'picked_up',
            ])
            ->assertOk();

        foreach ([$creator, $artist, $driver] as $recipient) {
            $this->assertDatabaseHas('push_notifications', [
                'user_id' => $recipient->id,
                'type' => 'driver_action',
            ]);
        }
    }

    public function test_artist_response_notifies_relevant_people_and_links_appointment(): void
    {
        $studio = Studio::factory()->create();
        $creator = $this->attachUserToStudio($studio, UserRole::Info);
        $artist = $this->attachUserToStudio($studio, UserRole::Artist);
        $supervisor = $this->attachUserToStudio($studio, UserRole::Supervisor);
        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $creator->id,
            'assigned_artist_user_id' => $artist->id,
            'appointment_type' => 'tattoo',
            'status' => 'confirmed',
            'artist_status' => null,
        ]);

        $this->actingAs($artist)
            ->patchJson("/api/appointments/{$appointment->id}/artist-response", [
                'response' => 'accepted',
            ])
            ->assertOk()
            ->assertJsonPath('data.artist_status', 'accepted');

        foreach ([$creator, $supervisor] as $recipient) {
            $notification = PushNotification::query()
                ->where('user_id', $recipient->id)
                ->where('type', 'artist_response')
                ->firstOrFail();
            $this->assertSame((string) $appointment->id, (string) data_get(
                $notification->data,
                'appointment_id'
            ));
        }
        $this->assertDatabaseMissing('push_notifications', [
            'user_id' => $artist->id,
            'type' => 'artist_response',
        ]);
    }

    public function test_artist_receives_immediate_notification_when_tattoo_is_assigned(): void
    {
        $studio = Studio::factory()->create();
        $supervisor = $this->attachUserToStudio($studio, UserRole::Supervisor);
        $artist = $this->attachUserToStudio($studio, UserRole::Artist);
        $appointment = Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $supervisor->id,
            'appointment_type' => 'tattoo',
            'status' => 'confirmed',
            'assigned_artist_user_id' => null,
        ]);

        $this->actingAs($supervisor)
            ->patchJson("/api/studios/{$studio->id}/appointments/{$appointment->id}/assign-artist", [
                'assigned_artist_user_id' => $artist->id,
            ])
            ->assertOk();

        $notification = PushNotification::query()
            ->where('user_id', $artist->id)
            ->where('type', 'artist_assigned')
            ->firstOrFail();
        $this->assertSame(
            (string) $appointment->id,
            (string) data_get($notification->data, 'appointment_id')
        );
    }

    public function test_request_and_acceptance_notifications_link_to_related_records(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $manager = User::factory()->create(['role' => UserRole::Yonetici]);
        $requester = User::factory()->create(['role' => UserRole::Kullanici]);
        $studio = $this->studioWithManager($manager);
        $supervisor = $this->attachUserToStudio($studio, UserRole::Supervisor);
        $info = $this->attachUserToStudio($studio, UserRole::Info);
        $designer = $this->attachUserToStudio($studio, UserRole::Designer);

        $requestId = $this->actingAs($requester)
            ->postJson('/api/appointments/request', [
                'studio_id' => $studio->id,
                'requested_at' => now()->addDays(2)->toIso8601String(),
                'type' => 'designer',
                'first_name' => 'Talep',
                'last_name' => 'Müşteri',
                'phone_country_code' => '+90',
                'phone_number' => '5551112233',
                'hotel_name' => 'Test Hotel',
                'room_number' => '101',
                'place' => 'Test Hotel',
                'pax' => 1,
                'image_path' => 'requests/customer.jpg',
            ])
            ->assertCreated()
            ->json('data.id');

        foreach ([$admin, $manager, $supervisor, $info, $designer] as $recipient) {
            $notification = PushNotification::query()
                ->where('user_id', $recipient->id)
                ->where('type', 'appointment_request')
                ->firstOrFail();
            $this->assertSame(
                (string) $requestId,
                (string) data_get($notification->data, 'appointment_request_id')
            );
        }
        $this->actingAs($info)
            ->getJson("/api/appointment-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('data.id', $requestId);
        $this->actingAs($designer)
            ->getJson("/api/appointment-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('data.id', $requestId);

        $appointmentId = $this->actingAs($supervisor)
            ->patchJson("/api/appointment-requests/{$requestId}/accept", [
                'price' => 2500,
            ])
            ->assertOk()
            ->json('data.appointment.id');

        $acceptedNotification = PushNotification::query()
            ->where('user_id', $requester->id)
            ->where('type', 'appointment_request_accepted')
            ->firstOrFail();
        $this->assertSame(
            (string) $appointmentId,
            (string) data_get($acceptedNotification->data, 'appointment_id')
        );
    }

    private function studioWithManager(User $manager): Studio
    {
        $company = Company::query()->create([
            'name' => 'Bildirim Şirketi',
            'manager_user_id' => $manager->id,
        ]);

        return Studio::factory()->create(['company_id' => $company->id]);
    }

    private function attachUserToStudio(Studio $studio, UserRole $role, bool $active = true): User
    {
        $user = User::factory()->create(['role' => $role]);
        $studio->users()->attach($user->id, [
            'role' => $role->value,
            'work_status' => 'working',
            'is_active' => $active,
            'joined_at' => now(),
        ]);

        return $user;
    }
}
