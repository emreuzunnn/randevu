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

Route::post('/ocr/appointment-slip', AppointmentSlipOcrController::class);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware(['api.auth'])->group(function (): void {
    Route::get('/home', [DashboardController::class, 'index']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/profile', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/studios/options', [UserDirectoryController::class, 'studioOptions']);
    Route::get('/shops', [ShopController::class, 'index']);
});

Route::middleware(['api.auth', 'role:admin,yonetici'])->group(function (): void {
    Route::patch('/studios/{studio}', [StudioController::class, 'update']);
    Route::post('/users', [UserDirectoryController::class, 'store']);
    Route::get('/studios/{studio}/users', [UserDirectoryController::class, 'indexByStudio']);
    Route::patch('/studios/{studio}/users/{user}', [UserDirectoryController::class, 'update']);
    Route::patch('/shops/{shop}', [ShopController::class, 'update']);
});

Route::middleware(['api.auth', 'role:admin'])->group(function (): void {
    Route::post('/shops', [ShopController::class, 'store']);
    Route::get('/studios/{studio}/managers', [StudioStaffController::class, 'index'])
        ->defaults('role', 'yonetici');
    Route::post('/studios/{studio}/managers', [StudioManagerController::class, 'store']);
    Route::patch('/studios/{studio}/managers/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'yonetici');
    Route::delete('/studios/{studio}/managers/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'yonetici');
});

Route::middleware(['api.auth', 'role:admin,yonetici'])->group(function (): void {
    Route::get('/studios/{studio}/supervisors', [StudioStaffController::class, 'index'])
        ->defaults('role', 'supervisor');
    Route::post('/studios/{studio}/supervisors', [StudioStaffController::class, 'store'])
        ->defaults('role', 'supervisor');
    Route::patch('/studios/{studio}/supervisors/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'supervisor');
    Route::delete('/studios/{studio}/supervisors/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'supervisor');

    Route::get('/studios/{studio}/drivers', [StudioStaffController::class, 'index'])
        ->defaults('role', 'sofor');
    Route::post('/studios/{studio}/drivers', [StudioStaffController::class, 'store'])
        ->defaults('role', 'sofor');
    Route::patch('/studios/{studio}/drivers/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'sofor');
    Route::delete('/studios/{studio}/drivers/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'sofor');

    Route::get('/studios/{studio}/employees', [StudioStaffController::class, 'index'])
        ->defaults('role', 'calisan');
    Route::post('/studios/{studio}/employees', [StudioStaffController::class, 'store'])
        ->defaults('role', 'calisan');
    Route::patch('/studios/{studio}/employees/{user}', [StudioStaffController::class, 'update'])
        ->defaults('role', 'calisan');
    Route::delete('/studios/{studio}/employees/{user}', [StudioStaffController::class, 'destroy'])
        ->defaults('role', 'calisan');
});

Route::middleware(['api.auth', 'role:admin,yonetici,supervisor,calisan'])->group(function (): void {
    Route::post('/studios/{studio}/appointments/check-customer', [AppointmentController::class, 'checkCustomerStatus']);
    Route::get('/studios/{studio}/appointments', [AppointmentController::class, 'index']);
    Route::get('/studios/{studio}/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::post('/studios/{studio}/appointments', [AppointmentController::class, 'store']);
    Route::patch('/studios/{studio}/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/studios/{studio}/appointments/{appointment}', [AppointmentController::class, 'destroy']);
});
