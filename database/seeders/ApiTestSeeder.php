<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Shop;
use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Seeder;

class ApiTestSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. PLATFORM YÖNETİCİLERİ ──────────────────────────────────────
        $admin = User::factory()->create([
            'name'     => 'Platform',
            'surname'  => 'Admin',
            'phone'    => '5550000001',
            'email'    => 'admin@example.com',
            'password' => '123456',
            'role'     => UserRole::Admin,
            'bio'      => 'Tüm sistemi yöneten platform admini.',
        ]);

        $yonetici = User::factory()->create([
            'name'     => 'Genel',
            'surname'  => 'Yönetici',
            'phone'    => '5550000002',
            'email'    => 'yonetici@example.com',
            'password' => '123456',
            'role'     => UserRole::Yonetici,
            'bio'      => 'Şirket bazında dükkan ve stüdyo yöneticisi.',
        ]);

        // ── 2. STÜDYO 1 ÇALIŞANLARI ───────────────────────────────────────
        $supervisor1 = User::factory()->create([
            'name'     => 'Süpervizör',
            'surname'  => 'Bir',
            'phone'    => '5550000003',
            'email'    => 'supervisor1@example.com',
            'password' => '123456',
            'role'     => UserRole::Supervisor,
            'bio'      => 'Stüdyo 1 yöneticisi ve süpervizörü.',
        ]);

        $designer1 = User::factory()->create([
            'name'      => 'Tasarımcı',
            'surname'   => 'Bir',
            'phone'     => '5550000004',
            'email'     => 'designer1@example.com',
            'password'  => '123456',
            'role'      => UserRole::Designer,
            'bio'       => 'Geometrik ve minimalist dövme tasarımları.',
            'rating'    => 4.8,
            'portfolio' => [
                ['title' => 'Geometrik Mandala',  'image_path' => null, 'description' => 'İnce çizgilerle merkez mandala tasarımı.', 'category' => 'geometric'],
                ['title' => 'Minimalist Çiçek',   'image_path' => null, 'description' => 'Tek çizgi tekniğiyle çiçek tasarımı.',    'category' => 'minimalist'],
            ],
        ]);

        $artist1 = User::factory()->create([
            'name'      => 'Artist',
            'surname'   => 'Bir',
            'phone'     => '5550000005',
            'email'     => 'artist1@example.com',
            'password'  => '123456',
            'role'      => UserRole::Artist,
            'bio'       => 'Realistik portre uzmanı. 8 yıllık deneyim.',
            'rating'    => 4.9,
            'portfolio' => [
                ['title' => 'Realistik Aslan',  'image_path' => null, 'description' => 'Siyah-gri tekniğiyle omuz dövmesi.',     'category' => 'realism'],
                ['title' => 'Tribal Full Kol',  'image_path' => null, 'description' => 'Full sleeve tribal kompozisyon.',        'category' => 'tribal'],
                ['title' => 'Gül Buketi',       'image_path' => null, 'description' => 'Ön kol için renk çalışması.',           'category' => 'floral'],
            ],
        ]);

        $artist1b = User::factory()->create([
            'name'      => 'Artist',
            'surname'   => 'Bir-B',
            'phone'     => '5550000006',
            'email'     => 'artist1b@example.com',
            'password'  => '123456',
            'role'      => UserRole::Artist,
            'bio'       => 'Traditional ve Neo-traditional stil.',
            'rating'    => 4.7,
            'portfolio' => [
                ['title' => 'Neo-Traditional Kartal', 'image_path' => null, 'description' => 'Kalın konturlu neo-traditional stil.',  'category' => 'neo-traditional'],
                ['title' => 'American Traditional',   'image_path' => null, 'description' => 'Klasik sailor jerry tarzı gemi.',       'category' => 'traditional'],
            ],
        ]);

        $info1 = User::factory()->create([
            'name'     => 'Info',
            'surname'  => 'Bir',
            'phone'    => '5550000007',
            'email'    => 'info1@example.com',
            'password' => '123456',
            'role'     => UserRole::Info,
        ]);

        $sofor1 = User::factory()->create([
            'name'     => 'Şoför',
            'surname'  => 'Bir',
            'phone'    => '5550000008',
            'email'    => 'sofor1@example.com',
            'password' => '123456',
            'role'     => UserRole::Sofor,
        ]);

        // ── 3. STÜDYO 2 ÇALIŞANLARI ───────────────────────────────────────
        $supervisor2 = User::factory()->create([
            'name'     => 'Süpervizör',
            'surname'  => 'İki',
            'phone'    => '5550000009',
            'email'    => 'supervisor2@example.com',
            'password' => '123456',
            'role'     => UserRole::Supervisor,
            'bio'      => 'Stüdyo 2 yöneticisi ve süpervizörü.',
        ]);

        $designer2 = User::factory()->create([
            'name'      => 'Tasarımcı',
            'surname'   => 'İki',
            'phone'     => '5550000010',
            'email'     => 'designer2@example.com',
            'password'  => '123456',
            'role'      => UserRole::Designer,
            'bio'       => 'Watercolor ve abstract dövme tasarımları.',
            'rating'    => 4.6,
            'portfolio' => [
                ['title' => 'Watercolor Kelebek', 'image_path' => null, 'description' => 'Sıvı boya etkisi, canlı renkler.',         'category' => 'watercolor'],
                ['title' => 'Abstract Fırtına',   'image_path' => null, 'description' => 'Soyut çizgilerle fırtına kompozisyonu.',   'category' => 'abstract'],
            ],
        ]);

        $artist2 = User::factory()->create([
            'name'      => 'Artist',
            'surname'   => 'İki',
            'phone'     => '5550000011',
            'email'     => 'artist2@example.com',
            'password'  => '123456',
            'role'      => UserRole::Artist,
            'bio'       => 'Fine line ve blackwork uzmanı.',
            'rating'    => 4.5,
            'portfolio' => [
                ['title' => 'Fine Line Botanik', 'image_path' => null, 'description' => 'İnce çizgiyle botanik yaprak serisi.', 'category' => 'fine-line'],
                ['title' => 'Blackwork Geometri','image_path' => null, 'description' => 'Tam siyah geometrik pattern.',         'category' => 'blackwork'],
            ],
        ]);

        $artist2b = User::factory()->create([
            'name'      => 'Artist',
            'surname'   => 'İki-B',
            'phone'     => '5550000012',
            'email'     => 'artist2b@example.com',
            'password'  => '123456',
            'role'      => UserRole::Artist,
            'bio'       => 'Japanese ve Irezumi stil.',
            'rating'    => 4.8,
            'portfolio' => [
                ['title' => 'Koi Balığı Tam Kol', 'image_path' => null, 'description' => 'Geleneksel Japanese tam kol çalışması.', 'category' => 'japanese'],
                ['title' => 'Hannya Maskesi',      'image_path' => null, 'description' => 'Irezumi tekniğiyle sırt üst bölüm.',     'category' => 'japanese'],
            ],
        ]);

        $info2 = User::factory()->create([
            'name'     => 'Info',
            'surname'  => 'İki',
            'phone'    => '5550000013',
            'email'    => 'info2@example.com',
            'password' => '123456',
            'role'     => UserRole::Info,
        ]);

        $sofor2 = User::factory()->create([
            'name'     => 'Şoför',
            'surname'  => 'İki',
            'phone'    => '5550000014',
            'email'    => 'sofor2@example.com',
            'password' => '123456',
            'role'     => UserRole::Sofor,
        ]);

        // ── 4. DİĞER ROLLER ───────────────────────────────────────────────
        $calisan = User::factory()->create([
            'name'     => 'Çalışan',
            'surname'  => 'Bir',
            'phone'    => '5550000015',
            'email'    => 'calisan@example.com',
            'password' => '123456',
            'role'     => UserRole::Calisan,
        ]);

        $kullaniciRol = User::factory()->create([
            'name'      => 'Freelancer',
            'surname'   => 'Artist',
            'phone'     => '5550000016',
            'email'     => 'freelancer@example.com',
            'password'  => '123456',
            'role'      => UserRole::KullaniciRol,
            'bio'       => 'Bağımsız çalışan dövme sanatçısı.',
            'rating'    => 4.3,
            'portfolio' => [
                ['title' => 'Minimalist Ay',  'image_path' => null, 'description' => 'Tek çizgi ay serisi.',     'category' => 'minimalist'],
                ['title' => 'Script Yazı',    'image_path' => null, 'description' => 'El yazısı metin dövmesi.', 'category' => 'lettering'],
            ],
        ]);

        $kullanici = User::factory()->create([
            'name'     => 'Kullanıcı',
            'surname'  => 'Bir',
            'phone'    => '5550000017',
            'email'    => 'kullanici@example.com',
            'password' => '123456',
            'role'     => UserRole::Kullanici,
        ]);

        // ── 5. ŞİRKET ─────────────────────────────────────────────────────
        $company = Company::create([
            'name'             => 'Ink Empire Group',
            'address'          => 'Bağdat Caddesi No:42, Kadıköy, İstanbul',
            'phone'            => '02125550000',
            'email'            => 'info@inkempire.com',
            'about'            => 'İstanbul\'un önde gelen dövme ve sanat stüdyoları zinciri.',
            'website'          => 'https://inkempire.com',
            'is_active'        => true,
            'max_shop_count'   => 5,
            'max_studio_count' => 10,
        ]);

        // ── 6. DÜKKANLAR ──────────────────────────────────────────────────
        $shop1 = Shop::create([
            'company_id'      => $company->id,
            'name'            => 'Ink Empire Kadıköy',
            'location'        => 'Bağdat Caddesi No:42, Kadıköy, İstanbul',
            'about'           => 'Kadıköy\'ün merkezi konumundaki ana dükkanımız.',
            'opening_time'    => '10:00',
            'closing_time'    => '22:00',
            'manager_user_id' => $yonetici->id,
            'is_active'       => true,
        ]);

        $shop2 = Shop::create([
            'company_id'      => $company->id,
            'name'            => 'Ink Empire Beşiktaş',
            'location'        => 'Çırağan Caddesi No:17, Beşiktaş, İstanbul',
            'about'           => 'Beşiktaş\'taki ikinci şubemiz.',
            'opening_time'    => '11:00',
            'closing_time'    => '21:00',
            'manager_user_id' => $yonetici->id,
            'is_active'       => true,
        ]);

        // ── 7. STÜDYOLAR ──────────────────────────────────────────────────
        $studio1 = Studio::create([
            'name'          => 'Ink Empire Kadıköy Tattoo',
            'slug'          => 'ink-empire-kadikoy-tattoo',
            'location'      => 'Bağdat Caddesi No:42/A, Kadıköy, İstanbul',
            'about'         => 'Realistik, traditional ve fine line dövme uzmanlığımızla hizmetinizdeyiz.',
            'opening_time'  => '10:00',
            'closing_time'  => '22:00',
            'owner_user_id' => $admin->id,
            'shop_id'       => $shop1->id,
        ]);

        $studio2 = Studio::create([
            'name'          => 'Ink Empire Beşiktaş Tattoo',
            'slug'          => 'ink-empire-besiktas-tattoo',
            'location'      => 'Çırağan Caddesi No:17/B, Beşiktaş, İstanbul',
            'about'         => 'Watercolor, blackwork ve Japanese stil dövmelerimizle yanınızdayız.',
            'opening_time'  => '11:00',
            'closing_time'  => '21:00',
            'owner_user_id' => $admin->id,
            'shop_id'       => $shop2->id,
        ]);

        // Bağımsız stüdyo — supervisor1 tarafından yönetilir
        $studio3 = Studio::create([
            'name'          => 'Bağımsız Piercing Studio',
            'slug'          => 'bagimsiz-piercing-studio',
            'location'      => 'Nişantaşı, İstanbul',
            'about'         => 'Dükkan bağlantısız bağımsız piercing stüdyosu.',
            'opening_time'  => '12:00',
            'closing_time'  => '20:00',
            'owner_user_id' => $supervisor1->id,
            'shop_id'       => null,
        ]);

        // ── 8. STÜDYO — KULLANICI ATAMALARI ───────────────────────────────
        foreach ([
            [$admin->id,       UserRole::Admin,      'working'],
            [$yonetici->id,    UserRole::Yonetici,   'working'],
            [$supervisor1->id, UserRole::Supervisor, 'working'],
            [$designer1->id,   UserRole::Designer,   'working'],
            [$artist1->id,     UserRole::Artist,     'working'],
            [$artist1b->id,    UserRole::Artist,     'working'],
            [$info1->id,       UserRole::Info,       'working'],
            [$sofor1->id,      UserRole::Sofor,      'transfer'],
            [$calisan->id,     UserRole::Calisan,    'break'],
        ] as [$userId, $role, $workStatus]) {
            $studio1->users()->attach($userId, [
                'role'        => $role->value,
                'work_status' => $workStatus,
                'is_active'   => true,
                'joined_at'   => now()->subMonths(rand(1, 12)),
            ]);
        }

        foreach ([
            [$admin->id,       UserRole::Admin,      'working'],
            [$yonetici->id,    UserRole::Yonetici,   'working'],
            [$supervisor2->id, UserRole::Supervisor, 'working'],
            [$designer2->id,   UserRole::Designer,   'working'],
            [$artist2->id,     UserRole::Artist,     'working'],
            [$artist2b->id,    UserRole::Artist,     'working'],
            [$info2->id,       UserRole::Info,       'working'],
            [$sofor2->id,      UserRole::Sofor,      'working'],
        ] as [$userId, $role, $workStatus]) {
            $studio2->users()->attach($userId, [
                'role'        => $role->value,
                'work_status' => $workStatus,
                'is_active'   => true,
                'joined_at'   => now()->subMonths(rand(1, 8)),
            ]);
        }

        $studio3->users()->attach($supervisor1->id, [
            'role'        => UserRole::Supervisor->value,
            'work_status' => 'working',
            'is_active'   => true,
            'joined_at'   => now()->subMonths(3),
        ]);
        $studio3->users()->attach($artist1->id, [
            'role'        => UserRole::Artist->value,
            'work_status' => 'working',
            'is_active'   => true,
            'joined_at'   => now()->subMonths(2),
        ]);

        // ── 9. RANDEVULAR — STÜDYO 1 ──────────────────────────────────────
        // driver_status:  null | 'picked_up' | 'dropped_off' | 'cancelled'
        // artist_status:  null | 'pending'   | 'accepted'    | 'rejected'
        $appointments1 = [
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Ahmet',
                'last_name'               => 'Yılmaz',
                'phone_country_code'      => '+90',
                'phone_number'            => '5551112233',
                'hotel_name'              => 'Hilton İstanbul',
                'room_number'             => '412',
                'place'                   => 'Hilton Lobby',
                'pax'                     => 1,
                'status'                  => 'confirmed',
                'driver_status'           => null,
                'artist_status'           => 'pending',
                'is_old_customer'         => false,
                'created_by_user_id'      => $info1->id,
                'assigned_driver_user_id' => $sofor1->id,
                'assigned_artist_user_id' => $artist1->id,
                'appointment_at'          => now()->addDays(1)->setTime(14, 0),
                'customer_notes'          => 'Sol kola küçük gül motifi istiyor.',
                'notes'                   => 'İlk randevu, dikkatli olunmalı.',
            ],
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Maria',
                'last_name'               => 'Rossi',
                'phone_country_code'      => '+39',
                'phone_number'            => '3401234567',
                'hotel_name'              => 'Four Seasons Bosphorus',
                'room_number'             => '815',
                'place'                   => 'Hotel Entrance',
                'pax'                     => 2,
                'status'                  => 'confirmed',
                'driver_status'           => 'picked_up',
                'artist_status'           => 'pending',
                'is_old_customer'         => true,
                'created_by_user_id'      => $supervisor1->id,
                'assigned_driver_user_id' => $sofor1->id,
                'assigned_artist_user_id' => $artist1b->id,
                'appointment_at'          => now()->addDays(2)->setTime(11, 30),
                'customer_notes'          => 'Sırt için büyük çiçek kompozisyonu.',
            ],
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'James',
                'last_name'               => 'Wilson',
                'phone_country_code'      => '+44',
                'phone_number'            => '7911123456',
                'hotel_name'              => 'Swissôtel The Bosphorus',
                'room_number'             => '307',
                'place'                   => 'Swissôtel Lobby',
                'pax'                     => 1,
                'status'                  => 'confirmed',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $info1->id,
                'assigned_driver_user_id' => $sofor1->id,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->addDays(3)->setTime(16, 0),
                'customer_notes'          => 'Kol boyunca tribal dövme.',
            ],
            [
                'appointment_type'        => 'standard',
                'first_name'              => 'Sophie',
                'last_name'               => 'Dubois',
                'phone_country_code'      => '+33',
                'phone_number'            => '612345678',
                'hotel_name'              => 'Çırağan Palace Kempinski',
                'room_number'             => '201',
                'place'                   => 'Palace Gate',
                'pax'                     => 3,
                'status'                  => 'pending',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $calisan->id,
                'assigned_driver_user_id' => null,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->addDays(4)->setTime(13, 0),
                'customer_notes'          => 'Bilek dövmesi.',
            ],
            [
                'appointment_type'        => 'designer',
                'first_name'              => 'Hans',
                'last_name'               => 'Müller',
                'phone_country_code'      => '+49',
                'phone_number'            => '15123456789',
                'hotel_name'              => 'Radisson Blu Bosphorus',
                'room_number'             => '512',
                'place'                   => 'Radisson Lobby',
                'pax'                     => 1,
                'status'                  => 'pending',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $designer1->id,
                'assigned_driver_user_id' => null,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->addDays(5)->setTime(15, 30),
                'customer_notes'          => 'Geometrik omuz dövmesi tasarımı.',
            ],
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Lena',
                'last_name'               => 'Schmidt',
                'phone_country_code'      => '+49',
                'phone_number'            => '17698765432',
                'hotel_name'              => 'Hilton İstanbul',
                'room_number'             => '225',
                'place'                   => 'Hilton Lobby',
                'pax'                     => 1,
                'status'                  => 'completed',
                'driver_status'           => 'dropped_off',
                'artist_status'           => 'accepted',
                'is_old_customer'         => true,
                'created_by_user_id'      => $info1->id,
                'assigned_driver_user_id' => $sofor1->id,
                'assigned_artist_user_id' => $artist1->id,
                'appointment_at'          => now()->subDays(2)->setTime(10, 0),
                'notes'                   => 'Müşteri çok memnun kaldı.',
            ],
            [
                'appointment_type'        => 'standard',
                'first_name'              => 'Carlos',
                'last_name'               => 'García',
                'phone_country_code'      => '+34',
                'phone_number'            => '612987654',
                'hotel_name'              => 'InterContinental Istanbul',
                'room_number'             => '718',
                'place'                   => 'IC Lobby',
                'pax'                     => 2,
                'status'                  => 'completed',
                'driver_status'           => 'dropped_off',
                'artist_status'           => 'accepted',
                'is_old_customer'         => false,
                'created_by_user_id'      => $supervisor1->id,
                'assigned_driver_user_id' => $sofor1->id,
                'assigned_artist_user_id' => $artist1b->id,
                'appointment_at'          => now()->subDays(5)->setTime(14, 30),
            ],
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Pierre',
                'last_name'               => 'Dupont',
                'phone_country_code'      => '+33',
                'phone_number'            => '698765432',
                'hotel_name'              => 'Le Méridien Istanbul',
                'room_number'             => '101',
                'place'                   => 'Le Méridien Lobby',
                'pax'                     => 1,
                'status'                  => 'pending',
                'driver_status'           => null,
                'artist_status'           => 'rejected',
                'is_old_customer'         => false,
                'created_by_user_id'      => $info1->id,
                'assigned_driver_user_id' => null,
                'assigned_artist_user_id' => $artist1->id,
                'appointment_at'          => now()->addDays(6)->setTime(10, 0),
                'notes'                   => 'Artist müsait değil, yeniden atanacak.',
            ],
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Anna',
                'last_name'               => 'Kowalski',
                'phone_country_code'      => '+48',
                'phone_number'            => '501234567',
                'hotel_name'              => 'Wyndham Grand Istanbul',
                'room_number'             => '334',
                'place'                   => 'Wyndham Lobby',
                'pax'                     => 1,
                'status'                  => 'cancelled',
                'driver_status'           => 'cancelled',
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $info1->id,
                'assigned_driver_user_id' => $sofor1->id,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->subDay()->setTime(12, 0),
                'notes'                   => 'Müşteri uçuşunu kaçırdı.',
            ],
        ];

        foreach ($appointments1 as $data) {
            Appointment::create(array_merge($data, ['studio_id' => $studio1->id]));
        }

        // ── 10. RANDEVULAR — STÜDYO 2 ─────────────────────────────────────
        $appointments2 = [
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Emily',
                'last_name'               => 'Johnson',
                'phone_country_code'      => '+1',
                'phone_number'            => '2125551234',
                'hotel_name'              => 'The Ritz-Carlton Istanbul',
                'room_number'             => '615',
                'place'                   => 'Ritz Lobby',
                'pax'                     => 1,
                'status'                  => 'confirmed',
                'driver_status'           => null,
                'artist_status'           => 'pending',
                'is_old_customer'         => false,
                'created_by_user_id'      => $info2->id,
                'assigned_driver_user_id' => $sofor2->id,
                'assigned_artist_user_id' => $artist2->id,
                'appointment_at'          => now()->addDays(1)->setTime(13, 0),
                'customer_notes'          => 'Boyun arkasına küçük kelebek.',
            ],
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Yuki',
                'last_name'               => 'Tanaka',
                'phone_country_code'      => '+81',
                'phone_number'            => '9012345678',
                'hotel_name'              => 'Conrad Istanbul Bosphorus',
                'room_number'             => '422',
                'place'                   => 'Conrad Lobby',
                'pax'                     => 2,
                'status'                  => 'confirmed',
                'driver_status'           => 'picked_up',
                'artist_status'           => 'pending',
                'is_old_customer'         => true,
                'created_by_user_id'      => $supervisor2->id,
                'assigned_driver_user_id' => $sofor2->id,
                'assigned_artist_user_id' => $artist2b->id,
                'appointment_at'          => now()->addDays(2)->setTime(10, 0),
                'customer_notes'          => 'Koi balığı Japanese stil, tam kol.',
            ],
            [
                'appointment_type'        => 'standard',
                'first_name'              => 'Lucas',
                'last_name'               => 'Oliveira',
                'phone_country_code'      => '+55',
                'phone_number'            => '11987654321',
                'hotel_name'              => 'Marriott İstanbul',
                'room_number'             => '309',
                'place'                   => 'Marriott Entrance',
                'pax'                     => 1,
                'status'                  => 'pending',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $info2->id,
                'assigned_driver_user_id' => null,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->addDays(3)->setTime(17, 0),
            ],
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Emma',
                'last_name'               => 'Larsson',
                'phone_country_code'      => '+46',
                'phone_number'            => '701234567',
                'hotel_name'              => 'Shangri-La Istanbul',
                'room_number'             => '511',
                'place'                   => 'Shangri-La Lobby',
                'pax'                     => 1,
                'status'                  => 'completed',
                'driver_status'           => 'dropped_off',
                'artist_status'           => 'accepted',
                'is_old_customer'         => false,
                'created_by_user_id'      => $supervisor2->id,
                'assigned_driver_user_id' => $sofor2->id,
                'assigned_artist_user_id' => $artist2->id,
                'appointment_at'          => now()->subDays(3)->setTime(11, 0),
                'notes'                   => 'Fine line çiçek, çok detaylı çalışma.',
            ],
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Mohammed',
                'last_name'               => 'Al-Rashid',
                'phone_country_code'      => '+971',
                'phone_number'            => '501234567',
                'hotel_name'              => 'Waldorf Astoria Istanbul',
                'room_number'             => '722',
                'place'                   => 'Waldorf Lobby',
                'pax'                     => 3,
                'status'                  => 'cancelled',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $supervisor2->id,
                'assigned_driver_user_id' => null,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->subDays(1)->setTime(15, 0),
                'notes'                   => 'Müşteri planını değiştirdi.',
            ],
        ];

        foreach ($appointments2 as $data) {
            Appointment::create(array_merge($data, ['studio_id' => $studio2->id]));
        }

        // ── 11. RANDEVULAR — STÜDYO 3 (Bağımsız) ─────────────────────────
        $appointments3 = [
            [
                'appointment_type'        => 'standard',
                'first_name'              => 'Isabella',
                'last_name'               => 'Ferrari',
                'phone_country_code'      => '+39',
                'phone_number'            => '3351234567',
                'hotel_name'              => 'Park Hyatt Istanbul',
                'room_number'             => '118',
                'place'                   => 'Park Hyatt Lobby',
                'pax'                     => 1,
                'status'                  => 'confirmed',
                'driver_status'           => null,
                'artist_status'           => 'pending',
                'is_old_customer'         => false,
                'created_by_user_id'      => $supervisor1->id,
                'assigned_driver_user_id' => null,
                'assigned_artist_user_id' => $artist1->id,
                'appointment_at'          => now()->addDays(2)->setTime(14, 0),
                'customer_notes'          => 'Kulak piercing kombinasyonu.',
            ],
            [
                'appointment_type'        => 'standard',
                'first_name'              => 'Oliver',
                'last_name'               => 'Brown',
                'phone_country_code'      => '+44',
                'phone_number'            => '7700900123',
                'hotel_name'              => 'Soho House Istanbul',
                'room_number'             => '205',
                'place'                   => 'Soho House Entrance',
                'pax'                     => 2,
                'status'                  => 'pending',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => true,
                'created_by_user_id'      => $supervisor1->id,
                'assigned_driver_user_id' => null,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->addDays(6)->setTime(16, 30),
            ],
        ];

        foreach ($appointments3 as $data) {
            Appointment::create(array_merge($data, ['studio_id' => $studio3->id]));
        }
    }
}
