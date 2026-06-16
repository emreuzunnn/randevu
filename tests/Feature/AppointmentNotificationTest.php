<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\PushNotification;
use App\Models\Shop;
use App\Models\Studio;
use App\Models\User;
use App\Services\AppointmentNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointment_create_notifies_all_active_branch_staff(): void
    {
        $shop = Shop::factory()->create();
        $studio = Studio::factory()->create(['shop_id' => $shop->id]);
        $branchStudio = Studio::factory()->create(['shop_id' => $shop->id]);
        $outsideStudio = Studio::factory()->create();

        $creator = $this->attachUserToStudio($studio, UserRole::Info);
        $artist = $this->attachUserToStudio($studio, UserRole::Artist);
        $driver = $this->attachUserToStudio($branchStudio, UserRole::Sofor);
        $inactiveStaff = $this->attachUserToStudio($studio, UserRole::Calisan, false);
        $outsideStaff = $this->attachUserToStudio($outsideStudio, UserRole::Info);

        $this->actingAs($creator)
            ->postJson("/api/studios/{$studio->id}/appointments", [
                'customer' => [
                    'first_name' => 'Bildirim',
                    'last_name' => 'Musteri',
                    'phone_number' => '5551112233',
                ],
                'pax' => 1,
                'appointment_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'appointment_type' => 'tattoo',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $artist->id,
            'type' => 'appointment_created',
        ]);
        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $driver->id,
            'type' => 'appointment_created',
        ]);
        $this->assertDatabaseMissing('push_notifications', [
            'user_id' => $creator->id,
            'type' => 'appointment_created',
        ]);
        $this->assertDatabaseMissing('push_notifications', [
            'user_id' => $inactiveStaff->id,
            'type' => 'appointment_created',
        ]);
        $this->assertDatabaseMissing('push_notifications', [
            'user_id' => $outsideStaff->id,
            'type' => 'appointment_created',
        ]);
    }

    public function test_due_reminders_notify_assigned_professional_and_branch_driver_once(): void
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
            'appointment_at' => now()->addMinutes(30),
            'status' => 'confirmed',
            'pickup_required' => true,
        ]);

        $service = app(AppointmentNotificationService::class);

        $this->assertSame(2, $service->sendDueReminders());
        $this->assertSame(0, $service->sendDueReminders());

        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $artist->id,
            'type' => 'appointment_reminder',
        ]);
        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $driver->id,
            'type' => 'appointment_reminder',
        ]);

        $this->assertSame(
            2,
            PushNotification::query()
                ->where('type', 'appointment_reminder')
                ->get()
                ->filter(fn (PushNotification $notification): bool => (string) data_get($notification->data, 'appointment_id') === (string) $appointment->id)
                ->count()
        );
    }

    public function test_due_reminders_fallback_to_branch_professionals_by_appointment_type(): void
    {
        $studio = Studio::factory()->create();
        $creator = $this->attachUserToStudio($studio, UserRole::Info);
        $designer = $this->attachUserToStudio($studio, UserRole::Designer);
        $artist = $this->attachUserToStudio($studio, UserRole::Artist);

        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $creator->id,
            'assigned_artist_user_id' => null,
            'appointment_type' => 'designer',
            'appointment_at' => now()->addMinutes(40),
            'status' => 'confirmed',
        ]);

        $this->assertSame(1, app(AppointmentNotificationService::class)->sendDueReminders());

        $this->assertDatabaseHas('push_notifications', [
            'user_id' => $designer->id,
            'type' => 'appointment_reminder',
        ]);
        $this->assertDatabaseMissing('push_notifications', [
            'user_id' => $artist->id,
            'type' => 'appointment_reminder',
        ]);
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
