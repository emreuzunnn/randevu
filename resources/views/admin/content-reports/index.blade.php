@extends('admin.layout', ['title' => 'Şikayetler'])

@section('content')
    @php
        $reasonLabels = [
            'profanity'              => 'Küfür',
            'harassment'             => 'Taciz',
            'inappropriate_content'  => 'Uygunsuz İçerik',
            'spam'                   => 'Spam',
            'other'                  => 'Diğer',
        ];
    @endphp

    <div class="hero-card" style="margin-bottom:0">
        <div class="section-eyebrow" style="margin-bottom:0.5rem">İçerik Denetimi</div>
        <h1 class="page-hero-title" style="font-size:1.5rem">Şikayet Yönetimi</h1>
        <p class="page-hero-desc" style="margin-top:0.5rem;font-size:0.8rem">
            Kullanıcı profilleri ve yorumlar için gönderilen şikayetleri buradan inceleyebilirsiniz.
        </p>
    </div>

    <div class="metric-grid">
        <article class="metric-card">
            <div class="section-eyebrow">Toplam</div>
            <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;color:var(--text-main)">{{ number_format($summary['total']) }}</div>
        </article>
        <article class="metric-card">
            <div class="section-eyebrow" style="color:var(--warning)">Bekleyen</div>
            <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;color:var(--text-main)">{{ number_format($summary['pending']) }}</div>
        </article>
        <article class="metric-card">
            <div class="section-eyebrow" style="color:var(--success)">İncelenen</div>
            <div style="margin-top:0.6rem;font-size:2rem;font-weight:800;color:var(--text-main)">{{ number_format($summary['resolved']) }}</div>
        </article>
    </div>

    <div class="panel-card">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem">
            <div>
                <div class="section-title">Kayıtlar</div>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-top:0.2rem">En yeni şikayetler önce gösterilir</div>
            </div>
            <form method="GET" action="{{ route('admin.content-reports.index') }}" class="action-row" style="align-items:flex-end">
                <div class="field-wrap" style="min-width:160px">
                    <label class="field-label">Durum</label>
                    <select name="status" class="field-select" style="font-size:0.8rem;padding:0.5rem 0.7rem">
                        <option value="">Tümü</option>
                        <option value="pending" @selected($status === 'pending')>Bekliyor</option>
                        <option value="resolved" @selected($status === 'resolved')>İncelendi</option>
                    </select>
                </div>
                <button class="button-primary" type="submit" style="padding:0.5rem 0.9rem;font-size:0.8rem">Filtrele</button>
            </form>
        </div>

        <div class="table-shell">
            <table>
                <thead>
                    <tr>
                        <th>Zaman</th>
                        <th>Tür</th>
                        <th>Şikayet Eden</th>
                        <th>Şikayet Edilen</th>
                        <th>İçerik</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        @php
                            $reportedUser = $report->reportedUser ?? $report->review?->reviewer;
                        @endphp
                        <tr>
                            <td style="white-space:nowrap">{{ $report->created_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td>
                                <span class="badge-pill badge-pill--info">{{ $report->review_id ? 'Yorum' : 'Profil' }}</span>
                                <div style="margin-top:0.35rem;font-size:0.72rem;color:var(--text-muted)">{{ $reasonLabels[$report->reason] ?? $report->reason }}</div>
                            </td>
                            <td>
                                <div style="font-weight:600;color:var(--text-main)">{{ $report->reporter?->fullName() ?? 'Silinmiş kullanıcı' }}</div>
                                <div style="font-size:0.72rem;color:var(--text-subtle)">{{ $report->reporter?->email ?? '—' }}</div>
                            </td>
                            <td>
                                <div style="font-weight:600;color:var(--text-main)">{{ $reportedUser?->fullName() ?? 'Silinmiş kullanıcı' }}</div>
                                <div style="font-size:0.72rem;color:var(--text-subtle)">{{ $reportedUser?->email ?? '—' }}</div>
                                @if($reportedUser?->banned_at)
                                    <span class="badge-pill badge-pill--danger" style="margin-top:0.35rem">Banlı</span>
                                @endif
                            </td>
                            <td style="min-width:220px">
                                <div style="font-size:0.75rem;color:var(--text-muted)">{{ \Illuminate\Support\Str::limit($report->details ?? $report->review?->comment ?? '—', 150) }}</div>
                                @if($report->review?->image_path)
                                    <a href="{{ $report->review->image_path }}" target="_blank" rel="noopener" style="display:inline-block;margin-top:0.4rem;font-size:0.72rem;color:var(--accent)">Görseli aç</a>
                                @endif
                            </td>
                            <td>
                                <span class="badge-pill {{ $report->status === 'resolved' ? 'badge-pill--success' : 'badge-pill--warning' }}">
                                    {{ $report->status === 'resolved' ? 'İncelendi' : 'Bekliyor' }}
                                </span>
                            </td>
                            <td>
                                <div class="action-row" style="flex-wrap:wrap;min-width:160px">
                                    @if($report->status !== 'resolved')
                                        <form method="POST" action="{{ route('admin.content-reports.resolve', $report) }}">
                                            @csrf
                                            <button class="button-ghost" type="submit" style="padding:0.4rem 0.65rem;font-size:0.72rem">İncelendi</button>
                                        </form>
                                    @endif
                                    @if($reportedUser && ! $reportedUser->banned_at)
                                        <form method="POST" action="{{ route('admin.content-reports.ban', $reportedUser) }}">
                                            @csrf
                                            <button class="button-ghost" type="submit" style="padding:0.4rem 0.65rem;font-size:0.72rem;color:var(--danger)">Ban Ver</button>
                                        </form>
                                    @elseif($reportedUser)
                                        <form method="POST" action="{{ route('admin.content-reports.unban', $reportedUser) }}">
                                            @csrf
                                            <button class="button-ghost" type="submit" style="padding:0.4rem 0.65rem;font-size:0.72rem;color:var(--success)">Banı Kaldır</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"><div class="empty-state" style="border:none;border-radius:0;padding:2rem">Henüz şikayet yok.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:1rem">{{ $reports->links() }}</div>
    </div>
@endsection
