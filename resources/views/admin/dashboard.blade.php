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
            ['label' => 'Toplam Kayıt', 'value' => $summary['total_appointments'] ?? 0, 'helper' => 'Seçili kapsam', 'color' => ''],
            ['label' => 'İptal Edilen', 'value' => $summary['cancelled_appointments'] ?? 0, 'helper' => 'Operasyon riski', 'color' => 'var(--danger)'],
            ['label' => 'Transfer Görevi', 'value' => $summary['transfer_count'] ?? 0, 'helper' => 'Planlanan transfer', 'color' => 'var(--info)'],
        ];

        $barItems = [
            ['key' => 'total', 'label' => 'Toplam', 'color' => 'var(--accent)'],
            ['key' => 'completed', 'label' => 'Tamamlanan', 'color' => 'var(--success)'],
            ['key' => 'active', 'label' => 'Aktif', 'color' => 'var(--warning)'],
            ['key' => 'cancelled', 'label' => 'İptal', 'color' => 'var(--danger)'],
        ];
    @endphp

    <div class="page-hero">
        <div>
            <div class="section-eyebrow" style="margin-bottom:0.4rem">Şirket Yönetimi</div>
            <h1 class="page-hero-title">Operasyon Merkezi</h1>
            <p class="page-hero-subtitle">Stüdyoların ve randevu operasyonunun güncel görünümü.</p>
        </div>
        <span class="badge-pill badge-pill--success">
            <span class="state-dot state-dot--success"></span> Güncel
        </span>
    </div>

    <div class="business-command-bar">
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

    <div class="metric-grid">
        @foreach($metricCards as $card)
            <article class="metric-card">
                <div class="metric-card__meta">
                    <span>{{ $card['label'] }}</span>
                </div>
                <div class="metric-card__value" @if($card['color'] !== '') style="color:{{ $card['color'] }}" @endif>
                    {{ number_format((int) $card['value'], 0, ',', '.') }}
                </div>
                <div class="metric-card__helper">{{ $card['helper'] }}</div>
            </article>
        @endforeach
    </div>

    <div class="panel-card dashboard-period-chart">
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
                                $height = $value > 0 ? max(18, (int) round(($value / $chartMax) * 190)) : 8;
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

    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));align-items:start">
        <div class="panel-card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
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

        <div class="panel-card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
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
@endsection
