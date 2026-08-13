<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Http\Controllers\Api\NotificationSettingsController;
use App\Models\AppSetting;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('appointments:send-reminders {--minutes=}', function () {
    $configuredMinutes = (int) AppSetting::valueFor(NotificationSettingsController::REMINDER_MINUTES_KEY, 15);
    $optionMinutes = $this->option('minutes');
    $minutes = max(1, (int) (($optionMinutes !== null && $optionMinutes !== '') ? $optionMinutes : $configuredMinutes));
    $sent = app(\App\Services\AppointmentNotificationService::class)
        ->sendDueReminders($minutes);

    $this->info("{$sent} randevu hatırlatma bildirimi gönderildi.");
})->purpose('Tasarım rezervasyonları için yaklaşan saat hatırlatma bildirimlerini gönderir');

Schedule::command('appointments:send-reminders')->everyMinute();
