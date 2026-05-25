<?php

use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\NotificationLogController as AdminNotificationLogController;
use App\Http\Controllers\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Admin\StudioController as AdminStudioController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');

Route::middleware(['auth', 'admin.panel'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
    Route::post('/companies', [AdminCompanyController::class, 'store'])->name('companies.store');
    Route::post('/companies/{company}', [AdminCompanyController::class, 'update'])->name('companies.update');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');

    Route::get('/appointments', [AdminAppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments', [AdminAppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/appointments/{appointment}', [AdminAppointmentController::class, 'show'])->name('appointments.show');

    Route::get('/notifications', AdminNotificationLogController::class)->name('notifications.index');

    Route::get('/studios', [AdminStudioController::class, 'index'])->name('studios.index');
    Route::post('/studios/{studio}', [AdminStudioController::class, 'update'])->name('studios.update');

    Route::get('/shops', [AdminShopController::class, 'index'])->name('shops.index');
    Route::post('/shops', [AdminShopController::class, 'store'])->name('shops.store');
    Route::post('/shops/{shop}', [AdminShopController::class, 'update'])->name('shops.update');
});
