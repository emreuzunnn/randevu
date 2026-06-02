<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentRequestController;
use App\Http\Controllers\Api\AppointmentSlipOcrController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ContentModerationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\PushTokenController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\StudioController;
use App\Http\Controllers\Api\StudioManagerController;
use App\Http\Controllers\Api\StudioStaffController;
use App\Http\Controllers\Api\StudioStaffInvitationController;
use App\Http\Controllers\Api\UserDirectoryController;
use App\Http\Controllers\Api\UserProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Herkese Açık Endpoint'ler (Auth gerektirmez)
|--------------------------------------------------------------------------
*/

// Görsel yüklenip AI ile randevu fişindeki alanları okuyarak JSON döndürür.
Route::post('/ocr/appointment-slip', AppointmentSlipOcrController::class);

// Kullanıcı girişi yapar ve sonraki API isteklerinde kullanılacak bearer token üretir.
Route::post('/login', [AuthController::class, 'login']);

// Kayıt ekranındaki güvenli rol ve uzmanlık seçenekleri.
Route::get('/register/options', [AuthController::class, 'registrationOptions']);

// Yeni kullanıcı kaydı: normal kullanıcı, bağımsız artist veya bağımsız tasarımcı oluşturur.
Route::post('/register', [AuthController::class, 'register']);

// Stüdyo keşif sayfası için herkese açık listeler.
Route::get('/public/studios', [PublicController::class, 'studios']);
Route::get('/public/studios/{studio}', [PublicController::class, 'studio']);
Route::get('/public/studios/{studio}/reviews', [PublicController::class, 'studioReviews']);
Route::get('/public/artists', [PublicController::class, 'artists']);
Route::get('/public/artists/{user}', [PublicController::class, 'artist']);
Route::get('/public/artists/{user}/availability', [PublicController::class, 'artistAvailability']);
Route::get('/public/artists/{user}/reviews', [PublicController::class, 'artistReviews']);

