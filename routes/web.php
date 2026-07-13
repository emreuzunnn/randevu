<?php

use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\ContentReportController as AdminContentReportController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NotificationLogController as AdminNotificationLogController;
use App\Http\Controllers\Admin\StudioController as AdminStudioController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Webhook\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');

Route::get('/webhook/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhook.whatsapp.verify');
Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('webhook.whatsapp.receive');
Route::get('/test-whatsapp', [WhatsAppWebhookController::class, 'sendTestMessage'])->name('webhook.whatsapp.test');

Route::middleware(['auth', 'admin.panel'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
    Route::post('/companies', [AdminCompanyController::class, 'store'])->name('companies.store');
    Route::post('/companies/{company}', [AdminCompanyController::class, 'update'])->name('companies.update');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');

    Route::get('/appointments', [AdminAppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/{appointment}', [AdminAppointmentController::class, 'show'])->name('appointments.show');
    Route::view('/earnings', 'admin.earnings.index')->name('earnings.index');

    Route::view('/appointment-requests', 'admin.appointment-requests.index')->name('appointment-requests.index');
    Route::view('/my-notifications', 'admin.my-notifications.index')->name('my-notifications.index');
    Route::view('/discovery', 'admin.discovery.index')->name('discovery.index');
    Route::view('/discovery/studios/{studio}', 'admin.discovery.studio')->name('discovery.studio');
    Route::view('/discovery/artists/{user}', 'admin.discovery.artist')->name('discovery.artist');
    Route::view('/profile', 'admin.profile.index')->name('profile.index');
    Route::view('/profile/appointments', 'admin.profile.appointments')->name('profile.appointments');
    Route::view('/settings', 'admin.settings.index')->name('settings.index');

    Route::get('/notifications', AdminNotificationLogController::class)->name('notifications.index');
    Route::get('/content-reports', [AdminContentReportController::class, 'index'])->name('content-reports.index');
    Route::post('/content-reports/{contentReport}/resolve', [AdminContentReportController::class, 'resolve'])->name('content-reports.resolve');
    Route::post('/content-reports/users/{user}/ban', [AdminContentReportController::class, 'ban'])->name('content-reports.ban');
    Route::post('/content-reports/users/{user}/unban', [AdminContentReportController::class, 'unban'])->name('content-reports.unban');

    Route::get('/studios', [AdminStudioController::class, 'index'])->name('studios.index');
    Route::post('/studios/{studio}', [AdminStudioController::class, 'update'])->name('studios.update');

});
