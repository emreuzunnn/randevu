<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Shop;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ApiTestSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'surname' => 'Boss',
            'phone' => '5550000001',
            'email' => 'admin@example.com',
            'password' => '123456',
            'role' => UserRole::Admin,
        ]);

        $manager = User::factory()->create([
            'name' => 'Yonetici',
            'surname' => 'Bir',
            'phone' => '5550000002',
            'email' => 'manager@example.com',
            'password' => '123456',
            'role' => UserRole::Yonetici,
        ]);

        $supervisor = User::factory()->create([
            'name' => 'Supervisor',
            'surname' => 'Bir',
            'phone' => '5550000003',
            'email' => 'supervisor@example.com',
            'password' => '123456',
            'role' => UserRole::Supervisor,
        ]);

        $driver = User::factory()->create([
            'name' => 'Sofor',
            'surname' => 'Bir',
            'phone' => '5550000004',
            'email' => 'driver@example.com',
            'password' => '123456',
            'role' => UserRole::Sofor,
        ]);

        $employee = User::factory()->create([
            'name' => 'Calisan',
            'surname' => 'Bir',
            'phone' => '5550000005',
            'email' => 'employee@example.com',
            'password' => '123456',
            'role' => UserRole::Calisan,
        ]);

        $shop = Shop::factory()->create([
            'name' => 'Merkez Dukkan',
            'location' => 'Istanbul',
            'manager_user_id' => $manager->id,
            'is_active' => true,
        ]);

        $studio = Studio::factory()->create([
            'name' => 'Merkez Studio',
            'location' => 'Istanbul',
            'slug' => Str::slug('Merkez Studio'),
            'owner_user_id' => $admin->id,
            'shop_id' => $shop->id,
            'notification_lead_minutes' => 30,
        ]);

        $studio->users()->attach($admin->id, [
            'role' => UserRole::Admin->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $studio->users()->attach($manager->id, [
            'role' => UserRole::Yonetici->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $studio->users()->attach($supervisor->id, [
            'role' => UserRole::Supervisor->value,
            'work_status' => 'working',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $studio->users()->attach($driver->id, [
            'role' => UserRole::Sofor->value,
            'work_status' => 'transfer',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $studio->users()->attach($employee->id, [
            'role' => UserRole::Calisan->value,
            'work_status' => 'break',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $employee->id,
            'assigned_driver_user_id' => $driver->id,
            'appointment_type' => 'vip',
            'first_name' => 'Fabian',
            'last_name' => 'Uzun',
            'phone_country_code' => '+90',
            'phone_number' => '5551112233',
            'hotel_name' => $studio->name,
            'room_number' => '3211',
            'place' => 'Ramada',
            'pax' => 3,
            'status' => 'confirmed',
            'is_old_customer' => false,
            'appointment_at' => now()->addDay(),
        ]);

        Appointment::factory()->create([
            'studio_id' => $studio->id,
            'created_by_user_id' => $employee->id,
            'assigned_driver_user_id' => $driver->id,
            'appointment_type' => 'standard',
            'first_name' => 'Eski',
            'last_name' => 'Musteri',
            'phone_country_code' => '+90',
            'phone_number' => '5559998877',
            'hotel_name' => $studio->name,
            'room_number' => '101',
            'place' => 'Airport Transfer',
            'pax' => 2,
            'status' => 'cancelled',
            'is_old_customer' => true,
            'appointment_at' => now()->subDay(),
        ]);
    }
}
