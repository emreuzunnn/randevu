@extends('admin.layout', ['title' => 'WhatsApp Mesajları'])

@section('content')
    @php
        $replyClasses = [
            'sent' => 'badge-pill--success',
            'failed' => 'badge-pill--danger',
            'skipped' => 'badge-pill--warning',
            'disabled' => 'badge-pill--info',
        ];
        $replyLabels = [
            'sent' => 'Cevaplandı',
            'failed' => 'Hata',
            'skipped' => 'Atlandı',
            'disabled' => 'Kapalı',
        ];
    @endphp

    <div class="hero-card" style="margin-bottom:0">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <div class="section-eyebrow" style="margin-bottom:0.5rem">WhatsApp Analiz</div>
                <h1 class="page-hero-title" style="font-size:1.5rem">Müşteri Mesajları</h1>
                <p class="page-hero-desc" style="margin-top:0.5rem;font-size:0.8rem">
                    Müşterilerin WhatsApp üzerinden gönderdiği cevaplar ve otomatik cevap durumları burada tutulur.
                </p>
            </div>
            <span class="badge-pill badge-pill--success" style="align-self:flex-start">Webhook Logları</span>
        </div>
    </div>

    <div class="metric-grid">
        <article class="metric-card">
            <div class="section-eyebrow">Toplam Mesaj</div>
            <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;color:var(--text-main);letter-spacing:-0.02em">
                {{ number_format($summary['total']) }}
            </div>
            <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-subtle)">Kayıtlı müşteri cevabı</div>
        </article>
        <article class="metric-card animate-stagger-1">
            <div class="section-eyebrow" style="color:var(--info)">Bugün</div>
            <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;color:var(--text-main);letter-spacing:-0.02em">
                {{ number_format($summary['today']) }}
            </div>
            <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-subtle)">Bugünkü cevaplar</div>
        </article>
        <article class="metric-card animate-stagger-2">
            <div class="section-eyebrow" style="color:var(--success)">Otomatik Cevap</div>
            <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;color:var(--text-main);letter-spacing:-0.02em">
                {{ number_format($summary['auto_replied']) }}
            </div>
            <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-subtle)">Başarıyla dönen cevap</div>
        </article>
        <article class="metric-card animate-stagger-3">
            <div class="section-eyebrow" style="color:var(--danger)">Hata</div>
            <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;color:var(--text-main);letter-spacing:-0.02em">
                {{ number_format($summary['failed']) }}
            </div>
            <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-subtle)">WhatsApp dönüş hatası</div>
        </article>
    </div>

    <div class="panel-card">
        <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:1.25rem">
            <div>
                <div class="section-title">Mesaj Kayıtları</div>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-top:0.2rem">Son WhatsApp müşteri cevapları</div>
            </div>
            <form method="GET" action="{{ route('admin.whatsapp-messages.index') }}" class="action-row" style="align-items:flex-end;flex-wrap:wrap">
                <div class="field-wrap" style="min-width:150px">
                    <label class="field-label">Mesaj Tipi</label>
                    <select name="type" class="field-select" style="font-size:0.8rem;padding:0.5rem 0.7rem">
                        <option value="">Tümü</option>
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected($filters['type'] === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field-wrap" style="min-width:150px">
                    <label class="field-label">Otomatik Cevap</label>
                    <select name="reply_status" class="field-select" style="font-size:0.8rem;padding:0.5rem 0.7rem">
                        <option value="">Tümü</option>
                        @foreach($replyStatuses as $status)
                            <option value="{{ $status }}" @selected($filters['reply_status'] === $status)>
                                {{ $replyLabels[$status] ?? $status }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field-wrap" style="min-width:140px">
                    <label class="field-label">Başlangıç</label>
                    <input type="date" name="date_from" class="field-input" style="font-size:0.8rem;padding:0.5rem 0.7rem" value="{{ $filters['date_from'] }}">
                </div>
                <div class="field-wrap" style="min-width:140px">
                    <label class="field-label">Bitiş</label>
                    <input type="date" name="date_to" class="field-input" style="font-size:0.8rem;padding:0.5rem 0.7rem" value="{{ $filters['date_to'] }}">
                </div>
                <div class="field-wrap" style="min-width:220px">
                    <label class="field-label">Arama</label>
                    <input name="q" class="field-input" style="font-size:0.8rem;padding:0.5rem 0.7rem" value="{{ $filters['q'] }}" placeholder="Telefon, isim, mesaj, hata">
                </div>
                <button class="button-primary" type="submit" style="padding:0.5rem 0.9rem;font-size:0.8rem">Filtrele</button>
                <a href="{{ route('admin.whatsapp-messages.index') }}" class="button-ghost" style="padding:0.5rem 0.9rem;font-size:0.8rem">Temizle</a>
            </form>
        </div>

        <div class="table-shell">
            <table>
                <thead>
                    <tr>
                        <th>Zaman</th>
                        <th>Müşteri</th>
                        <th>Mesaj</th>
                        <th>Tip</th>
                        <th>Otomatik Cevap</th>
                        <th>Payload</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td style="white-space:nowrap;min-width:130px">
                                <div style="font-weight:600;color:var(--text-main);font-size:0.82rem">
                                    {{ $log->received_at?->format('d.m.Y H:i') ?? $log->created_at?->format('d.m.Y H:i') ?? '—' }}
                                </div>
                                <div style="margin-top:0.2rem;font-size:0.72rem;color:var(--text-subtle)">
                                    {{ ($log->received_at ?? $log->created_at)?->diffForHumans() ?? '' }}
                                </div>
                            </td>
                            <td style="min-width:170px">
                                <div style="font-weight:700;color:var(--text-main);font-size:0.84rem">
                                    {{ $log->profile_name ?: 'İsimsiz müşteri' }}
                                </div>
                                <div style="margin-top:0.25rem;font-size:0.75rem;color:var(--text-muted)">
                                    {{ $log->from_phone ?: 'Numara yok' }}
                                </div>
                            </td>
                            <td style="min-width:280px;max-width:420px">
                                <div style="font-size:0.82rem;line-height:1.55;color:var(--text-main)">
                                    {{ $log->message_body ?: 'Metin içermeyen mesaj' }}
                                </div>
                                <div style="margin-top:0.35rem;font-size:0.68rem;color:var(--text-subtle)">
                                    ID: {{ \Illuminate\Support\Str::limit($log->message_id, 42) }}
                                </div>
                            </td>
                            <td>
                                <span class="badge-pill" style="font-size:0.65rem">{{ $log->message_type ?: 'unknown' }}</span>
                            </td>
                            <td style="min-width:190px">
                                <span class="badge-pill {{ $replyClasses[$log->auto_reply_status] ?? '' }}" style="font-size:0.65rem">
                                    {{ $replyLabels[$log->auto_reply_status] ?? ($log->auto_reply_status ?: 'Bekliyor') }}
                                </span>
                                @if($log->auto_replied_at)
                                    <div style="margin-top:0.3rem;font-size:0.72rem;color:var(--text-subtle)">
                                        {{ $log->auto_replied_at->format('d.m.Y H:i') }}
                                    </div>
                                @endif
                                @if($log->auto_reply_error)
                                    <div style="margin-top:0.4rem;font-size:0.72rem;line-height:1.5;color:var(--danger)">
                                        {{ \Illuminate\Support\Str::limit($log->auto_reply_error, 120) }}
                                    </div>
                                @endif
                            </td>
                            <td style="min-width:120px">
                                <details style="font-size:0.72rem;color:var(--text-subtle)">
                                    <summary style="cursor:pointer;color:var(--text-muted)">Detay</summary>
                                    <pre style="margin-top:0.4rem;max-width:360px;white-space:pre-wrap;font-size:0.68rem;line-height:1.5">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state" style="border:none;border-radius:0;padding:2rem">
                                    Henüz WhatsApp müşteri mesajı bulunmuyor.
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
