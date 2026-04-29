<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_create_appointment(): void
    {
        [$employee, $studio] = $this->createStudioMember(UserRole::Calisan);
        $driver = User::factory()->create([
            'role' => UserRole::Sofor,
        ]);

        $studio->users()->attach($driver->id, [
            'role' => UserRole::Sofor->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", [
            'slip_image_path' => 'slips/test.jpg',
            'customer' => [
                'first_name' => 'Fabian',
                'last_name' => 'Uzun',
                'phone_number' => '5551112233',
                'hotel_name' => 'Ramada',
                'room_number' => '3211',
            ],
            'pax' => 3,
            'appointment_at' => '2026-04-18 17:00:00',
            'appointment_type' => 'standard',
            'notes' => 'Test appointment',
            'assigned_driver_user_id' => $driver->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('appointments', [
            'studio_id' => $studio->id,
            'created_by_user_id' => $employee->id,
            'assigned_driver_user_id' => $driver->id,
            'pax' => 3,
            'first_name' => 'Fabian',
            'last_name' => 'Uzun',
            'phone_number' => '5551112233',
            'is_old_customer' => 0,
        ]);
    }

    public function test_can_check_customer_status_from_previous_appointments(): void
    {
        [$employee, $studio] = $this->createStudioMember(UserRole::Calisan);

        $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", [
            'customer' => [
                'first_name' => 'Fabian',
                'last_name' => 'Uzun',
                'phone_country_code' => '+90',
                'phone_number' => '5551112233',
            ],
            'pax' => 2,
            'appointment_at' => '2026-04-18 18:00:00',
        ])->assertCreated();

        $response = $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments/check-customer", [
            'customer' => [
                'first_name' => 'Fabian',
                'last_name' => 'Uzun',
                'phone_country_code' => '+90',
                'phone_number' => '5551112233',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_old_customer', true);
    }

    public function test_employee_can_list_appointments(): void
    {
        [$employee, $studio] = $this->createStudioMember(UserRole::Calisan);

        $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", [
            'customer' => [
                'first_name' => 'Visible',
                'last_name' => 'Customer',
            ],
            'pax' => 2,
            'appointment_at' => '2026-04-18 18:00:00',
        ])->assertCreated();

        $response = $this->actingAs($employee)->getJson("/api/studios/{$studio->id}/appointments");

        $response->assertOk()
            ->assertJsonFragment([
                'first_name' => 'Visible',
                'last_name' => 'Customer',
            ]);
    }

    public function test_second_appointment_for_same_customer_is_marked_old(): void
    {
        [$employee, $studio] = $this->createStudioMember(UserRole::Calisan);

        $payload = [
            'customer' => [
                'first_name' => 'Fabian',
                'last_name' => 'Uzun',
                'phone_number' => '5551112233',
                'room_number' => '3211',
                'hotel_name' => 'Ramada',
            ],
            'pax' => 3,
            'appointment_at' => '2026-04-18 17:00:00',
        ];

        $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", $payload)
            ->assertCreated();

        $response = $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", [
            ...$payload,
            'appointment_at' => '2026-04-19 17:00:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('appointments', [
            'studio_id' => $studio->id,
            'first_name' => 'Fabian',
            'last_name' => 'Uzun',
            'phone_number' => '5551112233',
            'is_old_customer' => 1,
        ]);
    }

    public function test_employee_can_update_appointment(): void
    {
        [$employee, $studio] = $this->createStudioMember(UserRole::Calisan);

        $appointmentId = $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", [
            'customer' => [
                'first_name' => 'Update',
                'last_name' => 'Me',
            ],
            'pax' => 2,
            'appointment_at' => '2026-04-18 18:00:00',
        ])->assertCreated()->json('data.id');

        $response = $this->actingAs($employee)->patchJson("/api/studios/{$studio->id}/appointments/{$appointmentId}", [
            'customer' => [
                'hotel_name' => 'Updated Hotel',
                'room_number' => '555',
            ],
            'pax' => 4,
            'status' => 'rescheduled',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rescheduled');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'hotel_name' => 'Updated Hotel',
            'room_number' => '555',
            'pax' => 4,
            'status' => 'rescheduled',
        ]);
    }

    public function test_employee_can_delete_appointment(): void
    {
        [$employee, $studio] = $this->createStudioMember(UserRole::Calisan);

        $appointmentId = $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", [
            'customer' => [
                'first_name' => 'Delete',
                'last_name' => 'Me',
            ],
            'pax' => 1,
            'appointment_at' => '2026-04-18 19:00:00',
        ])->assertCreated()->json('data.id');

        $this->actingAs($employee)
            ->deleteJson("/api/studios/{$studio->id}/appointments/{$appointmentId}")
            ->assertOk();

        $this->assertDatabaseMissing('appointments', [
            'id' => $appointmentId,
        ]);
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
