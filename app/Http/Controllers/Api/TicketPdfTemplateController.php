<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TicketPdfTemplateController extends Controller
{
    private const TEMPLATE_LANGUAGES = ['de', 'en', 'pl', 'nl', 'ru', 'ch', 'be', 'et', 'sv', 'no', 'da', 'fi', 'tr'];

    public function show(Request $request, Company $company): JsonResponse
    {
        $this->authorizeCompanyTemplateRead($request, $company);

        return response()->json([
            'status' => 'success',
            'data' => $this->templateFor($company),
        ]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $this->authorizeCompanyTemplate($request, $company);

        $validated = $request->validate([
            'default_language' => ['required', 'string', 'in:'.implode(',', self::TEMPLATE_LANGUAGES)],
            'brand_title' => ['nullable', 'string', 'max:100'],
            'brand_subtitle' => ['nullable', 'string', 'max:100'],
            'brand_tagline' => ['nullable', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2000'],
            'footer' => ['required', 'array'],
            'footer.email' => ['nullable', 'string', 'max:255'],
            'footer.phone' => ['nullable', 'string', 'max:60'],
            'footer.address' => ['nullable', 'string', 'max:500'],
            'footer.instagram' => ['nullable', 'string', 'max:120'],
            'footer.facebook' => ['nullable', 'string', 'max:120'],
            'translations' => ['required', 'array'],
            'translations.*.name' => ['nullable', 'string', 'max:80'],
            'translations.*.labels' => ['required', 'array'],
            'translations.*.labels.*' => ['nullable', 'string', 'max:120'],
            'translations.*.contract_text' => ['nullable', 'string', 'max:12000'],
            'translations.*.acceptance_text' => ['nullable', 'string', 'max:2000'],
            'translations.*.receipt_text' => ['nullable', 'string', 'max:2000'],
            'translations.*.confirmation_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $company->update([
            'ticket_pdf_template' => $this->normalizeTemplate($validated, $company),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Bilet PDF şablonu güncellendi.',
            'data' => $this->templateFor($company->fresh()),
        ]);
    }

    public function uploadLogo(Request $request, Company $company): JsonResponse
    {
        $this->authorizeCompanyTemplate($request, $company);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $file = $request->file('logo');
        $path = $file->storeAs(
            'logos/ticket-pdf/' . $company->id,
            Str::uuid() . '.' . $file->getClientOriginalExtension(),
            'public',
        );
        $url = '/storage/' . $path;

        $template = $this->templateFor($company);
        $template['logo_url'] = $url;
        $company->update(['ticket_pdf_template' => $template]);

        return response()->json([
            'status' => 'success',
            'message' => 'PDF logosu güncellendi.',
            'logo_url' => $url,
            'data' => $this->templateFor($company->fresh()),
        ]);
    }

    public static function defaultTemplate(?Company $company = null): array
    {
        $template = [
            'default_language' => 'de',
            'brand_title' => $company?->name ?: 'SOUL OF INK',
            'brand_subtitle' => 'TATTOO & PIERCING',
            'brand_tagline' => 'THE ART OF TRUST, THE MARK OF QUALITY',
            'logo_url' => $company?->logo_path,
            'footer' => [
                'email' => $company?->email ?: 'inksoulof@gmail.com',
                'phone' => $company?->phone ?: '+90 545 424 37 39',
                'address' => $company?->address ?: 'Gündoğdu Mahallesi 18.Sokak No:8',
                'instagram' => 'soulofink.gundogdu',
                'facebook' => 'soulofink.gundogdu',
            ],
            'translations' => [
                'de' => [
                    'name' => 'Germany (+49)',
                    'labels' => self::labels([
                        'documentDate' => 'Datum des Dokuments',
                        'ticketCode' => 'Ticketcode',
                        'customerName' => 'Vorname Familienname',
                        'phone' => 'Telefon',
                        'hotelRoom' => 'Hotel / Zimmernummer',
                        'ticketType' => 'Art des Tickets',
                        'infoStaff' => 'Info -Mitarbeiter',
                        'reservationDate' => 'Reservierungsdatum',
                        'reservationTime' => 'Reservierungszeit',
                        'pickup' => 'Abholung',
                        'quantity' => 'Menge',
                        'deposit' => 'Kaution',
                        'remaining' => 'Rest',
                        'artist' => 'Tattoo-Künstler',
                        'signature' => 'UNTERSCHRIFT',
                    ]),
                    'contract_text' => implode("\n", [
                        '1. Die Anzahlung wird geleistet, um den Termin des Kunden zu sichern. Bei Absage oder Nichterscheinen des Kunden kann die Anzahlung nicht zurückerstattet werden.',
                        '2. Sollte der Kunde den Termin verschieben wollen, muss er das Studio mindestens 24 Stunden vorher informieren. Andernfalls kann eine neue Anzahlung erforderlich sein.',
                        '3. Das Studio ist berechtigt, notwendige Hinweise zu Design, medizinischer Eignung und Durchführung zu geben.',
                    ]),
                    'acceptance_text' => 'Der Kunde akzeptiert die oben genannten Informationen und Bedingungen.',
                    'receipt_text' => 'Diese Quittung wird für die Reservierung und Zahlung ausgestellt.',
                    'confirmation_text' => 'Mit seiner Unterschrift bestätigt der Kunde die Richtigkeit der Angaben.',
                ],
                'tr' => [
                    'name' => 'Turkey (+90)',
                    'labels' => self::labels([
                        'documentDate' => 'Belge Tarihi',
                        'ticketCode' => 'Bilet Kodu',
                        'customerName' => 'Ad Soyad',
                        'phone' => 'Telefon',
                        'hotelRoom' => 'Otel / Oda Numarası',
                        'ticketType' => 'Bilet Türü',
                        'infoStaff' => 'Info Personeli',
                        'reservationDate' => 'Rezervasyon Tarihi',
                        'reservationTime' => 'Rezervasyon Saati',
                        'pickup' => 'Transfer',
                        'quantity' => 'Kişi',
                        'deposit' => 'Depozito',
                        'remaining' => 'Kalan',
                        'artist' => 'Dövme Sanatçısı',
                        'signature' => 'İMZA',
                    ]),
                    'contract_text' => implode("\n", [
                        '1. Alınan depozito müşterinin randevu saatini güvence altına almak içindir. Müşteri iptal eder veya gelmezse depozito iade edilmez.',
                        '2. Müşteri randevusunu değiştirmek isterse stüdyoya en az 24 saat önce bilgi vermelidir. Aksi durumda yeni depozito istenebilir.',
                        '3. Stüdyo tasarım, sağlık uygunluğu ve işlem planı hakkında gerekli yönlendirmeleri yapma hakkına sahiptir.',
                    ]),
                    'acceptance_text' => 'Müşteri yukarıdaki bilgi ve koşulları kabul eder.',
                    'receipt_text' => 'Bu belge rezervasyon ve ödeme kaydı için düzenlenmiştir.',
                    'confirmation_text' => 'Müşteri imzası ile bilgilerin doğruluğunu onaylar.',
                ],
                'en' => [
                    'name' => 'United Kingdom (+44)',
                    'labels' => self::labels([
                        'documentDate' => 'Document Date',
                        'ticketCode' => 'Ticket Code',
                        'customerName' => 'Full Name',
                        'phone' => 'Phone',
                        'hotelRoom' => 'Hotel / Room Number',
                        'ticketType' => 'Ticket Type',
                        'infoStaff' => 'Info Staff',
                        'reservationDate' => 'Reservation Date',
                        'reservationTime' => 'Reservation Time',
                        'pickup' => 'Pickup',
                        'quantity' => 'Quantity',
                        'deposit' => 'Deposit',
                        'remaining' => 'Remaining',
                        'artist' => 'Tattoo Artist',
                        'signature' => 'SIGNATURE',
                    ]),
                    'contract_text' => implode("\n", [
                        '1. The deposit is collected to secure the customer appointment. If the customer cancels or does not attend, the deposit is non-refundable.',
                        '2. If the customer wants to reschedule, the studio must be informed at least 24 hours before the appointment. Otherwise, a new deposit may be required.',
                        '3. The studio may provide necessary guidance regarding design, medical suitability and the final procedure plan.',
                    ]),
                    'acceptance_text' => 'The customer accepts the information and conditions above.',
                    'receipt_text' => 'This receipt is issued for reservation and payment records.',
                    'confirmation_text' => 'By signing, the customer confirms that the information is correct.',
                ],
            ],
        ];

        $english = $template['translations']['en'];
        foreach (self::extraTemplateLanguageNames() as $language => $name) {
            $template['translations'][$language] = array_replace_recursive($english, [
                'name' => $name,
            ]);
        }

        return $template;
    }

    private function templateFor(Company $company): array
    {
        return $this->normalizeTemplate($company->ticket_pdf_template ?? [], $company);
    }

    private function normalizeTemplate(array $stored, ?Company $company = null): array
    {
        $template = self::defaultTemplate($company);
        $template = array_replace_recursive($template, $stored);
        $template['logo_url'] = $this->normalizeLogoUrl($template['logo_url'] ?? null);
        $template['default_language'] = in_array($template['default_language'] ?? '', self::TEMPLATE_LANGUAGES, true)
            ? $template['default_language']
            : 'de';

        return $template;
    }

    private function normalizeLogoUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        $path = $parts['path'] ?? null;

        if ($path !== null && str_starts_with($path, '/storage/')
            && in_array($host, ['localhost', '127.0.0.1'], true)) {
            return $path;
        }

        return $url;
    }

    /**
     * @return array<string, string>
     */
    private static function extraTemplateLanguageNames(): array
    {
        return [
            'pl' => 'Poland (+48)',
            'nl' => 'Netherlands (+31)',
            'ru' => 'Russia (+7)',
            'ch' => 'Switzerland (+41)',
            'be' => 'Belgium (+32)',
            'et' => 'Estonia (+372)',
            'sv' => 'Sweden (+46)',
            'no' => 'Norway (+47)',
            'da' => 'Denmark (+45)',
            'fi' => 'Finland (+358)',
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private static function labels(array $overrides): array
    {
        return array_replace([
            'documentDate' => '',
            'ticketCode' => '',
            'customerName' => '',
            'phone' => '',
            'hotelRoom' => '',
            'ticketType' => '',
            'infoStaff' => '',
            'reservationDate' => '',
            'reservationTime' => '',
            'pickup' => '',
            'quantity' => '',
            'deposit' => '',
            'remaining' => '',
            'artist' => '',
            'signature' => '',
        ], $overrides);
    }

    private function authorizeCompanyTemplate(Request $request, Company $company): void
    {
        $user = $request->user();
        abort_if($user === null, 401);

        abort_unless(
            $user->hasRole(UserRole::Admin)
                || ($user->hasRole(UserRole::Yonetici) && (int) $company->manager_user_id === (int) $user->id),
            403,
        );
    }

    private function authorizeCompanyTemplateRead(Request $request, Company $company): void
    {
        $user = $request->user();
        abort_if($user === null, 401);

        abort_unless(
            $user->hasRole(UserRole::Admin)
                || ($user->hasRole(UserRole::Yonetici) && (int) $company->manager_user_id === (int) $user->id)
                || $user->studios()
                    ->wherePivot('is_active', true)
                    ->where('studios.company_id', $company->id)
                    ->exists(),
            403,
        );
    }
}
