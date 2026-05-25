@extends('admin.layout', ['title' => 'Bildirim Logları'])

@section('content')
    @php
        $statusClasses = [
            'sent' => 'badge-pill--success',
            'failed' => 'badge-pill--danger',
            'skipped' => 'badge-pill--warning',
        ];

        $statusLabels = [
            'sent' => 'Gönderildi',
            'failed' => 'Hata',
            'skipped' => 'Atlandı',
        ];
    @endphp

    <section class="hero-card">
        <div class="section-eyebrow">Push Bildirimleri</div>
        <div class="mt-2 text-2xl font-bold" style="color:var(--text-main)">Bildirim logları</div>
        <p class="mt-2 max-w-3xl text-sm leading-6" style="color:var(--text-muted)">
            Kullanıcıya kaydedilen bildirimler ve FCM teslimat denemeleri burada tutulur. iOS, Android ve web tokenlarında
            hata dönerse Firebase status ve hata kodu bu ekrandan kontrol edilir.
        </p>
    </section>

    <section class="metric-grid">
        <article class="metric-card">
            <div class="section-eyebrow">Toplam</div>
            <div class="mt-2 text-2xl font-bold" style="color:var(--text-main)">{{ number_format($summary['total']) }}</div>
            <div class="mt-1 text-xs" style="color:var(--text-subtle)">Teslimat kaydı</div>
        </article>
        <article class="metric-card">
            <div class="section-eyebrow">Başarılı</div>
            <div class="mt-2 text-2xl font-bold" style="color:var(--text-main)">{{ number_format($summary['sent']) }}</div>
            <div class="mt-1 text-xs" style="color:var(--text-subtle)">FCM kabul etti</div>
        </article>
        <article class="metric-card">
            <div class="section-eyebrow">Hatalı</div>
            <div class="mt-2 text-2xl font-bold" style="color:var(--text-main)">{{ number_format($summary['failed']) }}</div>
            <div class="mt-1 text-xs" style="color:var(--text-subtle)">FCM veya istek hatası</div>
        </article>
        <article class="metric-card">
            <div class="section-eyebrow">Bugün Hata</div>
            <div class="mt-2 text-2xl font-bold" style="color:var(--text-main)">{{ number_format($summary['failed_today']) }}</div>
            <div class="mt-1 text-xs" style="color:var(--text-subtle)">Günlük kontrol</div>
        </article>
    </section>

    <section class="panel-card">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="section-title">Kayıtlar</div>
                <div class="mt-1 text-sm" style="color:var(--text-muted)">Son bildirim teslimat hareketleri</div>
            </div>
            <form method="GET" action="{{ route('admin.notifications.index') }}" class="action-row" style="align-items:flex-end">
                <label class="field-wrap" style="min-width: 150px">
                    <span class="field-label">Durum</span>
                    <select name="status" class="field-select">
                        <option value="">Tümü</option>
                        @foreach(['sent' => 'Gönderildi', 'failed' => 'Hata', 'skipped' => 'Atlandı'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-wrap" style="min-width: 150px">
                    <span class="field-label">Platform</span>
                    <select name="platform" class="field-select">
                        <option value="">Tümü</option>
                        @foreach($platforms as $platform)
                            <option value="{{ $platform }}" @selected($filters['platform'] === $platform)>{{ strtoupper($platform) }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-wrap" style="min-width: 190px">
                    <span class="field-label">Tip</span>
                    <select name="type" class="field-select">
                        <option value="">Tümü</option>
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="field-wrap" style="min-width: 220px">
                    <span class="field-label">Arama</span>
                    <input name="q" class="field-input" value="{{ $filters['q'] }}" placeholder="Kullanıcı, başlık, hata kodu">
                </label>

                <button class="button-primary" type="submit">Filtrele</button>
                <a href="{{ route('admin.notifications.index') }}" class="button-ghost">Temizle</a>
            </form>
        </div>

        <div class="mt-5 table-shell">
            <table>
                <thead>
                    <tr>
                        <th>Zaman</th>
                        <th>Kullanıcı</th>
                        <th>Bildirim</th>
                        <th>Platform</th>
                        <th>Durum</th>
                        <th>FCM</th>
                        <th>Hata</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $notification = $log->notification;
                            $user = $log->user;
                            $statusClass = $statusClasses[$log->status] ?? '';
                            $statusLabel = $statusLabels[$log->status] ?? $log->status;
                            $payload = $notification?->data ?? [];
                        @endphp
                        <tr>
                            <td style="white-space:nowrap">
                                <div class="text-sm font-semibold" style="color:var(--text-main)">
                                    {{ $log->attempted_at?->format('d.m.Y H:i') ?? '-' }}
                                </div>
                                <div class="mt-1 text-xs" style="color:var(--text-subtle)">
                                    {{ $log->attempted_at?->diffForHumans() ?? '' }}
                                </div>
                            </td>
                            <td>
                                <div class="text-sm font-semibold" style="color:var(--text-main)">
                                    {{ $user?->fullName() ?: $user?->name ?: 'Silinmiş kullanıcı' }}
                                </div>
                                <div class="mt-1 text-xs" style="color:var(--text-subtle)">
                                    {{ $user?->email ?? '-' }}
                                </div>
                            </td>
                            <td style="min-width: 260px">
                                <div class="text-sm font-semibold" style="color:var(--text-main)">
                                    {{ $notification?->title ?? 'Ham FCM gönderimi' }}
                                </div>
                                <div class="mt-1 text-xs leading-5" style="color:var(--text-muted)">
                                    {{ \Illuminate\Support\Str::limit($notification?->body ?? '-', 110) }}
                                </div>
                                <div class="mt-2 action-row">
                                    @if($notification?->type)
                                        <span class="badge-pill badge-pill--info">{{ $notification->type }}</span>
                                    @endif
                                    @if($notification?->read_at)
                                        <span class="badge-pill badge-pill--success">Okundu</span>
                                    @elseif($notification)
                                        <span class="badge-pill">Okunmadı</span>
                                    @endif
                                </div>
                                @if(! empty($payload))
                                    <details class="mt-2 text-xs" style="color:var(--text-subtle)">
                                        <summary>Payload</summary>
                                        <pre class="mt-2" style="white-space:pre-wrap">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @endif
                            </td>
                            <td>
                                <span class="badge-pill">{{ strtoupper($log->platform ?? 'unknown') }}</span>
                            </td>
                            <td>
                                <span class="badge-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td>
                                <div class="text-sm font-semibold" style="color:var(--text-main)">{{ $log->fcm_status ?? '-' }}</div>
                                @if($log->fcm_message_id)
                                    <div class="mt-1 text-xs" style="color:var(--text-subtle)">
                                        {{ \Illuminate\Support\Str::limit($log->fcm_message_id, 42) }}
                                    </div>
                                @endif
                            </td>
                            <td style="min-width: 220px">
                                @if($log->fcm_error_code)
                                    <span class="badge-pill badge-pill--danger">{{ $log->fcm_error_code }}</span>
                                @endif
                                <div class="mt-1 text-xs leading-5" style="color:var(--text-muted)">
                                    {{ \Illuminate\Support\Str::limit($log->error_message ?? '-', 130) }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">Henüz bildirim teslimat logu bulunmuyor.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-5">
            {{ $logs->links() }}
        </div>
    </section>
@endsection
