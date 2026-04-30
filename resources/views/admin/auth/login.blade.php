<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Giris</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page text-white">
    <div class="mx-auto flex min-h-screen max-w-7xl items-center px-6 py-12">
        <div class="grid w-full gap-8 lg:grid-cols-[1.18fr_0.82fr]">
            <section class="login-shell p-10 lg:p-14">
                <div class="section-eyebrow">Kurumsal yonetim</div>
                <h1 class="mt-5 max-w-3xl text-5xl font-semibold leading-tight lg:text-6xl">Randevu operasyonunu tek merkezden kusursuz yonet.</h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                    Dukkanlar, studyolar, personel akisi ve randevu surecleri tek panelde birlesir; ekip daha hizli, operasyon daha net ilerler.
                </p>
                <div class="mt-10 grid gap-4 md:grid-cols-3">
                    <div class="data-card"><div class="text-sm text-muted">Canli takip</div><div class="mt-3 text-3xl font-semibold">Anlik</div></div>
                    <div class="data-card"><div class="text-sm text-muted">Raporlama</div><div class="mt-3 text-3xl font-semibold">Gunluk</div></div>
                    <div class="data-card"><div class="text-sm text-muted">Operasyon</div><div class="mt-3 text-3xl font-semibold">Kesintisiz</div></div>
                </div>
            </section>
            <section class="login-shell p-8 lg:p-10">
                <div class="section-eyebrow">Guvenli giris</div>
                <h2 class="mt-3 text-3xl font-semibold">Panele giris yap</h2>
                <p class="mt-3 text-sm leading-6 text-slate-300">Yetkili hesabinizla giris yapin, is akisinizi tek ekranda hizli ve duzenli sekilde yonetin.</p>
                <form action="{{ route('admin.login.submit') }}" method="POST" class="mt-8 form-grid">
                    @csrf
                    <div class="field-wrap">
                        <label class="field-label">Mail</label>
                        <input name="email" type="email" value="{{ old('email') }}" class="field-input" placeholder="admin@example.com">
                        @error('email')
                            <div class="text-sm text-red-300">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field-wrap">
                        <label class="field-label">6 haneli sifre</label>
                        <input name="password" type="password" class="field-input" placeholder="123456">
                    </div>
                    <button class="button-primary w-full justify-center" type="submit">Giris yap</button>
                </form>
            </section>
        </div>
    </div>
</body>
</html>
