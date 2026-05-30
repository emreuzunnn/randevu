@extends('admin.layout', ['title' => 'Bildirim Logları'])

@section('content')
    @php
        $statusClasses = [
            'sent'    => 'badge-pill--success',
            'failed'  => 'badge-pill--danger',
            'skipped' => 'badge-pill--warning',
        ];
        $statusLabels = [
            'sent'    => 'Gönderildi',
            'failed'  => 'Hata',
            'skipped' => 'Atlandı',
        ];
    @endphp

    {{-- Page header --}}
    <div class="hero-card" style="margin-bottom:0">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.5rem">Push Bildirimleri</div>
                <h1 class="page-hero-title" style="font-size:1.5rem">Bildirim Teslimat Logları</h1>
                <p class="page-hero-desc" style="margin-top:0.5rem;font-size:0.8rem">
                    FCM teslimat denemeleri, platform bazlı token hataları ve durum kayıtları burada tutulur.
                </p>
            </div>
            <span class="badge-pill badge-pill--info" style="align-self:flex-start">Sistem Logları</span>
        </div>
    </div>

    {{-- Metrics --}}
    <div class="metric-grid">
        <article class="metric-card">
            <div class="section-eyebrow">Toplam</div>
            <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;color:var(--text-main);letter-spacing:-0.02em">
                {{ number_format($summary['total']) }}
            </div>
            <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-subtle)">Teslimat kaydı</div>
        </article>
        <article class="metric-card animate-stagger-1">
            <div class="section-eyebrow" style="color:var(--success)">Başarılı</div>
            <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;color:var(--text-main);letter-spacing:-0.02em">
                {{ number_format($summary['sent']) }}
            </div>
            <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-subtle)">FCM kabul etti</div>
        </article>
        <article class="metric-card animate-stagger-2">
            <div class="section-eyebrow" style="color:var(--danger)">Hatalı</div>
            <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;color:var(--text-main);letter-spacing:-0.02em">
                {{ number_format($summary['failed']) }}
            </div>
            <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-subtle)">FCM veya istek hatası</div>
        </article>
        <article class="metric-card animate-stagger-3">
            <div class="section-eyebrow" style="color:var(--warning)">Bugün Hata</div>
            <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;color:var(--text-main);letter-spacing:-0.02em">
                {{ number_format($summary['failed_today']) }}
            </div>
            <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-subtle)">Günlük kontrol</div>
        </article>
    </div>

    {{-- Filter + Table --}}
    <div class="panel-card">

        {{-- Filters --}}
        <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
            <div>
                <div class="section-title">Kayıtlar</div>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-top:0.2rem">Son bildirim teslimat hareketleri</div>
            </div>
            <form method="GET" action="{{ route('admin.notifications.index') }}" class="action-row" style="align-items:flex-end;flex-wrap:wrap">
                <div class="field-wrap" style="min-width:140px">
                    <label class="field-label">Durum</label>
                    <select name="status" class="field-select" style="font-size:0.8rem;padding:0.5rem 0.7rem">
                        <option value="">Tümü</option>
                        @foreach(['sent' => 'Gönderildi', 'failed' => 'Hata', 'skipped' => 'Atlandı'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-wrap" style="min-width:130px">
                    <label class="field-label">Platform</label>
                    <select name="platform" class="field-select" style="font-size:0.8rem;padding:0.5rem 0.7rem">
                        <option value="">Tümü</option>
                        @foreach($platforms as $platform)
                            <option value="{{ $platform }}" @selected($filters['platform'] === $platform)>{{ strtoupper($platform) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-wrap" style="min-width:180px">
                    <label class="field-label">Tip</label>
                    <select name="type" class="field-select" style="font-size:0.8rem;padding:0.5rem 0.7rem">
                        <option value="">Tümü</option>
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-wrap" style="min-width:210px">
                    <label class="field-label">Arama</label>
                    <input name="q" class="field-input" style="font-size:0.8rem;padding:0.5rem 0.7rem" value="{{ $filters['q'] }}" placeholder="Kullanıcı, başlık, hata kodu">
                </div>
                <button class="button-primary" type="submit" style="padding:0.5rem 0.9rem;font-size:0.8rem">Filtrele</button>
                <a href="{{ route('admin.notifications.index') }}" class="button-ghost" style="padding:0.5rem 0.9rem;font-size:0.8rem">Temizle</a>
            </form>
        </div>

        <div class="table-shell">
            <table>
                <thead>
                    <tr>
                        <th>Zaman</th>
                        <th>Kullanıcı</th>
                        <th>Bildirim</th>
                        <th>Platform</th>
                        <th>Durum</th>
                        <th>FCM Status</th>
                        <th>Hata</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $notification = $log->notification;
                            $user         = $log->user;
                            $payload      = $notification?->data ?? [];
                        @endphp
                        <tr>
                            <td style="white-space:nowrap;min-width:130px">
                                <div style="font-weight:600;color:var(--text-main);font-size:0.82rem">
                                    {{ $log->attempted_at?->format('d.m.Y H:i') ?? '—' }}
                                </div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-subtle)">
                                    {{ $log->attempted_at?->diffForHumans() ?? '' }}
                                </div>
                            </td>
                            <td style="min-width:160px">
                                <div style="font-weight:600;color:var(--text-main);font-size:0.82rem">
                                    {{ $user?->fullName() ?: $user?->name ?: 'Silinmiş kullanıcı' }}
                                </div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-subtle)">
                                    {{ $user?->email ?? '—' }}
                                </div>
                            </td>
                            <td style="min-width:240px">
                                <div style="font-weight:600;color:var(--text-main);font-size:0.82rem">
                                    {{ $notification?->title ?? 'Ham FCM gönderimi' }}
                                </div>
                                <div style="margin-top:0.25rem;font-size:0.75rem;line-height:1.5;color:var(--text-muted)">
                                    {{ \Illuminate\Support\Str::limit($notification?->body ?? '—', 100) }}
                                </div>
                                <div style="margin-top:0.5rem;display:flex;flex-wrap:wrap;gap:0.35rem">
                                    @if($notification?->type)
                                        <span class="badge-pill badge-pill--info" style="font-size:0.62rem">{{ $notification->type }}</span>
                                    @endif
                                    @if($notification?->read_at)
                                        <span class="badge-pill badge-pill--success" style="font-size:0.62rem">Okundu</span>
                                    @elseif($notification)
                                        <span class="badge-pill" style="font-size:0.62rem">Okunmadı</span>
                                    @endif
                                </div>
                                @if(! empty($payload))
                                    <details style="margin-top:0.5rem;font-size:0.72rem;color:var(--text-subtle)">
                                        <summary style="cursor:pointer;color:var(--text-muted)">Payload</summary>
                                        <pre style="margin-top:0.4rem;white-space:pre-wrap;font-size:0.68rem;line-height:1.5">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @endif
                            </td>
                            <td>
                                <span class="badge-pill" style="font-size:0.65rem">{{ strtoupper($log->platform ?? 'unknown') }}</span>
                            </td>
                            <td>
                                <span class="badge-pill {{ $statusClasses[$log->status] ?? '' }}" style="font-size:0.65rem">
                                    {{ $statusLabels[$log->status] ?? $log->status }}
                                </span>
                            </td>
                            <td style="min-width:140px">
                                <div style="font-weight:600;color:var(--text-main);font-size:0.82rem">{{ $log->fcm_status ?? '—' }}</div>
                                @if($log->fcm_message_id)
                                    <div style="margin-top:0.2rem;font-size:0.68rem;color:var(--text-subtle)">
                                        {{ \Illuminate\Support\Str::limit($log->fcm_message_id, 38) }}
                                    </div>
                                @endif
                            </td>
                            <td style="min-width:200px">
                                @if($log->fcm_error_code)
                                    <span class="badge-pill badge-pill--danger" style="font-size:0.62rem">{{ $log->fcm_error_code }}</span>
                                @endif
                                <div style="margin-top:0.3rem;font-size:0.75rem;line-height:1.5;color:var(--text-muted)">
                                    {{ \Illuminate\Support\Str::limit($log->error_message ?? '—', 120) }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state" style="border:none;border-radius:0;padding:2rem">
                                    Henüz bildirim teslimat logu bulunmuyor.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:1rem">
            {{ $logs->links() }}
        </div>

    </div>

@endsection
