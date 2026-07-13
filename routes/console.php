<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('appointments:send-reminders {--minutes=15}', function () {
    $minutes = max(1, (int) $this->option('minutes'));
    $sent = app(\App\Services\AppointmentNotificationService::class)
        ->sendDueReminders($minutes);

    $this->info("{$sent} randevu hatırlatma bildirimi gönderildi.");
})->purpose('Tasarım rezervasyonları için yaklaşan saat hatırlatma bildirimlerini gönderir');

Schedule::command('appointments:send-reminders')->everyMinute();
