<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AppointmentRequest;
use App\Models\Company;
use App\Models\Studio;
use App\Models\StudioStaffInvitation;
use App\Models\PushNotification;
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
            'name'             => 'Tasarımcı',
            'surname'          => 'Bir',
            'username'         => 'designer1',
            'phone'            => '5550000004',
            'email'            => 'designer1@example.com',
            'password'         => '123456',
            'role'             => UserRole::Designer,
            'bio'              => 'Geometrik ve minimalist dövme tasarımları.',
            'experience_years' => 5,
            'specializations'  => ['minimal', 'fine_line', 'dotwork'],
            'rating'           => 4.8,
            'portfolio'        => [
                ['title' => 'Geometrik Mandala', 'image_path' => null, 'description' => 'İnce çizgilerle merkez mandala tasarımı.', 'category' => 'geometric'],
                ['title' => 'Minimalist Çiçek',  'image_path' => null, 'description' => 'Tek çizgi tekniğiyle çiçek tasarımı.',    'category' => 'minimalist'],
            ],
        ]);

        $artist1 = User::factory()->create([
            'name'             => 'Artist',
            'surname'          => 'Bir',
            'username'         => 'artist1',
            'phone'            => '5550000005',
            'email'            => 'artist1@example.com',
            'password'         => '123456',
            'role'             => UserRole::Artist,
            'bio'              => 'Realistik portre uzmanı. 8 yıllık deneyim.',
            'experience_years' => 8,
            'specializations'  => ['realism', 'blackwork', 'cover_up'],
            'rating'           => 4.9,
            'portfolio'        => [
                ['title' => 'Realistik Aslan', 'image_path' => null, 'description' => 'Siyah-gri tekniğiyle omuz dövmesi.',  'category' => 'realism'],
                ['title' => 'Tribal Full Kol', 'image_path' => null, 'description' => 'Full sleeve tribal kompozisyon.',     'category' => 'tribal'],
                ['title' => 'Gül Buketi',      'image_path' => null, 'description' => 'Ön kol için renk çalışması.',        'category' => 'floral'],
            ],
        ]);

        $artist1b = User::factory()->create([
            'name'             => 'Artist',
            'surname'          => 'Bir-B',
            'username'         => 'artist1b',
            'phone'            => '5550000006',
            'email'            => 'artist1b@example.com',
            'password'         => '123456',
            'role'             => UserRole::Artist,
            'bio'              => 'Traditional ve Neo-traditional stil.',
            'experience_years' => 6,
            'specializations'  => ['old_school', 'color', 'japanese'],
            'rating'           => 4.7,
            'portfolio'        => [
                ['title' => 'Neo-Traditional Kartal', 'image_path' => null, 'description' => 'Kalın konturlu neo-traditional stil.', 'category' => 'neo-traditional'],
                ['title' => 'American Traditional',   'image_path' => null, 'description' => 'Klasik sailor jerry tarzı gemi.',      'category' => 'traditional'],
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
            'name'             => 'Tasarımcı',
            'surname'          => 'İki',
            'username'         => 'designer2',
            'phone'            => '5550000010',
            'email'            => 'designer2@example.com',
            'password'         => '123456',
            'role'             => UserRole::Designer,
            'bio'              => 'Watercolor ve abstract dövme tasarımları.',
            'experience_years' => 4,
            'specializations'  => ['color', 'minimal', 'fine_line'],
            'rating'           => 4.6,
            'portfolio'        => [
                ['title' => 'Watercolor Kelebek', 'image_path' => null, 'description' => 'Sıvı boya etkisi, canlı renkler.',        'category' => 'watercolor'],
                ['title' => 'Abstract Fırtına',   'image_path' => null, 'description' => 'Soyut çizgilerle fırtına kompozisyonu.', 'category' => 'abstract'],
            ],
        ]);

        $artist2 = User::factory()->create([
            'name'             => 'Artist',
            'surname'          => 'İki',
            'username'         => 'artist2',
            'phone'            => '5550000011',
            'email'            => 'artist2@example.com',
            'password'         => '123456',
            'role'             => UserRole::Artist,
            'bio'              => 'Fine line ve blackwork uzmanı.',
            'experience_years' => 7,
            'specializations'  => ['fine_line', 'blackwork', 'dotwork'],
            'rating'           => 4.5,
            'portfolio'        => [
                ['title' => 'Fine Line Botanik',  'image_path' => null, 'description' => 'İnce çizgiyle botanik yaprak serisi.', 'category' => 'fine-line'],
                ['title' => 'Blackwork Geometri', 'image_path' => null, 'description' => 'Tam siyah geometrik pattern.',         'category' => 'blackwork'],
            ],
        ]);

        $artist2b = User::factory()->create([
            'name'             => 'Artist',
            'surname'          => 'İki-B',
            'username'         => 'artist2b',
            'phone'            => '5550000012',
            'email'            => 'artist2b@example.com',
            'password'         => '123456',
            'role'             => UserRole::Artist,
            'bio'              => 'Japanese ve Irezumi stil.',
            'experience_years' => 10,
            'specializations'  => ['japanese', 'color', 'cover_up'],
            'rating'           => 4.8,
            'portfolio'        => [
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

        $supervisor3 = User::factory()->create([
            'name'     => 'Süpervizör',
            'surname'  => 'Üç',
            'phone'    => '5550000025',
            'email'    => 'supervisor3@example.com',
            'password' => '123456',
            'role'     => UserRole::Supervisor,
            'bio'      => 'Stüdyo 3 yöneticisi ve süpervizörü.',
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

        $independentArtist = User::factory()->create([
            'name'      => 'Bağımsız',
            'surname'   => 'Artist',
            'phone'     => '5550000016',
            'email'     => 'bagimsiz.artist@example.com',
            'password'  => '123456',
            'role'      => UserRole::KullaniciRol,
            'requested_staff_role' => UserRole::Artist,
            'bio'       => 'Bağımsız çalışan dövme sanatçısı.',
            'rating'    => 4.3,
            'specializations' => ['minimal', 'fine_line', 'blackwork'],
            'portfolio' => [
                ['title' => 'Minimalist Ay',  'image_path' => null, 'description' => 'Tek çizgi ay serisi.',     'category' => 'minimalist'],
                ['title' => 'Script Yazı',    'image_path' => null, 'description' => 'El yazısı metin dövmesi.', 'category' => 'lettering'],
            ],
        ]);

        $independentDesigner = User::factory()->create([
            'name'      => 'Bağımsız',
            'surname'   => 'Tasarımcı',
            'phone'     => '5550000018',
            'email'     => 'bagimsiz.designer@example.com',
            'password'  => '123456',
            'role'      => UserRole::KullaniciRol,
            'requested_staff_role' => UserRole::Designer,
            'bio'       => 'Bağımsız çalışan dövme tasarımcısı.',
            'rating'    => 4.5,
            'specializations' => ['japanese', 'color', 'cover_up'],
        ]);

        foreach ([
            ['Şoför', 'Aday', 'bagimsiz.sofor@example.com', UserRole::Sofor],
            ['Info', 'Aday', 'bagimsiz.info@example.com', UserRole::Info],
            ['Çalışan', 'Aday', 'bagimsiz.calisan@example.com', UserRole::Calisan],
            ['Süpervizör', 'Aday', 'bagimsiz.supervisor@example.com', UserRole::Supervisor],
        ] as [$name, $surname, $email, $requestedRole]) {
            User::factory()->create([
                'name'                 => $name,
                'surname'              => $surname,
                'phone'                => '5550000099',
                'email'                => $email,
                'password'             => '123456',
                'role'                 => UserRole::KullaniciRol,
                'requested_staff_role' => $requestedRole,
            ]);
        }

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
            'manager_user_id'  => $yonetici->id,
            'name'             => 'Ink Empire Group',
            'address'          => 'Bağdat Caddesi No:42, Kadıköy, İstanbul',
            'phone'            => '02125550000',
            'email'            => 'info@inkempire.com',
            'about'            => 'İstanbul\'un önde gelen dövme ve sanat stüdyoları zinciri.',
            'website'          => 'https://inkempire.com',
            'is_active'        => true,
            'max_studio_count' => 10,
        ]);

        // ── 6. STÜDYOLAR ──────────────────────────────────────────────────
        $studio1 = Studio::create([
            'name'          => 'Ink Empire Kadıköy Tattoo',
            'slug'          => 'ink-empire-kadikoy-tattoo',
            'location'      => 'Bağdat Caddesi No:42/A, Kadıköy, İstanbul',
            'about'         => 'Realistik, traditional ve fine line dövme uzmanlığımızla hizmetinizdeyiz.',
            'opening_time'  => '10:00',
            'closing_time'  => '22:00',
            'owner_user_id' => $admin->id,
            'company_id'    => $company->id,
            'gallery_images' => [
                'https://images.unsplash.com/photo-1611501275019-9b5cda994e8d?auto=format&fit=crop&w=1200&q=80',
                'https://images.unsplash.com/photo-1590246814883-6b4f7a0f95da?auto=format&fit=crop&w=1200&q=80',
                'https://images.unsplash.com/photo-1598371839696-5c5bb00bdc28?auto=format&fit=crop&w=1200&q=80',
            ],
        ]);

        $studio2 = Studio::create([
            'name'          => 'Ink Empire Beşiktaş Tattoo',
            'slug'          => 'ink-empire-besiktas-tattoo',
            'location'      => 'Çırağan Caddesi No:17/B, Beşiktaş, İstanbul',
            'about'         => 'Watercolor, blackwork ve Japanese stil dövmelerimizle yanınızdayız.',
            'opening_time'  => '11:00',
            'closing_time'  => '21:00',
            'owner_user_id' => $admin->id,
            'company_id'    => $company->id,
            'gallery_images' => [
                'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=1200&q=80',
                'https://images.unsplash.com/photo-1542727365-19732a80dcfd?auto=format&fit=crop&w=1200&q=80',
            ],
        ]);

        $studio3 = Studio::create([
            'name'          => 'Bağımsız Piercing Studio',
            'slug'          => 'bagimsiz-piercing-studio',
            'location'      => 'Nişantaşı, İstanbul',
            'about'         => 'Piercing hizmetleri sunan şirket stüdyosu.',
            'opening_time'  => '12:00',
            'closing_time'  => '20:00',
            'owner_user_id' => $supervisor3->id,
            'company_id'    => $company->id,
            'gallery_images' => [
                'https://images.unsplash.com/photo-1605812860427-4024433a70fd?auto=format&fit=crop&w=1200&q=80',
                'https://images.unsplash.com/photo-1605497788044-5a32c7078486?auto=format&fit=crop&w=1200&q=80',
            ],
        ]);

        // ── 7. STÜDYO — KULLANICI ATAMALARI ───────────────────────────────
        foreach ([
            [$admin->id,       UserRole::Admin,      'working'],
            [$yonetici->id,    UserRole::Yonetici,   'working'],
            [$supervisor1->id, UserRole::Supervisor, 'working'],
            [$designer1->id,   UserRole::Designer,   'working'],
            [$artist1->id,     UserRole::Artist,     'working'],
            [$info1->id,       UserRole::Info,       'working'],
            [$sofor1->id,      UserRole::Sofor,      'transfer'],
            [$calisan->id,     UserRole::Calisan,    'break'],
        ] as [$userId, $role, $workStatus]) {
            $studio1->users()->attach($userId, [
                'role'        => $role->value,
                'work_status' => $workStatus,
                'commission_rate' => $this->commissionRate($role),
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
            [$info2->id,       UserRole::Info,       'working'],
            [$sofor2->id,      UserRole::Sofor,      'working'],
        ] as [$userId, $role, $workStatus]) {
            $studio2->users()->attach($userId, [
                'role'        => $role->value,
                'work_status' => $workStatus,
                'commission_rate' => $this->commissionRate($role),
                'is_active'   => true,
                'joined_at'   => now()->subMonths(rand(1, 8)),
            ]);
        }

        $studio3->users()->attach($supervisor3->id, [
            'role'        => UserRole::Supervisor->value,
            'work_status' => 'working',
            'commission_rate' => $this->commissionRate(UserRole::Supervisor),
            'is_active'   => true,
            'joined_at'   => now()->subMonths(3),
        ]);
        $studio3->users()->attach($artist1->id, [
            'role'        => UserRole::Artist->value,
            'work_status' => 'working',
            'commission_rate' => $this->commissionRate(UserRole::Artist),
            'is_active'   => true,
            'joined_at'   => now()->subMonths(2),
        ]);

        // Bağımsız profesyonel davet testleri:
        // bagimsiz.artist@example.com / 123456 ile giriş yapıp Bildirimler'den yanıtlanabilir.
        $artistInvitation = StudioStaffInvitation::create([
            'studio_id'          => $studio1->id,
            'user_id'            => $independentArtist->id,
            'invited_by_user_id' => $supervisor1->id,
            'role'               => UserRole::Artist->value,
            'status'             => 'pending',
        ]);

        PushNotification::create([
            'user_id' => $independentArtist->id,
            'type'    => 'studio_staff_invitation',
            'title'   => 'Yeni çalışanlık daveti',
            'body'    => $studio1->name.' sizi Artist olarak ekibine davet etti.',
            'data'    => [
                'invitation_id' => (string) $artistInvitation->id,
                'studio_id'     => (string) $studio1->id,
                'role'          => UserRole::Artist->value,
            ],
        ]);

        // bagimsiz.designer@example.com / 123456 ile tasarımcı daveti test edilebilir.
        $designerInvitation = StudioStaffInvitation::create([
            'studio_id'          => $studio2->id,
            'user_id'            => $independentDesigner->id,
            'invited_by_user_id' => $supervisor2->id,
            'role'               => UserRole::Designer->value,
            'status'             => 'pending',
        ]);

        PushNotification::create([
            'user_id' => $independentDesigner->id,
            'type'    => 'studio_staff_invitation',
            'title'   => 'Yeni çalışanlık daveti',
            'body'    => $studio2->name.' sizi Tasarımcı olarak ekibine davet etti.',
            'data'    => [
                'invitation_id' => (string) $designerInvitation->id,
                'studio_id'     => (string) $studio2->id,
                'role'          => UserRole::Designer->value,
            ],
        ]);

        // ── 9. RANDEVULAR — STÜDYO 1 ──────────────────────────────────────
        // driver_status:  null | 'picked_up' | 'dropped_off' | 'cancelled'
        // Randevuda bekleme yoktur; bekleme/kabul/red sadece talep akışındadır.
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
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $info1->id,
                'pickup_required'         => true,
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
                'artist_status'           => null,
                'is_old_customer'         => true,
                'created_by_user_id'      => $supervisor3->id,
                'pickup_required'         => true,
                'assigned_artist_user_id' => $artist1->id,
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
                'pickup_required'         => true,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->addDays(3)->setTime(16, 0),
                'customer_notes'          => 'Kol boyunca tribal dövme.',
            ],
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Sophie',
                'last_name'               => 'Dubois',
                'phone_country_code'      => '+33',
                'phone_number'            => '612345678',
                'hotel_name'              => 'Çırağan Palace Kempinski',
                'room_number'             => '201',
                'place'                   => 'Palace Gate',
                'pax'                     => 3,
                'status'                  => 'confirmed',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $calisan->id,
                'pickup_required'         => false,
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
                'status'                  => 'confirmed',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $designer1->id,
                'pickup_required'         => false,
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
                'artist_status'           => null,
                'is_old_customer'         => true,
                'created_by_user_id'      => $info1->id,
                'pickup_required'         => true,
                'assigned_artist_user_id' => $artist1->id,
                'appointment_at'          => now()->subDays(2)->setTime(10, 0),
                'notes'                   => 'Müşteri çok memnun kaldı.',
            ],
            [
                'appointment_type'        => 'tattoo',
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
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $supervisor3->id,
                'pickup_required'         => true,
                'assigned_artist_user_id' => $artist1->id,
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
                'status'                  => 'confirmed',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $info1->id,
                'pickup_required'         => false,
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
                'pickup_required'         => true,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->subDay()->setTime(12, 0),
                'notes'                   => 'Müşteri uçuşunu kaçırdı.',
            ],
        ];

        foreach ($appointments1 as $data) {
            $data['price'] ??= fake()->numberBetween(2500, 18000);
            $data['pickup_required'] ??= false;
            $data['tattoo_image_paths'] ??= [
                'https://images.unsplash.com/photo-1598371839696-5c5bb00bdc28?auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1611501275019-9b5cda994e8d?auto=format&fit=crop&w=900&q=80',
            ];
            if ($data['status'] === 'completed' && $data['appointment_type'] === 'tattoo') {
                $data['completed_tattoo_image_path'] ??= 'https://images.unsplash.com/photo-1598371839696-5c5bb00bdc28?auto=format&fit=crop&w=900&q=80';
            }
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
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $info2->id,
                'pickup_required'         => true,
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
                'artist_status'           => null,
                'is_old_customer'         => true,
                'created_by_user_id'      => $supervisor2->id,
                'pickup_required'         => true,
                'assigned_artist_user_id' => $artist2->id,
                'appointment_at'          => now()->addDays(2)->setTime(10, 0),
                'customer_notes'          => 'Koi balığı Japanese stil, tam kol.',
            ],
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Lucas',
                'last_name'               => 'Oliveira',
                'phone_country_code'      => '+55',
                'phone_number'            => '11987654321',
                'hotel_name'              => 'Marriott İstanbul',
                'room_number'             => '309',
                'place'                   => 'Marriott Entrance',
                'pax'                     => 1,
                'status'                  => 'confirmed',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $info2->id,
                'pickup_required'         => false,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->addDays(3)->setTime(17, 0),
            ],
            [
                'appointment_type'        => 'designer',
                'first_name'              => 'Mila',
                'last_name'               => 'Petrova',
                'phone_country_code'      => '+7',
                'phone_number'            => '9161234567',
                'hotel_name'              => 'Mandarin Oriental Bosphorus',
                'room_number'             => '604',
                'place'                   => 'Mandarin Lobby',
                'pax'                     => 1,
                'status'                  => 'confirmed',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $designer2->id,
                'pickup_required'         => false,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->addDays(4)->setTime(12, 30),
                'customer_notes'          => 'Sadece Studio 2 designer hesabında görünmeli.',
                'notes'                   => 'Designer test randevusu.',
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
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $supervisor2->id,
                'pickup_required'         => true,
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
                'pickup_required'         => false,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->subDays(1)->setTime(15, 0),
                'notes'                   => 'Müşteri planını değiştirdi.',
            ],
        ];

        foreach ($appointments2 as $data) {
            $data['price'] ??= fake()->numberBetween(3000, 22000);
            $data['pickup_required'] ??= false;
            $data['tattoo_image_paths'] ??= [
                'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1590246814883-6b4f7a0f95da?auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1542727365-19732a80dcfd?auto=format&fit=crop&w=900&q=80',
            ];
            if ($data['status'] === 'completed' && $data['appointment_type'] === 'tattoo') {
                $data['completed_tattoo_image_path'] ??= 'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=900&q=80';
            }
            Appointment::create(array_merge($data, ['studio_id' => $studio2->id]));
        }

        // ── 11. RANDEVULAR — STÜDYO 3 (Bağımsız) ─────────────────────────
        $appointments3 = [
            [
                'appointment_type'        => 'tattoo',
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
                'artist_status'           => null,
                'is_old_customer'         => false,
                'created_by_user_id'      => $supervisor1->id,
                'pickup_required'         => false,
                'assigned_artist_user_id' => $artist1->id,
                'appointment_at'          => now()->addDays(2)->setTime(14, 0),
                'customer_notes'          => 'Kulak piercing kombinasyonu.',
            ],
            [
                'appointment_type'        => 'tattoo',
                'first_name'              => 'Oliver',
                'last_name'               => 'Brown',
                'phone_country_code'      => '+44',
                'phone_number'            => '7700900123',
                'hotel_name'              => 'Soho House Istanbul',
                'room_number'             => '205',
                'place'                   => 'Soho House Entrance',
                'pax'                     => 2,
                'status'                  => 'confirmed',
                'driver_status'           => null,
                'artist_status'           => null,
                'is_old_customer'         => true,
                'created_by_user_id'      => $supervisor1->id,
                'pickup_required'         => false,
                'assigned_artist_user_id' => null,
                'appointment_at'          => now()->addDays(6)->setTime(16, 30),
            ],
        ];

        foreach ($appointments3 as $data) {
            $data['price'] ??= fake()->numberBetween(2000, 14000);
            $data['pickup_required'] ??= false;
            $data['tattoo_image_paths'] ??= [];
            if ($data['status'] === 'completed' && $data['appointment_type'] === 'tattoo') {
                $data['completed_tattoo_image_path'] ??= 'https://images.unsplash.com/photo-1598371839696-5c5bb00bdc28?auto=format&fit=crop&w=900&q=80';
            }
            Appointment::create(array_merge($data, ['studio_id' => $studio3->id]));
        }

        // ── 12. RANDEVU TALEPLERİ — kabul edilince randevuya dönüşür ─────
        AppointmentRequest::create([
            'requester_user_id'  => $kullanici->id,
            'target_user_id'     => $designer1->id,
            'studio_id'          => $studio1->id,
            'request_type'       => 'designer',
            'requested_at'       => now()->addDays(2)->setTime(15, 0),
            'notes'              => 'Ön kol için geometrik tasarım talebi.',
            'first_name'         => 'Deniz',
            'last_name'          => 'Kaya',
            'phone_country_code' => '+90',
            'phone_number'       => '5550001122',
            'hotel_name'         => 'Moda Hotel',
            'room_number'        => '304',
            'place'              => 'Moda Hotel',
            'pax'                => 1,
            'price'              => 4500,
            'image_path'         => 'https://images.unsplash.com/photo-1598371839696-5c5bb00bdc28?auto=format&fit=crop&w=1200&q=80',
            'tattoo_image_paths' => [
                'https://images.unsplash.com/photo-1611501275019-9b5cda994e8d?auto=format&fit=crop&w=900&q=80',
            ],
            'pickup_required'    => true,
            'status'             => 'pending',
        ]);

        AppointmentRequest::create([
            'requester_user_id'  => $supervisor1->id,
            'target_user_id'     => $artist1->id,
            'studio_id'          => $studio1->id,
            'request_type'       => 'tattoo',
            'requested_at'       => now()->addDays(3)->setTime(12, 0),
            'notes'              => 'Studio 1 kendi dövmecisine test dövme talebi.',
            'first_name'         => 'Studio',
            'last_name'          => 'Müşterisi',
            'phone_country_code' => '+49',
            'phone_number'       => '1510002233',
            'hotel_name'         => 'Kadıköy Ink Lobby',
            'room_number'        => 'A1',
            'place'              => 'Kadıköy Ink Lobby',
            'pax'                => 2,
            'price'              => 12000,
            'image_path'         => 'https://images.unsplash.com/photo-1611501275019-9b5cda994e8d?auto=format&fit=crop&w=1200&q=80',
            'tattoo_image_paths' => [
                'https://images.unsplash.com/photo-1590246814883-6b4f7a0f95da?auto=format&fit=crop&w=900&q=80',
                'https://images.unsplash.com/photo-1598371839696-5c5bb00bdc28?auto=format&fit=crop&w=900&q=80',
            ],
            'pickup_required'    => true,
            'status'             => 'pending',
        ]);

        AppointmentRequest::create([
            'requester_user_id'  => $kullanici->id,
            'target_user_id'     => null,
            'studio_id'          => $studio2->id,
            'request_type'       => 'tattoo',
            'requested_at'       => now()->addDays(4)->setTime(16, 0),
            'notes'              => 'Direkt stüdyoya gönderilmiş test dövme talebi.',
            'first_name'         => 'Maya',
            'last_name'          => 'Stone',
            'phone_country_code' => '+44',
            'phone_number'       => '7700001122',
            'hotel_name'         => 'Beşiktaş Palace',
            'room_number'        => '812',
            'place'              => 'Beşiktaş Palace',
            'pax'                => 1,
            'price'              => 8500,
            'image_path'         => 'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=1200&q=80',
            'tattoo_image_paths' => [
                'https://images.unsplash.com/photo-1542727365-19732a80dcfd?auto=format&fit=crop&w=900&q=80',
            ],
            'pickup_required'    => false,
            'status'             => 'pending',
        ]);

        AppointmentRequest::create([
            'requester_user_id'  => $kullanici->id,
            'target_user_id'     => $artist2->id,
            'studio_id'          => $studio2->id,
            'request_type'       => 'tattoo',
            'requested_at'       => now()->addDays(2)->setTime(18, 0),
            'notes'              => 'Artist 2 için fine line test talebi.',
            'first_name'         => 'Test',
            'last_name'          => 'Artistiki',
            'phone_country_code' => '+90',
            'phone_number'       => '5552223344',
            'hotel_name'         => 'Artist 2 Test Hotel',
            'room_number'        => '220',
            'place'              => 'Artist 2 Test Hotel',
            'pax'                => 1,
            'price'              => 6500,
            'image_path'         => 'https://images.unsplash.com/photo-1590246814883-6b4f7a0f95da?auto=format&fit=crop&w=1200&q=80',
            'tattoo_image_paths' => [
                'https://images.unsplash.com/photo-1611501275019-9b5cda994e8d?auto=format&fit=crop&w=900&q=80',
            ],
            'pickup_required'    => true,
            'status'             => 'pending',
        ]);

        AppointmentRequest::create([
            'requester_user_id'  => $kullanici->id,
            'target_user_id'     => $independentArtist->id,
            'studio_id'          => null,
            'request_type'       => 'tattoo',
            'requested_at'       => now()->addDays(5)->setTime(14, 30),
            'notes'              => 'Freelancer hesabına gelen test talebi. Kabul edilince randevu oluşmalı.',
            'first_name'         => 'Freelancer',
            'last_name'          => 'Test',
            'phone_country_code' => '+90',
            'phone_number'       => '5554447788',
            'hotel_name'         => 'Freelancer Test Hotel',
            'room_number'        => '510',
            'place'              => 'Freelancer Test Hotel',
            'pax'                => 1,
            'price'              => 9000,
            'image_path'         => 'https://images.unsplash.com/photo-1562962230-16e4623d36e6?auto=format&fit=crop&w=1200&q=80',
            'tattoo_image_paths' => [
                'https://images.unsplash.com/photo-1598371839696-5c5bb00bdc28?auto=format&fit=crop&w=900&q=80',
            ],
            'pickup_required'    => false,
            'status'             => 'pending',
        ]);
    }

    private function commissionRate(UserRole $role): int
    {
        return match ($role) {
            UserRole::Supervisor => 12,
            UserRole::Artist => 40,
            UserRole::Designer => 25,
            UserRole::Info => 10,
            UserRole::Sofor => 5,
            UserRole::Calisan => 8,
            default => 0,
        };
    }
}
