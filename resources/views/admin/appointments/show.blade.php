@extends('admin.layout', ['title' => 'Randevu Detayı'])

@section('content')

    {{-- Breadcrumb --}}
    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem">
        <a href="{{ route('admin.appointments.index') }}" class="button-ghost" style="padding:0.38rem 0.75rem;font-size:0.75rem;gap:0.3rem">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Randevular
        </a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-subtle)">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
        <span style="font-size:0.78rem;color:var(--text-muted)">Randevu Detayı</span>
    </div>

    {{-- Page header --}}
    <div class="hero-card" style="margin-bottom:1rem">
        @php
            $statusMap = [
                'completed'   => ['label' => 'Tamamlandı',        'class' => 'badge-pill--success'],
                'confirmed'   => ['label' => 'Aktif',             'class' => 'badge-pill--info'],
                'pending'     => ['label' => 'Bekliyor',          'class' => 'badge-pill--warning'],
                'cancelled'   => ['label' => 'İptal',             'class' => 'badge-pill--danger'],
                'rescheduled' => ['label' => 'Yeniden Planlandı', 'class' => 'badge-pill--warning'],
            ];
            $statusInfo = $statusMap[$appointment->status] ?? ['label' => $appointment->status, 'class' => ''];
        @endphp
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.5rem">Randevu Kaydı</div>
                <h1 class="page-hero-title" style="font-size:1.5rem">
                    {{ $appointment->customer->first_name ?? $appointment->first_name }}
                    {{ $appointment->customer->last_name ?? $appointment->last_name }}
                </h1>
                <div style="display:flex;align-items:center;gap:0.6rem;margin-top:0.5rem;flex-wrap:wrap">
                    <span style="font-size:0.8rem;color:var(--text-muted)">
                        {{ optional($appointment->appointment_at)->format('d.m.Y — H:i') }}
                    </span>
                    @if($appointment->studio?->name)
                        <span style="color:var(--text-subtle);font-size:0.8rem">·</span>
                        <span style="font-size:0.8rem;color:var(--text-muted)">{{ $appointment->studio->name }}</span>
                    @endif
                </div>
            </div>
            <span class="badge-pill {{ $statusInfo['class'] }}" style="font-size:0.72rem;padding:0.35rem 0.9rem;align-self:flex-start">
                {{ $statusInfo['label'] }}
            </span>
        </div>
    </div>

    {{-- Two-column detail layout --}}
    <div style="display:grid;gap:1rem" class="lg:grid-cols-2">

        {{-- Appointment info --}}
        <div class="panel-card">
            <div class="section-eyebrow" style="margin-bottom:0.4rem">Randevu Bilgileri</div>
            <div class="section-title" style="margin-bottom:1.25rem">Detaylar</div>

            <div class="detail-grid">
                @php
                    $typeLabels = [
                        'designer' => 'Tasarımcı Randevusu',
                        'tattoo'   => 'Dövme Randevusu',
                        'standard' => 'Standart',
                    ];
                @endphp
                <div class="detail-row">
                    <span class="detail-label">Randevu Tipi</span>
                    <span class="detail-value">{{ $typeLabels[$appointment->appointment_type] ?? $appointment->appointment_type }}</span>
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
                    <span class="detail-value">
                        <span class="badge-pill {{ $statusInfo['class'] }}" style="font-size:0.68rem">{{ $statusInfo['label'] }}</span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Pick up</span>
                    <span class="detail-value">{{ $appointment->pickup_required ? 'Gerekli' : 'Gerekli değil' }}</span>
                </div>
                @if($appointment->driver_status)
                    @php
                        $driverStatusMap = [
                            'picked_up'   => 'Müşteri Alındı',
                            'dropped_off' => 'Müşteri Bırakıldı',
                            'cancelled'   => 'İptal Edildi',
                        ];
                    @endphp
                    <div class="detail-row">
                        <span class="detail-label">Sürücü Durumu</span>
                        <span class="detail-value">{{ $driverStatusMap[$appointment->driver_status] ?? $appointment->driver_status }}</span>
                    </div>
                @endif
                <div class="detail-row">
                    <span class="detail-label">Stüdyo</span>
                    <span class="detail-value">{{ $appointment->studio?->name ?: '—' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Otel</span>
                    <span class="detail-value">{{ $appointment->hotel_name ?: '—' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Oda No</span>
                    <span class="detail-value">{{ $appointment->room_number ?: '—' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Telefon</span>
                    <span class="detail-value">
                        {{ $appointment->phone_country_code ? $appointment->phone_country_code . ' ' : '' }}{{ $appointment->phone_number ?: '—' }}
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Kişi Sayısı</span>
                    <span class="detail-value">{{ $appointment->pax }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Eski Müşteri</span>
                    <span class="detail-value">
                        @if($appointment->is_old_customer)
                            <span class="badge-pill badge-pill--info" style="font-size:0.65rem">Evet</span>
                        @else
                            <span style="color:var(--text-muted)">Hayır</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Team & notes --}}
        <div class="panel-card">
            <div class="section-eyebrow" style="margin-bottom:0.4rem">Ekip Bilgisi</div>
            <div class="section-title" style="margin-bottom:1.25rem">Atamalar &amp; Notlar</div>

            <div class="detail-grid">
                <div class="detail-row">
                    <span class="detail-label">Randevuyu Alan</span>
                    <span class="detail-value">
                        {{ trim(($appointment->createdBy?->name ?? '') . ' ' . ($appointment->createdBy?->surname ?? '')) ?: '—' }}
                    </span>
                </div>
                @if($appointment->assignedArtist)
                    <div class="detail-row">
                        <span class="detail-label">Artist</span>
                        <span class="detail-value">
                            {{ trim(($appointment->assignedArtist?->name ?? '') . ' ' . ($appointment->assignedArtist?->surname ?? '')) ?: '—' }}
                        </span>
                    </div>
                @endif
            </div>

            @if($appointment->customer_notes)
                <div style="margin-top:1.25rem">
                    <div class="field-label" style="margin-bottom:0.5rem">Müşteri Notu</div>
                    <div class="list-card" style="font-size:0.845rem;line-height:1.6;color:var(--text-muted)">
                        {{ $appointment->customer_notes }}
                    </div>
                </div>
            @endif

            @if($appointment->notes)
                <div style="margin-top:1rem">
                    <div class="field-label" style="margin-bottom:0.5rem">Operasyon Notu</div>
                    <div class="list-card" style="font-size:0.845rem;line-height:1.6;color:var(--text-muted)">
                        {{ $appointment->notes }}
                    </div>
                </div>
            @endif
            @if(!empty($appointment->tattoo_image_paths))
                <div style="margin-top:1rem">
                    <div class="field-label" style="margin-bottom:0.5rem">Dövme Görselleri</div>
                    <div style="display:flex;flex-wrap:wrap;gap:0.65rem">
                        @foreach($appointment->tattoo_image_paths as $imagePath)
                            <a href="{{ $imagePath }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $imagePath }}" alt="Dövme görseli" style="width:88px;height:88px;object-fit:cover;border-radius:0.65rem;border:1px solid var(--border)">
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
            @if($appointment->completed_tattoo_image_path)
                <div style="margin-top:1rem">
                    <div class="field-label" style="margin-bottom:0.5rem">Tamamlanan Dövme</div>
                    <a href="{{ $appointment->completed_tattoo_image_path }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ $appointment->completed_tattoo_image_path }}" alt="Tamamlanan dövme" style="width:120px;height:120px;object-fit:cover;border-radius:0.65rem;border:1px solid var(--border)">
                    </a>
                </div>
            @endif
        </div>

    </div>

@endsection
