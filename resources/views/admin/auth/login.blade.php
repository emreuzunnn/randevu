<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Giris</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[radial-gradient(circle_at_top,#fbbf24_0%,#f7f3ea_35%,#d6d3d1_100%)] text-stone-900">
    <div class="mx-auto flex min-h-screen max-w-6xl items-center px-6 py-12">
        <div class="grid w-full gap-8 lg:grid-cols-[1.2fr_0.8fr]">
            <section class="rounded-[2rem] border border-stone-200/70 bg-white/80 p-10 shadow-2xl shadow-stone-400/15 backdrop-blur">
                <div class="max-w-2xl">
                    <div class="text-xs font-semibold uppercase tracking-[0.35em] text-orange-500">Studio Control</div>
                    <h1 class="mt-4 text-5xl font-semibold leading-tight text-stone-950">Randevu yonetimini tek ekranda topla.</h1>
                    <p class="mt-5 text-lg leading-8 text-stone-600">
                        Admin panelinden kullanicilari, randevulari ve studyo detaylarini yonetebilirsin.
                    </p>
                </div>
            </section>
            <section class="rounded-[2rem] bg-stone-950 p-8 text-white shadow-2xl shadow-stone-500/30">
                <div class="mb-8">
                    <div class="text-sm uppercase tracking-[0.3em] text-orange-300">Admin Login</div>
                    <h2 class="mt-3 text-3xl font-semibold">Panele giris yap</h2>
                </div>
                <form action="{{ route('admin.login.submit') }}" method="POST" class="space-y-5">
                    @csrf
                    <div>
                        <label class="mb-2 block text-sm text-stone-300">Mail</label>
                        <input name="email" type="email" value="{{ old('email') }}" class="w-full rounded-2xl border border-stone-700 bg-stone-900 px-4 py-3 outline-none ring-0 placeholder:text-stone-500 focus:border-orange-400" placeholder="admin@example.com">
                        @error('email')
                            <div class="mt-2 text-sm text-red-300">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm text-stone-300">6 haneli sifre</label>
                        <input name="password" type="password" class="w-full rounded-2xl border border-stone-700 bg-stone-900 px-4 py-3 outline-none placeholder:text-stone-500 focus:border-orange-400" placeholder="123456">
                    </div>
                    <button class="w-full rounded-2xl bg-orange-500 px-4 py-3 font-semibold text-stone-950 transition hover:bg-orange-400">
                        Giris Yap
                    </button>
                </form>
            </section>
        </div>
    </div>
</body>
</html>
