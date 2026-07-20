<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş Yap — Randevu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..800;1,14..32,300..800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem">

    <div class="login-grid" style="width:100%;max-width:960px;display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:stretch">

        {{-- Left: brand panel --}}
        <div class="login-card login-brand-panel" style="padding:2.5rem;display:flex;flex-direction:column;justify-content:space-between">
            <div>
                {{-- Logo --}}
                <div style="display:flex;align-items:center;gap:0.7rem;margin-bottom:2.5rem">
                    <div style="width:2.4rem;height:2.4rem;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent-lo));display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(240,168,50,0.25);flex-shrink:0">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0C1220" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size:1.1rem;font-weight:800;letter-spacing:-0.02em;color:var(--text-main)">Randevu</div>
                        <div style="font-size:0.65rem;color:var(--text-muted);margin-top:0.05rem;letter-spacing:0.04em">YÖNETİM PANELİ</div>
                    </div>
                </div>

                <div class="section-eyebrow" style="margin-bottom:0.75rem">Kurumsal Operasyon Platformu</div>
                <h1 style="font-size:2rem;font-weight:800;letter-spacing:-0.03em;line-height:1.15;color:var(--text-main);margin-bottom:1rem">
                    Operasyonunuzu<br>tek merkezden<br>yönetin.
                </h1>
                <p style="font-size:0.845rem;line-height:1.7;color:var(--text-muted);max-width:36ch">
                    Şirketler, stüdyolar, personel ve randevu akışları tek panelde birleşir.
                </p>
            </div>

            {{-- Feature list --}}
            <div style="display:grid;gap:0.7rem;margin-top:2.5rem">
                @foreach([
                    ['Anlık takip', 'Randevu ve ekip hareketlerini canlı izleyin'],
                    ['Rol bazlı erişim', 'Admin, yönetici ve süpervizör kademeli yetki'],
                    ['Çoklu stüdyo', 'Şirket ve stüdyo ağını tek yerden denetleyin'],
                ] as [$title, $desc])
                <div style="display:flex;align-items:flex-start;gap:0.75rem">
                    <div style="width:1.4rem;height:1.4rem;border-radius:50%;background:var(--accent-dim);border:1px solid var(--accent-mid);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:0.1rem">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size:0.82rem;font-weight:600;color:var(--text-main)">{{ $title }}</div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.1rem">{{ $desc }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Right: login form --}}
        <div class="login-card" style="padding:2.5rem;display:flex;flex-direction:column;justify-content:center">
            <div style="margin-bottom:2rem">
                <div class="section-eyebrow" style="margin-bottom:0.6rem">Güvenli Giriş</div>
                <h2 style="font-size:1.5rem;font-weight:800;letter-spacing:-0.025em;color:var(--text-main)">Panele Giriş Yap</h2>
                <p style="font-size:0.8rem;color:var(--text-muted);margin-top:0.5rem;line-height:1.6">
                    Yetkili hesabınızla giriş yaparak operasyonunuzu yönetin.
                </p>
            </div>

            <form action="{{ route('admin.login.submit', [], false) }}" method="POST" class="form-grid">
                @csrf

                <div class="field-wrap">
                    <label class="field-label" for="email">E-posta Adresi</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        class="field-input"
                        placeholder="admin@example.com"
                        autocomplete="email"
                        autofocus
                    >
                    @error('email')
                        <div style="font-size:0.72rem;color:var(--danger);margin-top:0.1rem">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field-wrap">
                    <label class="field-label" for="password">Şifre</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        class="field-input"
                        placeholder="••••••••"
                        autocomplete="current-password"
                    >
                    @error('password')
                        <div style="font-size:0.72rem;color:var(--danger);margin-top:0.1rem">{{ $message }}</div>
                    @enderror
                </div>

                <button class="button-primary" type="submit" style="padding:0.7rem;margin-top:0.25rem;justify-content:center;font-size:0.845rem">
                    Giriş Yap
                </button>
            </form>

            <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--border)">
                <p style="font-size:0.72rem;color:var(--text-subtle);text-align:center;line-height:1.6">
                    Bu panel yalnızca yetkili kullanıcılara açıktır.<br>
                    Sorun yaşıyorsanız sistem yöneticinize başvurun.
                </p>
            </div>
        </div>

    </div>

    <style>
    @media (max-width: 720px) {
        .login-grid { grid-template-columns: 1fr !important; }
        .login-brand-panel { display: none !important; }
    }
    </style>
</body>
</html>
