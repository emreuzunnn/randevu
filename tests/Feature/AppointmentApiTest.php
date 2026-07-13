<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
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
            'appointment_type' => 'designer',
            'notes' => 'Test appointment',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('appointments', [
            'studio_id' => $studio->id,
            'created_by_user_id' => $employee->id,
            'pax' => 3,
            'first_name' => 'Fabian',
            'last_name' => 'Uzun',
            'phone_number' => '5551112233',
            'is_old_customer' => 0,
        ]);

        $this->assertDatabaseHas('customers', [
            'studio_id' => $studio->id,
            'first_name' => 'Fabian',
            'last_name' => 'Uzun',
            'phone_number' => '5551112233',
            'appointments_count' => 1,
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

        $secondAppointmentId = $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", [
            'customer' => [
                'first_name' => 'Fabian',
                'last_name' => 'Uzun',
                'phone_country_code' => '+90',
                'phone_number' => '5551112233',
            ],
            'pax' => 1,
            'appointment_at' => '2026-04-19 18:00:00',
        ])->assertCreated()
            ->json('data.id');

        $customer = Customer::query()
            ->where('studio_id', $studio->id)
            ->where('phone_number', '5551112233')
            ->firstOrFail();

        $this->assertSame(2, $customer->appointments_count);
        $this->assertDatabaseHas('appointments', [
            'id' => $secondAppointmentId,
            'studio_id' => $studio->id,
            'phone_number' => '5551112233',
            'customer_id' => $customer->id,
            'is_old_customer' => 1,
        ]);
    }

    public function test_can_find_old_customer_by_normalized_phone_number_and_returns_history(): void
    {
        [$employee, $studio] = $this->createStudioMember(UserRole::Calisan);

        $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", [
            'customer' => [
                'first_name' => 'Telefon',
                'last_name' => 'Musteri',
                'phone_country_code' => '+90',
                'phone_number' => '0 (555) 111-22-33',
                'hotel_name' => 'Demo Hotel',
                'room_number' => '201',
                'customer_notes' => 'Eski müşteri notu',
            ],
            'pax' => 2,
            'appointment_at' => '2026-04-18 18:00:00',
            'appointment_type' => 'tattoo',
            'price' => 5000,
        ])->assertCreated();

        $response = $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments/check-customer", [
            'customer' => [
                'phone_country_code' => '+90',
                'phone_number' => '5551112233',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_old_customer', true)
            ->assertJsonPath('data.customer.first_name', 'Telefon')
            ->assertJsonPath('data.previous_appointments.0.hotel_name', 'Demo Hotel')
            ->assertJsonPath('data.previous_appointments.0.room_number', '201')
            ->assertJsonPath('data.previous_appointments.0.pax', 2)
            ->assertJsonPath('data.previous_appointments.0.customer_notes', 'Eski müşteri notu');
    }

    public function test_studio_request_stays_on_studio_and_accept_assigns_staff_by_selected_type(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Kullanici]);
        $studio = Studio::factory()->create();
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $artist = User::factory()->create(['role' => UserRole::Artist]);
        $designer = User::factory()->create(['role' => UserRole::Designer]);

        $studio->users()->attach($supervisor->id, [
            'role' => UserRole::Supervisor->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);
        $studio->users()->attach($artist->id, [
            'role' => UserRole::Artist->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);
        $studio->users()->attach($designer->id, [
            'role' => UserRole::Designer->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $payload = [
            'studio_id' => $studio->id,
            'requested_at' => now()->addDays(2)->toIso8601String(),
            'first_name' => 'Talep',
            'last_name' => 'Musteri',
            'phone_country_code' => '+90',
            'phone_number' => '5551112233',
            'hotel_name' => 'Test Hotel',
            'room_number' => '101',
            'place' => 'Test Hotel',
            'pax' => 1,
            'image_path' => 'requests/customer.jpg',
        ];

        $this->actingAs($requester)
            ->postJson('/api/appointments/request', [
                ...$payload,
                'type' => 'tattoo',
            ])
            ->assertCreated()
            ->assertJsonPath('data.request_type', 'tattoo')
            ->assertJsonPath('data.target', null)
            ->assertJsonPath('data.studio.id', $studio->id);

        $designerRequestId = $this->actingAs($requester)
            ->postJson('/api/appointments/request', [
                ...$payload,
                'type' => 'designer',
                'requested_at' => now()->addDays(3)->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.request_type', 'designer')
            ->assertJsonPath('data.target', null)
            ->json('data.id');

        $appointmentId = $this->actingAs($supervisor)
            ->patchJson("/api/appointment-requests/{$designerRequestId}/accept", [
                'price' => '2800',
            ])
            ->assertOk()
            ->json('data.appointment.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'studio_id' => $studio->id,
            'assigned_artist_user_id' => $designer->id,
            'appointment_type' => 'designer',
            'price' => '2800.00',
        ]);
    }

    public function test_request_to_studio_bound_artist_is_handled_by_studio_and_accept_assigns_staff_with_price(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Kullanici]);
        [$supervisor, $studio] = $this->createStudioMember(UserRole::Supervisor);
        $artist = User::factory()->create(['role' => UserRole::Artist]);

        $studio->users()->attach($artist->id, [
            'role' => UserRole::Artist->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $requestId = $this->actingAs($requester)
            ->postJson('/api/appointments/request', [
                'artist_id' => $artist->id,
                'requested_at' => now()->addDays(2)->toIso8601String(),
                'type' => 'tattoo',
                'first_name' => 'Studio',
                'last_name' => 'Target',
                'phone_country_code' => '+90',
                'phone_number' => '5551112233',
                'hotel_name' => 'Test Hotel',
                'room_number' => '101',
                'place' => 'Test Hotel',
                'pax' => 1,
                'image_path' => 'requests/customer.jpg',
            ])
            ->assertCreated()
            ->assertJsonPath('data.target', null)
            ->assertJsonPath('data.studio.id', $studio->id)
            ->json('data.id');

        $appointmentId = $this->actingAs($supervisor)
            ->patchJson("/api/appointment-requests/{$requestId}/accept", [
                'price' => '4500',
            ])
            ->assertOk()
            ->json('data.appointment.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'studio_id' => $studio->id,
            'assigned_artist_user_id' => $artist->id,
            'price' => '4500.00',
        ]);
    }

    public function test_independent_artist_can_accept_request_with_price(): void
    {
        $requester = User::factory()->create(['role' => UserRole::Kullanici]);
        $artist = User::factory()->create([
            'role' => UserRole::KullaniciRol,
            'requested_staff_role' => UserRole::Artist,
        ]);

        $requestId = $this->actingAs($requester)
            ->postJson('/api/appointments/request', [
                'artist_id' => $artist->id,
                'requested_at' => now()->addDays(2)->toIso8601String(),
                'type' => 'tattoo',
                'first_name' => 'Free',
                'last_name' => 'Lancer',
                'phone_country_code' => '+90',
                'phone_number' => '5551112233',
                'hotel_name' => 'Test Hotel',
                'room_number' => '101',
                'place' => 'Test Hotel',
                'pax' => 1,
                'image_path' => 'requests/customer.jpg',
            ])
            ->assertCreated()
            ->assertJsonPath('data.target.id', $artist->id)
            ->json('data.id');

        $appointmentId = $this->actingAs($artist)
            ->patchJson("/api/appointment-requests/{$requestId}/accept", [
                'price' => '3200',
            ])
            ->assertOk()
            ->json('data.appointment.id');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointmentId,
            'studio_id' => null,
            'assigned_artist_user_id' => $artist->id,
            'price' => '3200.00',
        ]);
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
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('appointments', [
            'studio_id' => $studio->id,
            'first_name' => 'Fabian',
            'last_name' => 'Uzun',
            'phone_number' => '5551112233',
            'is_old_customer' => 1,
        ]);
    }

    public function test_employee_cannot_create_appointment_for_same_studio_same_datetime(): void
    {
        [$employee, $studio] = $this->createStudioMember(UserRole::Calisan);

        $payload = [
            'customer' => [
                'first_name' => 'Ayni',
                'last_name' => 'Saat',
            ],
            'pax' => 2,
            'appointment_at' => '2026-04-18 18:00:00',
        ];

        $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", $payload)
            ->assertCreated();

        $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", [
            ...$payload,
            'customer' => [
                'first_name' => 'Baska',
                'last_name' => 'Musteri',
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['appointment_at']);
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

    public function test_employee_cannot_update_appointment_to_conflicting_datetime(): void
    {
        [$employee, $studio] = $this->createStudioMember(UserRole::Calisan);

        $firstAppointmentId = $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", [
            'customer' => [
                'first_name' => 'Ilk',
                'last_name' => 'Randevu',
            ],
            'pax' => 2,
            'appointment_at' => '2026-04-18 18:00:00',
        ])->assertCreated()->json('data.id');

        $secondAppointmentId = $this->actingAs($employee)->postJson("/api/studios/{$studio->id}/appointments", [
            'customer' => [
                'first_name' => 'Ikinci',
                'last_name' => 'Randevu',
            ],
            'pax' => 1,
            'appointment_at' => '2026-04-18 19:00:00',
        ])->assertCreated()->json('data.id');

        $this->actingAs($employee)
            ->patchJson("/api/studios/{$studio->id}/appointments/{$secondAppointmentId}", [
                'appointment_at' => '2026-04-18 18:00:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['appointment_at']);

        $this->assertDatabaseHas('appointments', [
            'id' => $firstAppointmentId,
            'appointment_at' => '2026-04-18 18:00:00',
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
