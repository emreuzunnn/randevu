<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Studio>
 */
class StudioFactory extends Factory
{
    protected $model = Studio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' Studio';

        return [
            'name' => $name,
            'location' => fake()->city(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'logo_path' => null,
            'notification_lead_minutes' => 30,
            'owner_user_id' => User::factory(),
            'shop_id' => Shop::factory(),
        ];
    }
}
