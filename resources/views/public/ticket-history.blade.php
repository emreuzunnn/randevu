<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bilet Geçmişi</title>
    @vite(['resources/css/app.css'])
</head>
<body style="min-height:100vh;background:var(--bg);color:var(--text-main);padding:1.25rem">
    @php
        $statusLabels = [
            'confirmed' => 'Planlandı',
            'in_progress' => 'Devam ediyor',
            'completed' => 'Tamamlandı',
            'cancelled' => 'İptal edildi',
        ];
        $driverLabels = [
            'picked_up' => 'Müşteri alındı',
            'dropped_off' => 'Transfer tamamlandı',
            'customer_no_show' => 'Müşteri gelmedi',
            'cancelled' => 'Transfer iptal',
        ];
        $artistLabels = [
            'pending' => 'Artist onayı bekliyor',
            'accepted' => 'Artist kabul etti',
            'rejected' => 'Artist reddetti',
        ];
        $ticketTypeLabels = [
            'cream_sale' => 'Krem satışı',
            'piercing' => 'Piercing',
            'tattoo' => 'Dövme',
            'piercing_make' => 'Piercing yapımı',
        ];
        $tattooTypeLabels = [
            'coverup' => 'Coverup',
            'freehand' => 'Freehand',
            'refresh' => 'Refresh',
            'touchub' => 'Touchup',
            'clean' => 'Clean',
        ];
        $paymentMethodLabels = [
            'cash' => 'Nakit',
            'credit_card' => 'Kredi kartı',
        ];
        $imageUrl = static function (?string $path): ?string {
            if (! $path) {
                return null;
            }

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            return str_starts_with($path, '/storage/')
                ? $path
                : '/storage/'.ltrim($path, '/');
        };
    @endphp
    <main style="width:min(100%,760px);margin:0 auto;display:grid;gap:1rem">
        <section class="hero-card">
            <div class="section-eyebrow">Tattoodesk</div>
            <h1 class="page-hero-title" style="margin-top:0.45rem">Geçmiş Randevularınız</h1>
            <p class="page-hero-desc" style="margin-top:0.55rem">
                Bu sayfada aynı telefon numarasıyla oluşturulmuş önceki randevu ve bilet kayıtlarınız listelenir.
            </p>
        </section>

        <section class="panel-card">
            <div class="section-title" style="margin-bottom:0.8rem">
                {{ trim(($appointment->first_name ?? '').' '.($appointment->last_name ?? '')) ?: 'Müşteri' }}
            </div>
            <div class="list-stack">
                @forelse($appointments as $item)
                    @php
                        $ticketLabels = collect($item->ticket_types ?? [])
                            ->map(fn ($type) => $ticketTypeLabels[$type] ?? $type)
                            ->filter()
                            ->implode(', ');
                        $images = collect([
                                $item->photo_path,
                                $item->source_image_path,
                                ...($item->tattoo_image_paths ?? []),
                                $item->completed_tattoo_image_path,
                            ])
                            ->map($imageUrl)
                            ->filter()
                            ->unique()
                            ->values();
                    @endphp
                    <article class="list-card" style="padding:0.9rem 1rem">
                        <div style="display:flex;justify-content:space-between;gap:0.75rem;align-items:flex-start">
                            <div>
                                <div style="font-weight:700">
                                    {{ $item->appointment_type === 'tattoo' ? 'Bilet' : 'Randevu' }}
                                </div>
                                <div style="margin-top:0.25rem;font-size:0.78rem;color:var(--text-muted)">
                                    {{ optional($item->appointment_at)->format('d.m.Y H:i') ?? 'Tarih yok' }}
                                </div>
                                <div style="margin-top:0.2rem;font-size:0.76rem;color:var(--text-subtle)">
                                    {{ $item->studio?->company?->name ?? $item->studio?->name ?? 'Stüdyo' }}
                                </div>
                            </div>
                            <span class="badge-pill">{{ $statusLabels[$item->status] ?? ucfirst($item->status ?? 'confirmed') }}</span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:0.55rem;margin-top:0.85rem">
                            <div class="field-wrap" style="padding:0.65rem;background:var(--surface-soft);border-radius:0.7rem">
                                <div class="field-label">Müşteri</div>
                                <div style="font-weight:700">{{ trim(($item->first_name ?? '').' '.($item->last_name ?? '')) ?: '—' }}</div>
                                <div style="margin-top:0.15rem;color:var(--text-muted);font-size:0.78rem">{{ trim(($item->phone_country_code ?? '').' '.($item->phone_number ?? '')) ?: '—' }}</div>
                            </div>
                            <div class="field-wrap" style="padding:0.65rem;background:var(--surface-soft);border-radius:0.7rem">
                                <div class="field-label">Konaklama</div>
                                <div style="font-weight:700">{{ $item->hotel_name ?: '—' }}</div>
                                <div style="margin-top:0.15rem;color:var(--text-muted);font-size:0.78rem">Oda {{ $item->room_number ?: '—' }}</div>
                            </div>
                            <div class="field-wrap" style="padding:0.65rem;background:var(--surface-soft);border-radius:0.7rem">
                                <div class="field-label">Yer / Kişi</div>
                                <div style="font-weight:700">{{ $item->place ?: '—' }}</div>
                                <div style="margin-top:0.15rem;color:var(--text-muted);font-size:0.78rem">{{ $item->pax ?: 1 }} kişi</div>
                            </div>
                            <div class="field-wrap" style="padding:0.65rem;background:var(--surface-soft);border-radius:0.7rem">
                                <div class="field-label">Atama</div>
                                <div style="font-weight:700">{{ $item->assignedArtist?->name ?: 'Atanmadı' }}</div>
                                <div style="margin-top:0.15rem;color:var(--text-muted);font-size:0.78rem">{{ $artistLabels[$item->artist_status] ?? '—' }}</div>
                            </div>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.75rem">
                            @if($item->appointment_type === 'tattoo' && $ticketLabels)
                                <span class="badge-pill">{{ $ticketLabels }}</span>
                            @endif
                            @if($item->appointment_type === 'tattoo' && $item->tattoo_type)
                                <span class="badge-pill">{{ $tattooTypeLabels[$item->tattoo_type] ?? $item->tattoo_type }}</span>
                            @endif
                            @if($item->appointment_type === 'tattoo' && $item->payment_method)
                                <span class="badge-pill">{{ $paymentMethodLabels[$item->payment_method] ?? $item->payment_method }}</span>
                            @endif
                            @if($item->pickup_required)
                                <span class="badge-pill">Pick up var</span>
                            @endif
                            @if($item->driver_status)
                                <span class="badge-pill">{{ $driverLabels[$item->driver_status] ?? $item->driver_status }}</span>
                            @endif
                            @if($item->is_old_customer)
                                <span class="badge-pill">Eski müşteri</span>
                            @endif
                        </div>
                        @if($item->customer_notes || $item->notes)
                            <div style="display:grid;gap:0.5rem;margin-top:0.85rem">
                                @if($item->customer_notes)
                                    <div style="padding:0.75rem;border:1px solid var(--border);border-radius:0.8rem;color:var(--text-muted);font-size:0.82rem">
                                        <strong style="color:var(--text-main)">Müşteri Notu:</strong> {{ $item->customer_notes }}
                                    </div>
                                @endif
                                @if($item->notes)
                                    <div style="padding:0.75rem;border:1px solid var(--border);border-radius:0.8rem;color:var(--text-muted);font-size:0.82rem">
                                        <strong style="color:var(--text-main)">Not:</strong> {{ $item->notes }}
                                    </div>
                                @endif
                            </div>
                        @endif
                        @if($images->isNotEmpty())
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(86px,1fr));gap:0.55rem;margin-top:0.85rem">
                                @foreach($images as $url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener" style="display:block;border-radius:0.8rem;overflow:hidden;border:1px solid var(--border);background:var(--surface-soft);aspect-ratio:1/1">
                                        <img src="{{ $url }}" alt="Randevu görseli" style="width:100%;height:100%;object-fit:cover">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="empty-state">Bu telefon numarasıyla kayıt bulunamadı.</div>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
