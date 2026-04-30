<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentSlipOcrController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\StudioController;
use App\Http\Controllers\Api\StudioManagerController;
use App\Http\Controllers\Api\StudioStaffController;
use App\Http\Controllers\Api\UserDirectoryController;
use Illuminate\Support\Facades\Route;

// Gorsel yuklenip AI ile randevu fisindeki alanlari okuyarak JSON dondurur.
Route::post('/ocr/appointment-slip', AppointmentSlipOcrController::class);

// Kullanici girisi yapar ve sonraki API isteklerinde kullanilacak bearer token uretir.
Route::post('/login', [AuthController::class, 'login']);

// Kimlik dogrulamasi gereken genel API'ler: anasayfa, profil, cikis, erisilebilir studyo ve dukkan listeleri.
Route::middleware(['api.auth'])->group(function (): void {
    // Kullaniciya gore filtrelenmis dashboard ozeti ve gunluk randevu verilerini dondurur.
    Route::get('/home', [DashboardController::class, 'index']);
    // Giris yapan kullanicinin temel profil bilgisini dondurur.
    Route::get('/me', [AuthController::class, 'me']);
    // Mobil tarafla uyumluluk icin /me ile ayni profil bilgisini alternatif endpointten dondurur.
    Route::get('/profile', [AuthController::class, 'me']);
    // Mevcut kullanicinin bearer token ini iptal ederek cikis yapar.
    Route::post('/logout', [AuthController::class, 'logout']);
    // Giris yapan kullanicinin gorebildigi studyo seceneklerini dropdown icin listeler.
    Route::get('/studios/options', [UserDirectoryController::class, 'studioOptions']);
    // Giris yapan kullanicinin erisebildigi dukkanlari ve onlara bagli studyolari listeler.
    Route::get('/shops', [ShopController::class, 'index']);
});

// Yapisal yonetim API'leri: sadece admin ve yonetici dukkan/studyo/personel duzenleyebilir.
Route::middleware(['api.auth', 'role:admin,yonetici'])->group(function (): void {
    // Secili studyo ayarlarini gunceller.
    Route::patch('/studios/{studio}', [StudioController::class, 'update']);
    // Secili studyoya yeni personel ekler.
    Route::post('/users', [UserDirectoryController::class, 'store']);
    // Secili studyoya bagli tum kullanicilari listeler.
    Route::get('/studios/{studio}/users', [UserDirectoryController::class, 'indexByStudio']);
    // Secili studyodaki kullanicinin rol, durum veya profil bilgilerini gunceller.
    Route::patch('/studios/{studio}/users/{user}', [UserDirectoryController::class, 'update']);
    // Secili dukkanin ad, konum veya yonetici atamasi gibi bilgilerini gunceller.
    Route::patch('/shops/{shop}', [ShopController::class, 'update']);
});

// Sadece admin tarafindan kullanilan ust seviye yonetim API'leri.
Route::middleware(['api.auth', 'role:admin'])->group(function (): void {
    // Yeni dukkan olusturur ve yonetici atayabilir.
    Route::post('/shops', [ShopController::class, 'store']);
    // Bir studyodaki yonetici rolundeki kullanicilari listeler.
    Route::get('/studios/{studio}/managers', [StudioStaffController::class, 'index'])
        ->defaults('role', 'yonetici');
    // Bir studyoya yonetici ekler veya mevcut kullaniciyi yonetici olarak baglar.
    Route::post('/studios/{studio}/managers', [StudioManagerController::class, 'store']);
    // Bir studyodaki yoneticinin bilgilerini gunceller.
    Route::patch('/studios/{studio}/managers/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'yonetici');
    // Bir studyodaki yoneticiyi pasife alir.
    Route::delete('/studios/{studio}/managers/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'yonetici');
});

// Admin ve yonetici tarafindan kullanilan personel yonetim API'leri.
Route::middleware(['api.auth', 'role:admin,yonetici'])->group(function (): void {
    // Bir studyodaki supervisor kullanicilarini listeler.
    Route::get('/studios/{studio}/supervisors', [StudioStaffController::class, 'index'])
        ->defaults('role', 'supervisor');
    // Bir studyoya supervisor ekler.
    Route::post('/studios/{studio}/supervisors', [StudioStaffController::class, 'store'])
        ->defaults('role', 'supervisor');
    // Bir studyodaki supervisor bilgisini gunceller.
    Route::patch('/studios/{studio}/supervisors/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'supervisor');
    // Bir studyodaki supervisor kullanicisini pasife alir.
    Route::delete('/studios/{studio}/supervisors/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'supervisor');

    // Bir studyodaki sofor kullanicilarini listeler.
    Route::get('/studios/{studio}/drivers', [StudioStaffController::class, 'index'])
        ->defaults('role', 'sofor');
    // Bir studyoya yeni sofor ekler.
    Route::post('/studios/{studio}/drivers', [StudioStaffController::class, 'store'])
        ->defaults('role', 'sofor');
    // Bir studyodaki sofor bilgisini gunceller.
    Route::patch('/studios/{studio}/drivers/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'sofor');
    // Bir studyodaki soforu pasife alir.
    Route::delete('/studios/{studio}/drivers/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'sofor');

    // Bir studyodaki calisan kullanicilarini listeler.
    Route::get('/studios/{studio}/employees', [StudioStaffController::class, 'index'])
        ->defaults('role', 'calisan');
    // Bir studyoya yeni calisan ekler.
    Route::post('/studios/{studio}/employees', [StudioStaffController::class, 'store'])
        ->defaults('role', 'calisan');
    // Bir studyodaki calisan bilgisini gunceller.
    Route::patch('/studios/{studio}/employees/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'calisan');
    // Bir studyodaki calisani pasife alir.
    Route::delete('/studios/{studio}/employees/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'calisan');
});

// Randevu operasyonlari: admin, yonetici, supervisor ve calisan bu grup uzerinden randevu yonetebilir.
Route::middleware(['api.auth', 'role:admin,yonetici,supervisor,calisan'])->group(function (): void {
    // Musterinin onceki randevusuna bakarak eski mi yeni mi oldugunu kontrol eder.
    Route::post('/studios/{studio}/appointments/check-customer', [AppointmentController::class, 'checkCustomerStatus']);
    // Secili studyodaki randevulari listeler.
    Route::get('/studios/{studio}/appointments', [AppointmentController::class, 'index']);
    // Tek bir randevunun detayini getirir.
    Route::get('/studios/{studio}/appointments/{appointment}', [AppointmentController::class, 'show']);
    // Yeni randevu olusturur.
    Route::post('/studios/{studio}/appointments', [AppointmentController::class, 'store']);
    // Var olan randevunun durum, surucu veya musteri bilgilerini gunceller.
    Route::patch('/studios/{studio}/appointments/{appointment}', [AppointmentController::class, 'update']);
    // Randevuyu sistemden siler.
    Route::delete('/studios/{studio}/appointments/{appointment}', [AppointmentController::class, 'destroy']);
});
