@extends('admin.layout', ['title' => 'Randevu Detayı'])

@section('content')
    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 mb-5" style="font-size:0.82rem">
        <a href="{{ route('admin.appointments.index') }}" class="button-ghost" style="padding:0.4rem 0.8rem;font-size:0.78rem">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Randevular
        </a>
        <span style="color:var(--text-subtle)">›</span>
        <span style="color:var(--text-muted)">Detay</span>
    </div>

    {{-- Hero başlık --}}
    <section class="hero-card mb-5">
        <div class="section-eyebrow">Randevu Kaydı</div>
        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">
                    {{ $appointment->customer->first_name ?? $appointment->first_name }}
                    {{ $appointment->customer->last_name ?? $appointment->last_name }}
                </h1>
                <p class="mt-2 text-sm" style="color:var(--text-muted)">
                    {{ optional($appointment->appointment_at)->format('d.m.Y — H:i') }}
                    @if($appointment->studio?->name)
                        &middot; {{ $appointment->studio->name }}
                    @endif
                </p>
            </div>
            @php
                $statusMap = [
                    'completed'   => ['label' => 'Tamamlandı',        'class' => 'badge-pill--success'],
                    'confirmed'   => ['label' => 'Onaylandı',         'class' => 'badge-pill--info'],
                    'pending'     => ['label' => 'Bekliyor',          'class' => 'badge-pill--warning'],
                    'cancelled'   => ['label' => 'İptal',             'class' => 'badge-pill--danger'],
                    'rescheduled' => ['label' => 'Yeniden Planlandı', 'class' => 'badge-pill--warning'],
                ];
                $statusInfo = $statusMap[$appointment->status] ?? ['label' => $appointment->status, 'class' => ''];
            @endphp
            <span class="badge-pill {{ $statusInfo['class'] }}" style="font-size:0.72rem;padding:0.38rem 0.85rem">
                {{ $statusInfo['label'] }}
            </span>
        </div>
    </section>

    {{-- İki sütunlu detay --}}
    <div class="grid gap-5 lg:grid-cols-2">

        {{-- Randevu bilgileri --}}
        <div class="panel-card">
            <div class="section-eyebrow">Randevu Bilgileri</div>
            <h2 class="mt-2 section-title">Detaylar</h2>

            <div class="mt-5 detail-grid">
                <div class="detail-row">
                    <span class="detail-label">Randevu Tipi</span>
                    <span class="detail-value">{{ $appointment->appointment_type }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tarih</span>
                    <span class="detail-value">{{ optional($appointment->appointment_at)->format('d.m.Y') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Saat</span>
                    <span class="detail-value">{{ optional($appointment->appointment_at)->format('H:i') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Yer</span>
                    <span class="detail-value">{{ $appointment->place ?: '—' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Durum</span>
                    <span class="detail-value">{{ $statusInfo['label'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Stüdyo</span>
                    <span class="detail-value">{{ $appointment->studio?->name ?: '—' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Oda No</span>
                    <span class="detail-value">{{ $appointment->room_number ?: '—' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Telefon</span>
                    <span class="detail-value">{{ $appointment->phone_number ?: '—' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Kişi Sayısı</span>
                    <span class="detail-value">{{ $appointment->pax }}</span>
                </div>
            </div>
        </div>

        {{-- Ekip & notlar --}}
        <div class="panel-card">
            <div class="section-eyebrow">Ekip Bilgisi</div>
            <h2 class="mt-2 section-title">Atamalar &amp; Notlar</h2>

            <div class="mt-5 detail-grid">
                <div class="detail-row">
                    <span class="detail-label">Randevuyu Alan</span>
                    <span class="detail-value">
                        {{ trim(($appointment->createdBy?->name ?? '') . ' ' . ($appointment->createdBy?->surname ?? '')) ?: '—' }}
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Sürücü</span>
                    <span class="detail-value">
                        {{ trim(($appointment->assignedDriver?->name ?? '') . ' ' . ($appointment->assignedDriver?->surname ?? '')) ?: '—' }}
                    </span>
                </div>
            </div>

            @if($appointment->notes)
                <div class="mt-5">
                    <div class="field-label mb-2">Notlar</div>
                    <div class="list-card text-sm leading-relaxed" style="color:var(--text-muted)">
                        {{ $appointment->notes }}
                    </div>
                </div>
            @endif
        </div>

    </div>
@endsection