/*
|--------------------------------------------------------------------------
| Kimlik Doğrulaması Gereken Genel API'ler
|--------------------------------------------------------------------------
*/
Route::middleware(['api.auth'])->group(function (): void {
    // Kullanıcıya göre filtrelenmiş dashboard özeti ve günlük randevu verilerini döndürür.
    Route::get('/home', [DashboardController::class, 'index']);
    // Dönem bazlı raporlama. Sorgu parametreleri: period (daily|weekly|monthly|quarterly), studio_id (opsiyonel)
    Route::get('/reports', [ReportController::class, 'index']);

    // Profil
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me', [AuthController::class, 'updateProfile']);
    Route::delete('/me', [AuthController::class, 'destroyAccount']);
    Route::get('/profile', [AuthController::class, 'me']);
    Route::patch('/profile', [AuthController::class, 'updateProfile']);

    // İçerik ve kullanıcı şikayeti
    Route::post('/users/{user}/report', [ContentModerationController::class, 'reportUser']);
    Route::post('/reviews/{review}/report', [ContentModerationController::class, 'reportReview']);

    // Push bildirim token kaydı ve bildirim merkezi
    Route::post('/push-tokens', [PushTokenController::class, 'store']);
    Route::delete('/push-tokens', [PushTokenController::class, 'destroy']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/test', [NotificationController::class, 'test']);
    Route::post('/notifications/test-broadcast', [NotificationController::class, 'broadcastTest']);
    Route::patch('/notifications/{pushNotification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{pushNotification}', [NotificationController::class, 'destroy']);

    // Freelancer çalışanlık davetleri: kullanıcı kabul ettiğinde stüdyo üyeliği açılır.
    Route::get('/staff-invitations', [StudioStaffInvitationController::class, 'index']);
    Route::post('/users/{user}/staff-invitations', [StudioStaffInvitationController::class, 'store']);
    Route::patch('/staff-invitations/{studioStaffInvitation}/accept', [StudioStaffInvitationController::class, 'accept']);
    Route::patch('/staff-invitations/{studioStaffInvitation}/reject', [StudioStaffInvitationController::class, 'reject']);

    // Portfolyo — tüm roller erişebilir; şoför/kullanıcı rollerinde has_portfolio: false döner
    Route::get('/me/portfolio', [AuthController::class, 'getPortfolio']);
    Route::patch('/me/portfolio', [AuthController::class, 'updatePortfolio']);
    Route::post('/me/portfolio/items', [AuthController::class, 'addPortfolioItem']);
    Route::delete('/me/portfolio/items/{index}', [AuthController::class, 'removePortfolioItem']);

    // Avatar: yükle/güncelle (multipart/form-data, alan adı: avatar) | sil
    Route::post('/me/avatar', [MediaController::class, 'uploadAvatar']);
    Route::delete('/me/avatar', [MediaController::class, 'deleteAvatar']);

    // Portfolio görsel yükleme — URL döndürür, portfolyoya kaydetmez
    Route::post('/me/portfolio/upload', [MediaController::class, 'uploadPortfolioImage']);

    // Dropdown kaynakları — {user}/{studio} parametreli route'lardan ÖNCE tanımlanmalı
    Route::get('/studios/options', [UserDirectoryController::class, 'studioOptions']);
    Route::get('/users/options', [UserDirectoryController::class, 'userOptions']);

    // Herhangi bir kullanıcının profilini görüntüle (giriş yapılmış kullanıcılar)
    Route::get('/users/{user}', [UserProfileController::class, 'show']);
    Route::post('/public/artists/{user}/reviews', [PublicController::class, 'storeArtistReview']);
    Route::post('/public/studios/{studio}/reviews', [PublicController::class, 'storeStudioReview']);

    // Çıkış
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dükkan listesi
    Route::get('/shops', [ShopController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Stüdyo Yönetimi: Admin + Yönetici + Supervisor
|--------------------------------------------------------------------------
*/
Route::middleware(['api.auth', 'role:admin,yonetici,supervisor'])->group(function (): void {
    // Erişilebilir stüdyoları detay ve sayaç bilgileriyle listeler.
    Route::get('/studios/overview', [StudioController::class, 'overview']);
    // Yeni stüdyo oluşturur (şirket limiti kontrol edilir).
    Route::post('/studios', [StudioController::class, 'store']);
    // Seçili stüdyo ayarlarını günceller.
    Route::patch('/studios/{studio}', [StudioController::class, 'update']);
    // Seçili stüdyoyu sistemden siler.
    Route::delete('/studios/{studio}', [StudioController::class, 'destroy']);

});

Route::middleware(['api.auth', 'role:admin,yonetici'])->group(function (): void {
    // Yönetici yalnızca sahibi olduğu şirkete bağlı şubeleri oluşturabilir.
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/shops', [ShopController::class, 'store']);
    // Dükkan güncelleme.
    Route::patch('/shops/{shop}', [ShopController::class, 'update']);
});

/*
|--------------------------------------------------------------------------
| Stüdyo Yönetimi: Stüdyo Admini
| Studio admin kendi stüdyosunu yönetir (logo, bildirim, personel)
|--------------------------------------------------------------------------
*/
Route::middleware(['api.auth', 'role:admin,yonetici,supervisor'])->group(function (): void {
    // Stüdyo ayarları (logo, isim, bildirim süresi)
    Route::patch('/studios/{studio}/settings', [StudioController::class, 'update']);

    // Stüdyodaki tüm kullanıcıları listeler.
    Route::get('/users', [UserDirectoryController::class, 'index']);
    Route::get('/studios/{studio}/users', [UserDirectoryController::class, 'indexByStudio']);
    // Kullanıcının stüdyo rolünü ve aktif-pasif üyelik durumunu güncelle.
    Route::patch('/studios/{studio}/users/{user}', [UserDirectoryController::class, 'update']);
    // Yeni kullanıcı oluşturur ve stüdyoya atar.
    Route::post('/users', [UserDirectoryController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Platform Admin Özel İşlemleri
|--------------------------------------------------------------------------
*/
Route::middleware(['api.auth', 'role:admin'])->group(function (): void {
    // Mobil admin paneli: tüm stüdyolardaki randevu ve çalışan listeleri.
    Route::get('/admin/appointments', [AppointmentController::class, 'adminIndex']);
    Route::get('/admin/users', [UserDirectoryController::class, 'adminIndex']);

    // Tüm yorumları listele / uygunsuz yorumu sil.
    Route::get('/reviews', [PublicController::class, 'reviews']);
    Route::delete('/reviews/{review}', [PublicController::class, 'destroyReview']);
    Route::get('/content-reports', [ContentModerationController::class, 'reports']);
    Route::patch('/content-reports/{contentReport}/resolve', [ContentModerationController::class, 'resolveReport']);
    Route::post('/users/{user}/ban', [ContentModerationController::class, 'banUser']);
    Route::delete('/users/{user}/ban', [ContentModerationController::class, 'unbanUser']);

    // Şirket yönetimi
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::patch('/companies/{company}', [CompanyController::class, 'update']);

    // Dükkan sil
    Route::delete('/shops/{shop}', [ShopController::class, 'destroy']);

    // Yönetici (yonetici) atamaları
    Route::get('/studios/{studio}/managers', [StudioStaffController::class, 'index'])
        ->defaults('role', 'yonetici');
    Route::post('/studios/{studio}/managers', [StudioManagerController::class, 'store']);
    Route::patch('/studios/{studio}/managers/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'yonetici');
    Route::delete('/studios/{studio}/managers/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'yonetici');
});

/*
|--------------------------------------------------------------------------
| Personel Yönetimi: Admin + Yönetici + Studio Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['api.auth', 'role:admin,yonetici,supervisor'])->group(function (): void {
    // Supervisor
    Route::get('/studios/{studio}/supervisors', [StudioStaffController::class, 'index'])
        ->defaults('role', 'supervisor');
    Route::post('/studios/{studio}/supervisors', [StudioStaffController::class, 'store'])
        ->defaults('role', 'supervisor');
    Route::patch('/studios/{studio}/supervisors/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'supervisor');
    Route::delete('/studios/{studio}/supervisors/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'supervisor');

    // Şoför
    Route::get('/studios/{studio}/drivers', [StudioStaffController::class, 'index'])
        ->defaults('role', 'sofor');
    Route::post('/studios/{studio}/drivers', [StudioStaffController::class, 'store'])
        ->defaults('role', 'sofor');
    Route::patch('/studios/{studio}/drivers/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'sofor');
    Route::delete('/studios/{studio}/drivers/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'sofor');

    // Tasarımcı
    Route::get('/studios/{studio}/designers', [StudioStaffController::class, 'index'])
        ->defaults('role', 'designer');
    Route::post('/studios/{studio}/designers', [StudioStaffController::class, 'store'])
        ->defaults('role', 'designer');
    Route::patch('/studios/{studio}/designers/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'designer');
    Route::delete('/studios/{studio}/designers/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'designer');

    // Artist
    Route::get('/studios/{studio}/artists', [StudioStaffController::class, 'index'])
        ->defaults('role', 'artist');
    Route::post('/studios/{studio}/artists', [StudioStaffController::class, 'store'])
        ->defaults('role', 'artist');
    Route::patch('/studios/{studio}/artists/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'artist');
    Route::delete('/studios/{studio}/artists/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'artist');

    // Info çalışanı
    Route::get('/studios/{studio}/info-staff', [StudioStaffController::class, 'index'])
        ->defaults('role', 'info');
    Route::post('/studios/{studio}/info-staff', [StudioStaffController::class, 'store'])
        ->defaults('role', 'info');
    Route::patch('/studios/{studio}/info-staff/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'info');
    Route::delete('/studios/{studio}/info-staff/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'info');

    // Geriye dönük uyumluluk — çalışan (calisan) rolü
    Route::get('/studios/{studio}/employees', [StudioStaffController::class, 'index'])
        ->defaults('role', 'calisan');
    Route::post('/studios/{studio}/employees', [StudioStaffController::class, 'store'])
        ->defaults('role', 'calisan');
    Route::patch('/studios/{studio}/employees/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'calisan');
    Route::delete('/studios/{studio}/employees/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'calisan');
});

/*
|--------------------------------------------------------------------------
| Randevu İşlemleri
|--------------------------------------------------------------------------
*/

// Randevu oluşturma/güncelleme: stüdyo yönetimi + bilgi ekranları + tasarımcı + şoför
Route::middleware(['api.auth', 'role:admin,yonetici,supervisor,designer,info,sofor,calisan'])->group(function (): void {
    // Randevu oluşturma/güncelleme ekranları için artist listesi ve sabitleri döndürür.
    Route::get('/studios/{studio}/appointment-support', [AppointmentController::class, 'support']);
    // Müşterinin önceki randevusuna bakarak eski mi yeni mi olduğunu kontrol eder.
    Route::post('/studios/{studio}/appointments/check-customer', [AppointmentController::class, 'checkCustomerStatus']);
    // Yeni randevu oluşturur.
    Route::post('/studios/{studio}/appointments', [AppointmentController::class, 'store']);
    // Var olan randevunun durum veya müşteri bilgilerini günceller.
    Route::patch('/studios/{studio}/appointments/{appointment}', [AppointmentController::class, 'update']);
});

// Randevu silme: yalnızca supervisor ve üstü
Route::middleware(['api.auth', 'role:admin,yonetici,supervisor'])->group(function (): void {
    Route::delete('/studios/{studio}/appointments/{appointment}', [AppointmentController::class, 'destroy']);
});

// Randevu listesi ve detay: tüm stüdyo çalışanları tüm randevuları görür
Route::middleware(['api.auth', 'role:admin,yonetici,supervisor,designer,artist,info,sofor,calisan'])->group(function (): void {
    // Seçili stüdyodaki tüm randevuları listeler.
    Route::get('/studios/{studio}/appointments', [AppointmentController::class, 'index']);
    // Tek bir randevunun detayını getirir.
    Route::get('/studios/{studio}/appointments/{appointment}', [AppointmentController::class, 'show']);
});

// Artist atama: supervisor ve üstü
Route::middleware(['api.auth', 'role:admin,yonetici,supervisor'])->group(function (): void {
    // Randevuya artist veya freelancer atar.
    // assigned_artist_user_id: stüdyo artisti ya da kullanici_rol kullanıcısı
    Route::patch('/studios/{studio}/appointments/{appointment}/assign-artist', [AppointmentController::class, 'assignArtist']);
});

// Şoför şubesindeki pick up gereken randevuları görür ve durum güncelleyebilir.
Route::middleware(['api.auth', 'role:sofor'])->group(function (): void {
    // Şoförün bağlı olduğu şubedeki pick up gereken randevuları listeler.
    Route::get('/my-appointments', [AppointmentController::class, 'myAppointments']);
    // driver_status: picked_up (aldım) | dropped_off (bıraktım) | cancelled (iptal ettim)
    Route::patch('/studios/{studio}/appointments/{appointment}/driver-action', [AppointmentController::class, 'driverAction']);
});

// Kullanıcı/stüdyo randevu talebi — kabul edilince randevuya dönüşür.
Route::middleware(['api.auth'])->group(function (): void {
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'showMine']);
    Route::post('/appointments/request', [AppointmentRequestController::class, 'store']);
    Route::get('/appointment-requests', [AppointmentRequestController::class, 'index']);
    Route::get('/appointment-requests/{appointmentRequest}', [AppointmentRequestController::class, 'show']);
    Route::patch('/appointment-requests/{appointmentRequest}/accept', [AppointmentRequestController::class, 'accept']);
    Route::patch('/appointment-requests/{appointmentRequest}/reject', [AppointmentRequestController::class, 'reject']);
});

// Artist/designer kendi atanmış randevularını listeler.
Route::middleware(['api.auth', 'role:artist,designer,kullanici_rol'])->group(function (): void {
    // Stüdyodan bağımsız, artiste atanmış randevuları listeler.
    Route::get('/my-artist-appointments', [AppointmentController::class, 'myArtistAppointments']);
    // Artist tamamlanan dövmenin fotoğrafını yükler; fiyat bundan sonra görünür.
    Route::post('/appointments/{appointment}/artist-complete', [AppointmentController::class, 'artistComplete']);
});

/*
|--------------------------------------------------------------------------
| Profil & Portfolio Endpoint'leri
|--------------------------------------------------------------------------
*/

// Stüdyo profili: randevu istatistikleri, galeri, çalışma saatleri
Route::middleware(['api.auth', 'role:admin,yonetici,supervisor'])->group(function (): void {
    Route::get('/studios/{studio}/profile', [ProfileController::class, 'studioProfile']);
});

// Şube profili: bağlı stüdyolar, toplu istatistikler, galeri
Route::middleware(['api.auth', 'role:admin,yonetici,supervisor'])->group(function (): void {
    Route::get('/shops/{shop}/profile', [ProfileController::class, 'shopProfile']);
});

// Şirket profili: tüm şubeler, stüdyolar, toplu portfolio
Route::middleware(['api.auth', 'role:admin'])->group(function (): void {
    Route::get('/companies/{company}/profile', [ProfileController::class, 'companyProfile']);
});

/*
|--------------------------------------------------------------------------
| Medya Yükleme: Logo & Galeri Görselleri
|--------------------------------------------------------------------------
*/

// Stüdyo medya yönetimi (admin, yönetici, supervisor)
Route::middleware(['api.auth', 'role:admin,yonetici,supervisor'])->group(function (): void {
    Route::post('/studios/{studio}/logo', [MediaController::class, 'uploadStudioLogo']);
    Route::post('/studios/{studio}/gallery', [MediaController::class, 'addStudioGalleryImage']);
    Route::delete('/studios/{studio}/gallery', [MediaController::class, 'removeStudioGalleryImage']);
});

// Şube medya yönetimi (admin, yönetici)
Route::middleware(['api.auth', 'role:admin,yonetici'])->group(function (): void {
    Route::post('/shops/{shop}/logo', [MediaController::class, 'uploadShopLogo']);
    Route::post('/shops/{shop}/gallery', [MediaController::class, 'addShopGalleryImage']);
    Route::delete('/shops/{shop}/gallery', [MediaController::class, 'removeShopGalleryImage']);
});

// Şirket medya yönetimi (yalnızca admin)
Route::middleware(['api.auth', 'role:admin'])->group(function (): void {
    Route::post('/companies/{company}/logo', [MediaController::class, 'uploadCompanyLogo']);
    Route::post('/companies/{company}/gallery', [MediaController::class, 'addCompanyGalleryImage']);
    Route::delete('/companies/{company}/gallery', [MediaController::class, 'removeCompanyGalleryImage']);
});
