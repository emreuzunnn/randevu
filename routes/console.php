<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Http\Controllers\Api\NotificationSettingsController;
use App\Models\AppSetting;
use App\Services\WhatsAppTemplateService;

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

Artisan::command('whatsapp:create-templates {--dry-run : Meta API çağrısı yapmadan oluşturulacak şablonları listeler}', function () {
    $service = app(WhatsAppTemplateService::class);

    if ((bool) $this->option('dry-run')) {
        $definitions = $service->templateDefinitions();
        $this->info(count($definitions).' WhatsApp mesaj şablonu hazırlanacak.');

        foreach ($definitions as $definition) {
            $this->line("- {$definition['name']} ({$definition['language']})");
        }

        return 0;
    }

    $results = $service->createAllTemplates();
    $successCount = collect($results)->where('success', true)->count();
    $failedCount = count($results) - $successCount;

    foreach ($results as $result) {
        $prefix = $result['success'] ? '<info>OK</info>' : '<error>HATA</error>';
        $template = $result['template'] ?? 'ayar';
        $language = $result['language'] ?? '-';
        $status = $result['status'] ?? '-';
        $this->line("{$prefix} {$template} ({$language}) HTTP {$status}");

        if (! $result['success'] && filled($result['error'] ?? null)) {
            $this->line('  '.$result['error']);
        }
    }

    $this->info("WhatsApp şablon işlemi tamamlandı. Başarılı: {$successCount}, Hatalı: {$failedCount}");

    return $failedCount === 0 ? 0 : 1;
})->purpose('Randevu oluşturma ve hatırlatma için çok dilli WhatsApp mesaj şablonlarını Meta API üzerinden oluşturur');

Schedule::command('appointments:send-reminders')->everyMinute();
