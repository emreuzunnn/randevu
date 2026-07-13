@extends('admin.layout', ['title' => 'Dashboard'])

@section('content')
    @php
        $chartMax = max(1, collect($periodReports ?? [])->flatMap(fn ($report) => [
            $report['total'] ?? 0,
            $report['completed'] ?? 0,
            $report['active'] ?? 0,
            $report['cancelled'] ?? 0,
        ])->max() ?: 1);

        $metricCards = [
            ['label' => 'Toplam Kayıt', 'value' => $summary['total_appointments'] ?? 0, 'helper' => 'Tüm randevu ve biletler', 'tone' => 'blue', 'icon' => 'calendar'],
            ['label' => 'İptal Edilen', 'value' => $summary['cancelled_appointments'] ?? 0, 'helper' => 'İptal durumundaki kayıtlar', 'tone' => 'red', 'icon' => 'alert'],
            ['label' => 'Transfer Görevi', 'value' => $summary['transfer_count'] ?? 0, 'helper' => 'Pickup işaretli kayıtlar', 'tone' => 'teal', 'icon' => 'route'],
        ];

        $barItems = [
            ['key' => 'total', 'label' => 'Toplam', 'color' => 'var(--accent)'],
            ['key' => 'completed', 'label' => 'Tamamlanan', 'color' => 'var(--success)'],
            ['key' => 'active', 'label' => 'Aktif', 'color' => 'var(--warning)'],
            ['key' => 'cancelled', 'label' => 'İptal', 'color' => 'var(--danger)'],
        ];
    @endphp

    <div class="admin-dashboard-page">
    <div class="page-hero dashboard-hero">
        <div class="dashboard-hero__copy">
            <div class="section-eyebrow" style="margin-bottom:0.4rem">Şirket Yönetimi</div>
            <h1 class="page-hero-title">Operasyon Merkezi</h1>
            <p class="page-hero-subtitle">Randevu, bilet, transfer ve stüdyo performansını tek ekranda takip edin.</p>
        </div>
        <div class="dashboard-hero__status">
            <span class="badge-pill badge-pill--success">
                <span class="state-dot state-dot--success"></span> Canlı görünüm
            </span>
            <span class="dashboard-hero__date">{{ now()->format('d.m.Y H:i') }}</span>
        </div>
    </div>

    <div class="business-command-bar dashboard-command-bar">
        <div class="business-command-bar__label">
            <span class="section-eyebrow">Hızlı İşlemler</span>
            <span>Günlük yönetim akışlarına doğrudan erişin</span>
        </div>
        <div class="action-row">
            @if(auth()->user()?->hasRole(\App\Enums\UserRole::Admin))
                <a href="{{ route('admin.companies.index') }}" class="button-secondary">Şirketler</a>
            @endif
            @if(auth()->user()?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici, \App\Enums\UserRole::Supervisor]))
                <a href="{{ route('admin.studios.index') }}" class="button-secondary">Stüdyolar</a>
            @endif
            <a href="{{ route('admin.appointments.index') }}" class="button-primary">Randevu / Biletleri Aç</a>
        </div>
    </div>

    <div class="metric-grid dashboard-metric-grid">
        @foreach($metricCards as $card)
            <article class="metric-card dashboard-metric-card dashboard-metric-card--{{ $card['tone'] }}">
                <div class="dashboard-metric-card__top">
                    <div class="dashboard-metric-card__icon" aria-hidden="true">
                        @if($card['icon'] === 'calendar')
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>
                            </svg>
                        @elseif($card['icon'] === 'alert')
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>
                            </svg>
                        @else
                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="6" cy="19" r="3"/><circle cx="18" cy="5" r="3"/><path d="M8.6 17.4 15.4 6.6"/>
                            </svg>
                        @endif
                    </div>
                    <span>{{ $card['label'] }}</span>
                </div>
                <div class="metric-card__value">
                    {{ number_format((int) $card['value'], 0, ',', '.') }}
                </div>
                <div class="metric-card__helper">{{ $card['helper'] }}</div>
            </article>
        @endforeach
    </div>

    <div class="panel-card dashboard-period-chart dashboard-period-chart--admin">
        <div class="dashboard-period-chart__head">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.3rem">Rapor Grafiği</div>
                <div class="section-title">Günlük / Aylık / Yıllık</div>
            </div>
            <div class="dashboard-period-chart__legend">
                @foreach($barItems as $item)
                    <span><i style="background:{{ $item['color'] }}"></i>{{ $item['label'] }}</span>
                @endforeach
            </div>
        </div>
        <div class="dashboard-period-chart__plot">
            @forelse($periodReports as $report)
                <div class="dashboard-period-group">
                    <div class="dashboard-period-group__bars">
                        @foreach($barItems as $item)
                            @php
                                $value = (int) ($report[$item['key']] ?? 0);
                                $height = $value > 0 ? max(18, (int) round(($value / $chartMax) * 220)) : 8;
                            @endphp
                            <div class="dashboard-period-bar-wrap" title="{{ $report['label'] }} {{ $item['label'] }}: {{ $value }}">
                                <span>{{ number_format($value, 0, ',', '.') }}</span>
                                <div class="dashboard-period-bar" style="height:{{ $height }}px;background:{{ $item['color'] }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="dashboard-period-group__label">
                        <strong>{{ $report['label'] }}</strong>
                        <span>{{ $report['date_from'] }} - {{ $report['date_to'] }}</span>
                    </div>
                </div>
            @empty
                <div class="empty-state" style="padding:1.5rem;border:none">Rapor grafiği için kayıt bulunmuyor.</div>
            @endforelse
        </div>
    </div>

    <div class="dashboard-bottom-grid">
        <div class="panel-card dashboard-table-card">
            <div class="dashboard-card-head">
                <div>
                    <div class="section-eyebrow" style="margin-bottom:0.3rem">Stüdyo</div>
                    <div class="section-title">Stüdyo Performansı</div>
                </div>
                <span class="badge-pill">{{ $studios->count() }} lokasyon</span>
            </div>
            <div class="table-shell">
                <table>
                    <thead>
                        <tr>
                            <th>Stüdyo Adı</th>
                            <th>Konum</th>
                            <th>Randevu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($studios as $studio)
                            <tr>
                                <td style="font-weight:600">{{ $studio->name }}</td>
                                <td style="color:var(--text-muted)">{{ $studio->location ?: '—' }}</td>
                                <td style="font-weight:600">{{ $studio->appointments_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" style="color:var(--text-muted);text-align:center;padding:1.5rem">Stüdyo bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel-card dashboard-table-card">
            <div class="dashboard-card-head">
                <div>
                    <div class="section-eyebrow" style="margin-bottom:0.3rem">Son Kayıtlar</div>
                    <div class="section-title">Randevu / Bilet Akışı</div>
                </div>
                <span class="badge-pill badge-pill--warning">{{ $recentAppointments->count() }} kayıt</span>
            </div>
            <div class="list-stack">
                @forelse($recentAppointments as $appointment)
                    <div class="list-card" style="padding:0.7rem 0.85rem">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem">
                            <div>
                                <div style="font-weight:600;font-size:0.845rem;color:var(--text-main)">
                                    {{ trim($appointment->first_name.' '.$appointment->last_name) ?: 'Müşteri' }}
                                </div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-muted)">
                                    {{ $appointment->hotel_name ?: ($appointment->studio?->name ?: '—') }}
                                </div>
                                <div style="margin-top:0.2rem;font-size:0.7rem;color:var(--text-subtle)">
                                    {{ optional($appointment->appointment_at)->format('d.m.Y H:i') }}
                                </div>
                            </div>
                            <span class="badge-pill" style="font-size:0.65rem;flex-shrink:0">{{ ucfirst($appointment->status) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="empty-state" style="padding:1.5rem;border:none">Kayıt bulunmuyor.</div>
                @endforelse
            </div>
        </div>
    </div>
    </div>
@endsection
