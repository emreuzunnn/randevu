<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bilet Geçmişi</title>
    @vite(['resources/css/app.css'])
</head>
<body style="min-height:100vh;background:var(--bg);color:var(--text-main);padding:1.25rem">
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
                            <span class="badge-pill">{{ ucfirst($item->status ?? 'confirmed') }}</span>
                        </div>
                    </article>
                @empty
                    <div class="empty-state">Bu telefon numarasıyla kayıt bulunamadı.</div>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
