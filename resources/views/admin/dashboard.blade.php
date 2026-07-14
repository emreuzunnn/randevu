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
            ['label' => 'Toplam Kayıt', 'value' => $summary['total_appointments'] ?? 0, 'helper' => 'Randevu + bilet', 'tone' => 'blue', 'icon' => 'calendar'],
            ['label' => 'Toplam Randevu', 'value' => $summary['design_appointments'] ?? 0, 'helper' => 'Sadece tasarım', 'tone' => 'teal', 'icon' => 'calendar'],
            ['label' => 'Toplam Bilet', 'value' => $summary['ticket_appointments'] ?? 0, 'helper' => 'Dövme / piercing', 'tone' => 'blue', 'icon' => 'calendar'],
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

    <style>
        .admin-dashboard-page {
            display: grid !important;
            gap: 1rem !important;
        }

        .admin-dashboard-page .page-hero {
            display: flex !important;
            align-items: flex-start !important;
            justify-content: space-between !important;
            gap: 1.25rem !important;
            padding: 1.45rem 1.55rem !important;
            border: 1px solid #d8dee8 !important;
            border-radius: 10px !important;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 55%, #eef6ff 100%) !important;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08) !important;
        }

        .admin-dashboard-page .page-hero-subtitle {
            margin-top: 0.5rem !important;
            max-width: 48rem !important;
            color: var(--text-muted) !important;
            font-size: 0.9rem !important;
            line-height: 1.65 !important;
        }

        .admin-dashboard-page .dashboard-hero__status {
            display: grid !important;
            justify-items: end !important;
            gap: 0.55rem !important;
            flex-shrink: 0 !important;
        }

        .admin-dashboard-page .dashboard-hero__date {
            color: var(--text-subtle) !important;
            font-size: 0.72rem !important;
            font-weight: 700 !important;
        }

        .admin-dashboard-page .dashboard-metric-grid {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 1rem !important;
        }

        .admin-dashboard-page .dashboard-metric-card {
            min-height: 9.4rem !important;
            display: grid !important;
            align-content: space-between !important;
            gap: 1rem !important;
            padding: 1.2rem !important;
            overflow: hidden !important;
            position: relative !important;
            border: 1px solid rgba(203, 213, 225, 0.9) !important;
            border-radius: 12px !important;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
        }

        .admin-dashboard-page .dashboard-metric-card::before {
            content: '' !important;
            position: absolute !important;
            inset: 0 0 auto 0 !important;
            width: 100% !important;
            height: 4px !important;
            background: var(--metric-accent, var(--accent)) !important;
        }

        .admin-dashboard-page .dashboard-metric-card::after {
            content: '' !important;
            position: absolute !important;
            right: -2.7rem !important;
            bottom: -3.3rem !important;
            width: 8.8rem !important;
            height: 8.8rem !important;
            border-radius: 999px !important;
            background: var(--metric-soft, rgba(37, 99, 235, 0.1)) !important;
        }

        .admin-dashboard-page .dashboard-metric-card--blue {
            --metric-accent: var(--accent);
            --metric-soft: rgba(37, 99, 235, 0.12);
        }

        .admin-dashboard-page .dashboard-metric-card--red {
            --metric-accent: var(--danger);
            --metric-soft: rgba(220, 38, 38, 0.12);
        }

        .admin-dashboard-page .dashboard-metric-card--teal {
            --metric-accent: var(--teal);
            --metric-soft: rgba(15, 118, 110, 0.12);
        }

        .admin-dashboard-page .dashboard-metric-card__top {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 0.85rem !important;
            color: var(--text-muted) !important;
            font-size: 0.78rem !important;
            font-weight: 800 !important;
            position: relative !important;
            z-index: 1 !important;
        }

        .admin-dashboard-page .dashboard-metric-card__icon {
            width: 2.35rem !important;
            height: 2.35rem !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 10px !important;
            background: var(--metric-soft) !important;
            color: var(--metric-accent) !important;
            flex-shrink: 0 !important;
        }

        .admin-dashboard-page .dashboard-metric-card .metric-card__value {
            color: var(--text-main) !important;
            font-size: clamp(2.1rem, 4vw, 3.05rem) !important;
            font-weight: 850 !important;
            line-height: 0.95 !important;
            position: relative !important;
            z-index: 1 !important;
        }

        .admin-dashboard-page .dashboard-metric-card .metric-card__helper {
            color: var(--text-subtle) !important;
            font-size: 0.78rem !important;
            font-weight: 650 !important;
            position: relative !important;
            z-index: 1 !important;
        }

        .admin-dashboard-page .dashboard-period-chart {
            display: grid !important;
            gap: 1.35rem !important;
            padding: 1.35rem !important;
            border: 1px solid rgba(203, 213, 225, 0.95) !important;
            border-radius: 12px !important;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
            box-shadow: 0 22px 52px rgba(15, 23, 42, 0.09) !important;
            overflow: hidden !important;
        }

        .admin-dashboard-page .dashboard-period-chart__head {
            display: flex !important;
            align-items: flex-start !important;
            justify-content: space-between !important;
            gap: 1rem !important;
            flex-wrap: wrap !important;
        }

        .admin-dashboard-page .dashboard-period-chart__legend {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 0.55rem !important;
            flex-wrap: wrap !important;
        }

        .admin-dashboard-page .dashboard-period-chart__legend span {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.35rem !important;
            padding: 0.38rem 0.58rem !important;
            border: 1px solid #e5e9f0 !important;
            border-radius: 999px !important;
            background: #ffffff !important;
            color: var(--text-muted) !important;
            font-size: 0.72rem !important;
            font-weight: 750 !important;
        }

        .admin-dashboard-page .dashboard-period-chart__legend i {
            width: 0.58rem !important;
            height: 0.58rem !important;
            border-radius: 999px !important;
            display: inline-block !important;
        }

        .admin-dashboard-page .dashboard-period-chart__plot {
            min-height: 350px !important;
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 1.15rem !important;
            padding: 1.15rem !important;
            border: 1px solid #e5e9f0 !important;
            border-radius: 12px !important;
            background: repeating-linear-gradient(0deg, rgba(148, 163, 184, 0.12) 0 1px, transparent 1px 56px), linear-gradient(180deg, #ffffff 0%, #f8fafc 100%) !important;
        }

        .admin-dashboard-page .dashboard-period-group {
            display: grid !important;
            grid-template-rows: 1fr auto !important;
            gap: 0.85rem !important;
            padding: 0.9rem !important;
            border: 1px solid #e5e9f0 !important;
            border-radius: 12px !important;
            background: rgba(255, 255, 255, 0.78) !important;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07) !important;
            animation: revealUp 420ms ease both !important;
        }

        .admin-dashboard-page .dashboard-period-group__bars {
            min-height: 250px !important;
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 0.55rem !important;
            align-items: end !important;
            padding: 0.75rem 0.25rem 0 !important;
            border-bottom: 1px solid #d8dee8 !important;
        }

        .admin-dashboard-page .dashboard-period-bar-wrap {
            height: 238px !important;
            min-width: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-end !important;
            align-items: center !important;
            gap: 0.42rem !important;
        }

        .admin-dashboard-page .dashboard-period-bar-wrap span {
            color: var(--text-main) !important;
            font-size: 0.72rem !important;
            font-weight: 850 !important;
            line-height: 1 !important;
            white-space: nowrap !important;
        }

        .admin-dashboard-page .dashboard-period-bar {
            width: min(100%, 2.75rem) !important;
            min-height: 8px !important;
            border-radius: 0.9rem 0.9rem 0.35rem 0.35rem !important;
            transform-origin: bottom !important;
            animation: chartGrow 760ms cubic-bezier(0.2, 0.8, 0.2, 1) both !important;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.16), inset 0 1px 0 rgba(255, 255, 255, 0.42) !important;
        }

        .admin-dashboard-page .dashboard-period-group__label {
            display: grid !important;
            gap: 0.25rem !important;
            padding-top: 0.8rem !important;
            text-align: center !important;
        }

        .admin-dashboard-page .dashboard-period-group__label strong {
            color: var(--text-main) !important;
            font-size: 0.98rem !important;
        }

        .admin-dashboard-page .dashboard-period-group__label span {
            color: var(--text-muted) !important;
            font-size: 0.68rem !important;
        }

        .admin-dashboard-page .dashboard-bottom-grid {
            display: grid !important;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr) !important;
            gap: 1rem !important;
            align-items: start !important;
        }

        .admin-dashboard-page .dashboard-card-head {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 1rem !important;
            margin-bottom: 1.25rem !important;
        }

        @media (max-width: 980px) {
            .admin-dashboard-page .dashboard-metric-grid,
            .admin-dashboard-page .dashboard-period-chart__plot,
            .admin-dashboard-page .dashboard-bottom-grid {
                grid-template-columns: 1fr !important;
            }

            .admin-dashboard-page .page-hero,
            .admin-dashboard-page .dashboard-command-bar,
            .admin-dashboard-page .dashboard-card-head {
                flex-direction: column !important;
                align-items: stretch !important;
            }

            .admin-dashboard-page .dashboard-hero__status {
                justify-items: start !important;
            }
        }
    </style>

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
