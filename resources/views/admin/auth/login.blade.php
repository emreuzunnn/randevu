<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Randevu — Giriş</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page text-white">
    <div class="mx-auto flex min-h-screen max-w-7xl items-center px-6 py-12">
        <div class="grid w-full gap-8 grid-cols-[1.2fr_0.8fr]">

            {{-- Sol panel: tanıtım --}}
            <section class="login-shell p-10 lg:p-14">
                <div class="section-eyebrow">Kurumsal Yönetim Platformu</div>
                <h1 class="mt-5 max-w-3xl text-5xl font-bold leading-tight tracking-tight lg:text-6xl">
                    Randevu operasyonunuzu tek merkezden kusursuz yönetin.
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8" style="color:var(--text-muted)">
                    Dükkanlar, stüdyolar, personel akışı ve randevu süreçleri tek panelde birleşir; ekip daha hızlı, operasyon daha net ilerler.
                </p>
                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <div class="data-card">
                        <div class="text-xs" style="color:var(--text-muted)">Canlı Takip</div>
                        <div class="mt-3 text-3xl font-bold" style="color:var(--accent)">Anlık</div>
                    </div>
                    <div class="data-card">
                        <div class="text-xs" style="color:var(--text-muted)">Raporlama</div>
                        <div class="mt-3 text-3xl font-bold" style="color:var(--accent)">Günlük</div>
                    </div>
                    <div class="data-card">
                        <div class="text-xs" style="color:var(--text-muted)">Operasyon</div>
                        <div class="mt-3 text-3xl font-bold" style="color:var(--accent)">Kesintisiz</div>
                    </div>
                </div>
            </section>

            {{-- Sağ panel: giriş formu --}}
            <section class="login-shell p-8 lg:p-10">
                <div class="section-eyebrow">Güvenli Giriş</div>
                <h2 class="mt-3 text-3xl font-bold">Panele Giriş Yap</h2>
                <p class="mt-3 text-sm leading-6" style="color:var(--text-muted)">
                    Yetkili hesabınızla giriş yapın, iş akışınızı tek ekranda hızlı ve düzenli şekilde yönetin.
                </p>

                <form action="{{ route('admin.login.submit') }}" method="POST" class="mt-8 form-grid">
                    @csrf
                    <div class="field-wrap">
                        <label class="field-label">E-posta Adresi</label>
                        <input name="email" type="email" value="{{ old('email') }}"
                               class="field-input" placeholder="admin@example.com">
                        @error('email')
                            <div class="text-xs mt-0.5" style="color:var(--danger)">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field-wrap">
                        <label class="field-label">Şifre</label>
                        <input name="password" type="password"
                               class="field-input" placeholder="••••••">
                        @error('password')
                            <div class="text-xs mt-0.5" style="color:var(--danger)">{{ $message }}</div>
                        @enderror
                    </div>

                    <button class="button-primary w-full justify-center mt-1"
                            type="submit" style="padding:0.72rem">
                        Giriş Yap
                    </button>
                </form>
            </section>

        </div>
    </div>
</body>
</html>
