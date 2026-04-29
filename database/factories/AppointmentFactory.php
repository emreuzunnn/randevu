<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'studio_id' => Studio::factory(),
            'created_by_user_id' => User::factory(),
            'assigned_driver_user_id' => null,
            'appointment_type' => 'standard',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone_country_code' => '+90',
            'phone_number' => fake()->numerify('5#########'),
            'hotel_name' => fake()->company(),
            'room_number' => fake()->numerify('####'),
            'place' => fake()->streetAddress(),
            'photo_path' => null,
            'customer_notes' => null,
            'pax' => fake()->numberBetween(1, 6),
            'appointment_at' => now()->addDay(),
            'status' => 'pending',
            'is_old_customer' => false,
            'notes' => null,
            'source_image_path' => null,
        ];
    }
}
