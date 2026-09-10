<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppTemplateService
{
    private const TEMPLATE_PREFIX = 'tattodesk_design';

    private const COUNTRY_LOCALES = [
        '358' => 'fi',
        '372' => 'et',
        '90' => 'tr',
        '49' => 'de',
        '44' => 'en',
        '48' => 'pl',
        '31' => 'nl',
        '41' => 'de',
        '32' => 'nl',
        '46' => 'sv',
        '47' => 'no',
        '45' => 'da',
        '7' => 'ru',
    ];

    private const LOCALE_LANGUAGES = [
        'tr' => 'tr',
        'en' => 'en_US',
        'de' => 'de',
        'pl' => 'pl',
        'nl' => 'nl',
        'ru' => 'ru',
        'et' => 'et',
        'sv' => 'sv',
        'no' => 'nb',
        'da' => 'da',
        'fi' => 'fi',
    ];

    private const TEMPLATE_TEXTS = [
        'tr' => [
            'appointment_created' => "*Randevu Hatırlatma*\nMerhaba, {{company_name}} şirketine tasarım için randevunuz bulunmaktadır.\n\n{{customer_name}}\n{{hotel_name}} {{room_number}}\n{{appointment_datetime}}\n\nLütfen 5 dakika önceden hazır olunuz.\n\nTeşekkürler.",
            'appointment_reminder' => "*Randevu Hatırlatması*\nMerhaba {{customer_name}},\n\nRandevunuza *{{reminder_minutes}} dakika* kaldığını hatırlatmak isteriz.\n\nRandevu saatinizde hazır bulunmanızı rica ederiz.\n\nTeşekkür ederiz.",
        ],
        'en' => [
            'appointment_created' => "*Appointment Reminder*\nHello, you have an appointment for a free design with {{company_name}}.\n\n{{customer_name}}\n{{hotel_name}} {{room_number}}\n{{appointment_datetime}}\n\nPlease be ready 5 minutes early.\n\nThank you.",
            'appointment_reminder' => "*Appointment Reminder*\nHello {{customer_name}},\n\nWe would like to remind you that your appointment is in *{{reminder_minutes}} minutes*.\n\nPlease be ready at your appointment time.\n\nThank you.",
        ],
        'de' => [
            'appointment_created' => "*Termin-Erinnerung*\nHallo, Sie haben einen Termin für ein kostenloses Design bei {{company_name}}.\n\n{{customer_name}}\n{{hotel_name}} {{room_number}}\n{{appointment_datetime}}\n\nBitte seien Sie 5 Minuten vorher bereit.\n\nVielen Dank.",
            'appointment_reminder' => "*Termin-Erinnerung*\nHallo {{customer_name}},\n\nwir möchten Sie daran erinnern, dass Ihr Termin in *{{reminder_minutes}} Minuten* beginnt.\n\nBitte seien Sie pünktlich zu Ihrem Termin bereit.\n\nVielen Dank.",
        ],
        'pl' => [
            'appointment_created' => "*Przypomnienie o wizycie*\nDzień dobry, masz umówioną wizytę na bezpłatny projekt w {{company_name}}.\n\n{{customer_name}}\n{{hotel_name}} {{room_number}}\n{{appointment_datetime}}\n\nProsimy być gotowym 5 minut wcześniej.\n\nDziękujemy.",
            'appointment_reminder' => "*Przypomnienie o wizycie*\nDzień dobry {{customer_name}},\n\nprzypominamy, że Twoja wizyta rozpocznie się za *{{reminder_minutes}} minut*.\n\nProsimy być gotowym o wyznaczonej godzinie.\n\nDziękujemy.",
        ],
        'nl' => [
            'appointment_created' => "*Afspraakherinnering*\nHallo, u heeft een afspraak voor een gratis ontwerp bij {{company_name}}.\n\n{{customer_name}}\n{{hotel_name}} {{room_number}}\n{{appointment_datetime}}\n\nZorg dat u 5 minuten eerder klaar bent.\n\nBedankt.",
            'appointment_reminder' => "*Afspraakherinnering*\nHallo {{customer_name}},\n\nwij willen u eraan herinneren dat uw afspraak over *{{reminder_minutes}} minuten* begint.\n\nZorg dat u op tijd klaar bent.\n\nBedankt.",
        ],
        'ru' => [
            'appointment_created' => "*Напоминание о записи*\nЗдравствуйте, у вас есть запись на бесплатный дизайн в {{company_name}}.\n\n{{customer_name}}\n{{hotel_name}} {{room_number}}\n{{appointment_datetime}}\n\nПожалуйста, будьте готовы за 5 минут.\n\nСпасибо.",
            'appointment_reminder' => "*Напоминание о записи*\nЗдравствуйте, {{customer_name}}.\n\nНапоминаем, что до вашей записи осталось *{{reminder_minutes}} минут*.\n\nПожалуйста, будьте готовы ко времени записи.\n\nСпасибо.",
        ],
        'et' => [
            'appointment_created' => "*Aja meeldetuletus*\nTere, teil on tasuta disaini aeg ettevõttes {{company_name}}.\n\n{{customer_name}}\n{{hotel_name}} {{room_number}}\n{{appointment_datetime}}\n\nPalun olge valmis 5 minutit varem.\n\nAitäh.",
            'appointment_reminder' => "*Aja meeldetuletus*\nTere {{customer_name}},\n\nsoovime meelde tuletada, et teie aeg algab *{{reminder_minutes}} minuti* pärast.\n\nPalun olge õigel ajal valmis.\n\nAitäh.",
        ],
        'sv' => [
            'appointment_created' => "*Tidsbokningspåminnelse*\nHej, du har en bokning för en kostnadsfri design hos {{company_name}}.\n\n{{customer_name}}\n{{hotel_name}} {{room_number}}\n{{appointment_datetime}}\n\nVar redo 5 minuter innan.\n\nTack.",
            'appointment_reminder' => "*Tidsbokningspåminnelse*\nHej {{customer_name}},\n\nvi vill påminna dig om att din bokning börjar om *{{reminder_minutes}} minuter*.\n\nVar redo vid din bokade tid.\n\nTack.",
        ],
        'no' => [
            'appointment_created' => "*Timepåminnelse*\nHei, du har en time for gratis design hos {{company_name}}.\n\n{{customer_name}}\n{{hotel_name}} {{room_number}}\n{{appointment_datetime}}\n\nVennligst vær klar 5 minutter før.\n\nTakk.",
            'appointment_reminder' => "*Timepåminnelse*\nHei {{customer_name}},\n\nvi minner om at timen din starter om *{{reminder_minutes}} minutter*.\n\nVennligst vær klar til avtalt tid.\n\nTakk.",
        ],
        'da' => [
            'appointment_created' => "*Aftalepåmindelse*\nHej, du har en aftale om gratis design hos {{company_name}}.\n\n{{customer_name}}\n{{hotel_name}} {{room_number}}\n{{appointment_datetime}}\n\nVær venligst klar 5 minutter før.\n\nTak.",
            'appointment_reminder' => "*Aftalepåmindelse*\nHej {{customer_name}},\n\nvi vil minde dig om, at din aftale starter om *{{reminder_minutes}} minutter*.\n\nVær venligst klar til tiden.\n\nTak.",
        ],
        'fi' => [
            'appointment_created' => "*Ajanvarausmuistutus*\nHei, sinulla on aika maksuttomaan suunnitteluun yrityksessä {{company_name}}.\n\n{{customer_name}}\n{{hotel_name}} {{room_number}}\n{{appointment_datetime}}\n\nOle valmis 5 minuuttia aikaisemmin.\n\nKiitos.",
            'appointment_reminder' => "*Ajanvarausmuistutus*\nHei {{customer_name}},\n\nmuistutamme, että ajanvarauksesi alkaa *{{reminder_minutes}} minuutin* kuluttua.\n\nOle valmis ajanvarauksesi aikaan.\n\nKiitos.",
        ],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function createAllTemplates(): array
    {
        $accessToken = (string) config('services.whatsapp.access_token', '');
        $businessAccountId = (string) config('services.whatsapp.business_account_id', '');
        $version = (string) config('services.whatsapp.graph_version', 'v23.0');

        if ($accessToken === '' || $businessAccountId === '') {
            return [[
                'success' => false,
                'template' => null,
                'language' => null,
                'status' => null,
                'error' => 'WHATSAPP_ACCESS_TOKEN veya WHATSAPP_BUSINESS_ACCOUNT_ID eksik.',
                'response' => null,
            ]];
        }

        $results = [];

        foreach ($this->templateDefinitions() as $definition) {
            try {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->asJson()
                    ->timeout(20)
                    ->post("https://graph.facebook.com/{$version}/{$businessAccountId}/message_templates", $definition);

                $payload = $response->json() ?? [];
                $results[] = [
                    'success' => $response->successful(),
                    'template' => $definition['name'],
                    'language' => $definition['language'],
                    'status' => $response->status(),
                    'error' => $response->successful() ? null : (data_get($payload, 'error.message') ?: $response->body()),
                    'response' => $payload,
                ];
            } catch (Throwable $exception) {
                $results[] = [
                    'success' => false,
                    'template' => $definition['name'],
                    'language' => $definition['language'],
                    'status' => null,
                    'error' => $exception->getMessage(),
                    'response' => null,
                ];

                Log::channel('whatsapp')->error('WhatsApp template creation exception', [
                    'template' => $definition['name'],
                    'language' => $definition['language'],
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function templateDefinitions(): array
    {
        $definitions = [];

        foreach (self::TEMPLATE_TEXTS as $locale => $texts) {
            $definitions[] = $this->definition('appointment_created', $locale, $texts['appointment_created'], [
                ['param_name' => 'company_name', 'example' => 'Soul Of Ink Tattoo & Piercing'],
                ['param_name' => 'customer_name', 'example' => 'Emre Uzun'],
                ['param_name' => 'hotel_name', 'example' => 'Ramada Hotel'],
                ['param_name' => 'room_number', 'example' => '312'],
                ['param_name' => 'appointment_datetime', 'example' => '16.08.2026 14:30'],
            ]);

            $definitions[] = $this->definition('appointment_reminder', $locale, $texts['appointment_reminder'], [
                ['param_name' => 'customer_name', 'example' => 'Emre Uzun'],
                ['param_name' => 'reminder_minutes', 'example' => '15'],
            ]);
        }

        return $definitions;
    }

    /**
     * @return array{name:string, language:string}
     */
    public function templateForPhone(string $event, ?string $phone): array
    {
        $locale = $this->localeForPhone($phone);

        return [
            'name' => $this->templateName($event, $locale),
            'language' => self::LOCALE_LANGUAGES[$locale] ?? self::LOCALE_LANGUAGES['en'],
        ];
    }

    public function localeForPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        foreach (self::COUNTRY_LOCALES as $countryCode => $locale) {
            if (str_starts_with($digits, $countryCode)) {
                return $locale;
            }
        }

        return 'en';
    }

    private function definition(string $event, string $locale, string $bodyText, array $examples): array
    {
        return [
            'name' => $this->templateName($event, $locale),
            'language' => self::LOCALE_LANGUAGES[$locale] ?? self::LOCALE_LANGUAGES['en'],
            'category' => 'UTILITY',
            'parameter_format' => 'NAMED',
            'allow_category_change' => true,
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $bodyText,
                    'example' => [
                        'body_text_named_params' => $examples,
                    ],
                ],
            ],
        ];
    }

    private function templateName(string $event, string $locale): string
    {
        $eventSuffix = match ($event) {
            'appointment_created' => 'created',
            'appointment_reminder' => 'reminder',
            default => str_replace(['-', ':'], '_', $event),
        };

        return self::TEMPLATE_PREFIX.'_'.$eventSuffix.'_'.$locale;
    }
}
