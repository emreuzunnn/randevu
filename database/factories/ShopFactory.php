<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    protected $model = Shop::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Dukkan',
            'location' => fake()->city(),
            'manager_user_id' => User::factory()->state([
                'role' => UserRole::Yonetici,
            ]),
            'is_active' => true,
        ];
    }
}
