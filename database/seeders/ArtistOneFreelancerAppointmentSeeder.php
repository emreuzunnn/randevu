<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ArtistOneFreelancerAppointmentSeeder extends Seeder
{
    public function run(): void
    {
        $artist = User::query()
            ->where('email', 'artist1@example.com')
            ->first();

        if ($artist === null) {
            return;
        }

        $phones = ['5558810101', '5558810102', '5558810103', '5558810104'];

        Appointment::query()
            ->whereNull('studio_id')
            ->where('assigned_artist_user_id', $artist->id)
            ->whereIn('phone_number', $phones)
            ->delete();

        foreach ($this->appointments() as $appointment) {
            Appointment::query()->create([
                ...$appointment,
                'studio_id' => null,
                'created_by_user_id' => $artist->id,
                'assigned_artist_user_id' => $artist->id,
                'artist_status' => 'accepted',
                'pickup_required' => false,
                'is_old_customer' => false,
                ...$this->publicToken($appointment['phone_number']),
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function appointments(): array
    {
        return [
            [
                'appointment_type' => 'tattoo',
                'first_name' => 'Freelancer',
                'last_name' => 'Geçmiş',
                'phone_country_code' => '+90',
                'phone_number' => '5558810101',
                'hotel_name' => 'Freelancer Test Hotel',
                'room_number' => 'F101',
                'place' => 'Freelancer müşteri lokasyonu',
                'pax' => 1,
                'price' => 680,
                'deposit_amount' => 180,
                'payment_method' => 'cash',
                'ticket_types' => ['tattoo'],
                'tattoo_type' => 'coverup',
                'appointment_at' => now()->subDays(18)->setTime(14, 30),
                'status' => 'completed',
                'driver_status' => null,
                'photo_path' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=900&q=80',
                'source_image_path' => 'https://images.unsplash.com/photo-1601848714157-d845bb5c11ff?auto=format&fit=crop&w=900&q=80',
                'tattoo_image_paths' => [
                    'https://images.unsplash.com/photo-1598371839696-5c5bb00bdc28?auto=format&fit=crop&w=900&q=80',
                ],
                'completed_tattoo_image_path' => 'https://images.unsplash.com/photo-1611501275019-9b5cda994e8d?auto=format&fit=crop&w=900&q=80',
                'customer_notes' => 'Artist 1 freelancer geçmiş kayıt testi.',
                'notes' => 'Freelancer tamamlanan geçmiş bilet.',
            ],
            [
                'appointment_type' => 'tattoo',
                'first_name' => 'Freelancer',
                'last_name' => 'Yaklaşan',
                'phone_country_code' => '+90',
                'phone_number' => '5558810102',
                'hotel_name' => 'Freelancer Future Hotel',
                'room_number' => 'F202',
                'place' => 'Freelancer müşteri adresi',
                'pax' => 2,
                'price' => 920,
                'deposit_amount' => 250,
                'payment_method' => 'credit_card',
                'ticket_types' => ['tattoo', 'cream_sale'],
                'tattoo_type' => 'freehand',
                'appointment_at' => now()->addDays(4)->setTime(16, 0),
                'status' => 'confirmed',
                'driver_status' => null,
                'photo_path' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=900&q=80',
                'source_image_path' => 'https://images.unsplash.com/photo-1611501275019-9b5cda994e8d?auto=format&fit=crop&w=900&q=80',
                'tattoo_image_paths' => [
                    'https://images.unsplash.com/photo-1598371839696-5c5bb00bdc28?auto=format&fit=crop&w=900&q=80',
                    'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=900&q=80',
                ],
                'completed_tattoo_image_path' => null,
                'customer_notes' => 'Artist 1 freelancer gelecek kayıt testi.',
                'notes' => 'Freelancer gelecek bilet.',
            ],
            [
                'appointment_type' => 'tattoo',
                'first_name' => 'Freelancer',
                'last_name' => 'İptal',
                'phone_country_code' => '+49',
                'phone_number' => '5558810103',
                'hotel_name' => 'Freelancer Cancel Hotel',
                'room_number' => 'F303',
                'place' => 'Freelancer müşteri oteli',
                'pax' => 1,
                'price' => 390,
                'deposit_amount' => 100,
                'payment_method' => 'cash',
                'ticket_types' => ['piercing'],
                'tattoo_type' => 'clean',
                'appointment_at' => now()->subDays(7)->setTime(12, 15),
                'status' => 'cancelled',
                'driver_status' => 'cancelled',
                'photo_path' => null,
                'source_image_path' => 'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=900&q=80',
                'tattoo_image_paths' => [
                    'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=900&q=80',
                ],
                'completed_tattoo_image_path' => null,
                'customer_notes' => 'Artist 1 freelancer iptal kayıt testi.',
                'notes' => 'Freelancer iptal geçmiş bilet.',
            ],
            [
                'appointment_type' => 'tattoo',
                'first_name' => 'Freelancer',
                'last_name' => 'Touchup',
                'phone_country_code' => '+44',
                'phone_number' => '5558810104',
                'hotel_name' => 'Freelancer Archive Hotel',
                'room_number' => 'F404',
                'place' => 'Freelancer arşiv lokasyonu',
                'pax' => 1,
                'price' => 540,
                'deposit_amount' => 140,
                'payment_method' => 'credit_card',
                'ticket_types' => ['tattoo'],
                'tattoo_type' => 'touchub',
                'appointment_at' => now()->subDays(42)->setTime(11, 45),
                'status' => 'completed',
                'driver_status' => null,
                'photo_path' => null,
                'source_image_path' => 'https://images.unsplash.com/photo-1542727365-19732a80dcfd?auto=format&fit=crop&w=900&q=80',
                'tattoo_image_paths' => [
                    'https://images.unsplash.com/photo-1542727365-19732a80dcfd?auto=format&fit=crop&w=900&q=80',
                ],
                'completed_tattoo_image_path' => 'https://images.unsplash.com/photo-1542727365-19732a80dcfd?auto=format&fit=crop&w=900&q=80',
                'customer_notes' => 'Artist 1 freelancer eski arşiv kayıt testi.',
                'notes' => 'Freelancer eski tamamlanan bilet.',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function publicToken(string $phoneNumber): array
    {
        if (! Schema::hasColumn('appointments', 'public_token')) {
            return [];
        }

        return ['public_token' => 'artist1-freelancer-'.$phoneNumber];
    }
}
