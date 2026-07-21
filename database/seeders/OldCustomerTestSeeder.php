<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Seeder;

class OldCustomerTestSeeder extends Seeder
{
    public function run(): void
    {
        $testPhones = [
            ['code' => '+90', 'number' => '5557778899'],
            ['code' => '+49', 'number' => '1517778899'],
            ['code' => '+44', 'number' => '7700778899'],
        ];

        foreach ($testPhones as $phone) {
            Appointment::query()
                ->where('phone_country_code', $phone['code'])
                ->where('phone_number', $phone['number'])
                ->delete();

            Customer::query()
                ->where('phone_country_code', $phone['code'])
                ->where('phone_number', $phone['number'])
                ->delete();
        }

        $studios = Studio::query()->orderBy('id')->take(3)->get();

        if ($studios->isEmpty()) {
            return;
        }

        $this->seedOldCustomerForStudio(
            $studios->get(0) ?? $studios->first(),
            [
                'first_name' => 'Eski',
                'last_name' => 'Müşteri',
                'phone_country_code' => '+90',
                'phone_number' => '5557778899',
                'hotel_name' => 'Eski Müşteri Test Hotel',
                'room_number' => '101',
                'place' => 'Test Hotel Lobby',
            ],
        );

        $this->seedOldCustomerForStudio(
            $studios->get(1) ?? $studios->first(),
            [
                'first_name' => 'Old',
                'last_name' => 'Customer',
                'phone_country_code' => '+49',
                'phone_number' => '1517778899',
                'hotel_name' => 'Berlin Test Hotel',
                'room_number' => '202',
                'place' => 'Berlin Test Lobby',
            ],
        );

        $this->seedOldCustomerForStudio(
            $studios->get(2) ?? $studios->first(),
            [
                'first_name' => 'History',
                'last_name' => 'Guest',
                'phone_country_code' => '+44',
                'phone_number' => '7700778899',
                'hotel_name' => 'London Test Hotel',
                'room_number' => '303',
                'place' => 'London Test Lobby',
            ],
        );
    }

    private function seedOldCustomerForStudio(Studio $studio, array $customer): void
    {
        $creator = $this->staffFor($studio, UserRole::Info)
            ?? $this->staffFor($studio, UserRole::Supervisor)
            ?? User::query()->first();

        $artist = $this->staffFor($studio, UserRole::Artist);
        $designer = $this->staffFor($studio, UserRole::Designer);

        if (!$creator) {
            return;
        }

        $phoneKey = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $customer['phone_country_code'].'-'.$customer['phone_number'])), '-');
        $tokenPrefix = 'old-customer-'.$phoneKey;

        $base = [
            'studio_id' => $studio->id,
            'created_by_user_id' => $creator->id,
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'phone_country_code' => $customer['phone_country_code'],
            'phone_number' => $customer['phone_number'],
            'hotel_name' => $customer['hotel_name'],
            'room_number' => $customer['room_number'],
            'place' => $customer['place'],
            'pax' => 1,
            'status' => 'completed',
            'driver_status' => 'dropped_off',
            'artist_status' => 'accepted',
            'is_old_customer' => true,
            'pickup_required' => true,
            'customer_notes' => 'Eski müşteri otomatik doldurma testi için seed kaydı.',
        ];

        Appointment::create(array_merge($base, [
            'appointment_type' => 'tattoo',
            'assigned_artist_user_id' => $artist?->id,
            'appointment_at' => now()->subDays(28)->setTime(14, 0),
            'public_token' => $tokenPrefix.'-coverup',
            'price' => 420,
            'deposit_amount' => 120,
            'payment_method' => 'cash',
            'ticket_types' => ['tattoo'],
            'tattoo_type' => 'coverup',
            'tattoo_image_paths' => [
                'https://images.unsplash.com/photo-1598371839696-5c5bb00bdc28?auto=format&fit=crop&w=900&q=80',
            ],
            'completed_tattoo_image_path' => 'https://images.unsplash.com/photo-1611501275019-9b5cda994e8d?auto=format&fit=crop&w=900&q=80',
            'notes' => 'Geçmiş bilet test kaydı.',
        ]));

        Appointment::create(array_merge($base, [
            'appointment_type' => 'designer',
            'assigned_artist_user_id' => $designer?->id,
            'appointment_at' => now()->subDays(14)->setTime(11, 30),
            'price' => null,
            'deposit_amount' => null,
            'payment_method' => null,
            'ticket_types' => [],
            'tattoo_type' => null,
            'tattoo_image_paths' => [],
            'notes' => 'Geçmiş tasarım randevusu test kaydı.',
        ]));

        Appointment::create(array_merge($base, [
            'appointment_type' => 'tattoo',
            'assigned_artist_user_id' => $artist?->id,
            'appointment_at' => now()->subDays(3)->setTime(16, 15),
            'public_token' => $tokenPrefix.'-piercing',
            'price' => 280,
            'deposit_amount' => 80,
            'payment_method' => 'credit_card',
            'ticket_types' => ['piercing'],
            'tattoo_type' => 'clean',
            'tattoo_image_paths' => [
                'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=900&q=80',
            ],
            'completed_tattoo_image_path' => 'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=900&q=80',
            'notes' => 'Yakın tarihli eski müşteri test kaydı.',
        ]));

        Appointment::create(array_merge($base, [
            'appointment_type' => 'tattoo',
            'assigned_artist_user_id' => $artist?->id,
            'appointment_at' => now()->subDays(7)->setTime(18, 45),
            'public_token' => $tokenPrefix.'-cancelled',
            'status' => 'cancelled',
            'driver_status' => 'cancelled',
            'artist_status' => 'rejected',
            'price' => 190,
            'deposit_amount' => 50,
            'payment_method' => 'cash',
            'ticket_types' => ['cream_sale', 'piercing_make'],
            'tattoo_type' => 'refresh',
            'pickup_required' => false,
            'tattoo_image_paths' => [
                'https://images.unsplash.com/photo-1542856391-010fb87dcfed?auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1552627019-947c3789ffb5?auto=format&fit=crop&w=900&q=80',
            ],
            'completed_tattoo_image_path' => null,
            'notes' => 'İptal edilen bilet geçmişte görünmeli.',
        ]));

        Appointment::create(array_merge($base, [
            'appointment_type' => 'tattoo',
            'assigned_artist_user_id' => $artist?->id,
            'appointment_at' => now()->addDays(2)->setTime(12, 20),
            'public_token' => $tokenPrefix.'-qr-test',
            'status' => 'confirmed',
            'driver_status' => null,
            'artist_status' => 'accepted',
            'price' => 510,
            'deposit_amount' => 150,
            'payment_method' => 'credit_card',
            'ticket_types' => ['tattoo', 'piercing'],
            'tattoo_type' => 'freehand',
            'photo_path' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=900&q=80',
            'source_image_path' => 'https://images.unsplash.com/photo-1601848714157-d845bb5c11ff?auto=format&fit=crop&w=900&q=80',
            'tattoo_image_paths' => [
                'https://images.unsplash.com/photo-1598371839696-5c5bb00bdc28?auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1611501275019-9b5cda994e8d?auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=900&q=80',
            ],
            'completed_tattoo_image_path' => null,
            'customer_notes' => 'QR geçmiş ekranı test bileti. Müşteri bütün geçmiş kayıtlarını güvenli biçimde görmeli.',
            'notes' => 'Bu kaydın QR kodu test için önerilir.',
        ]));
    }

    private function staffFor(Studio $studio, UserRole $role): ?User
    {
        return $studio->users()
            ->where('users.role', $role->value)
            ->orderBy('users.id')
            ->first();
    }
}
