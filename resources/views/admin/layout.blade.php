<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin Panel' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 text-stone-900">
    <div class="flex min-h-screen">
        <aside class="hidden w-72 bg-stone-950 px-6 py-8 text-stone-100 lg:block">
            <div class="mb-10">
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-orange-300">Randevu</div>
                <div class="mt-2 text-2xl font-semibold">Admin Panel</div>
            </div>
            <nav class="space-y-2 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="block rounded-xl px-4 py-3 hover:bg-stone-800">Dashboard</a>
                @if (auth()->user()?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici]))
                    <a href="{{ route('admin.shops.index') }}" class="block rounded-xl px-4 py-3 hover:bg-stone-800">Dukkanlar</a>
                    <a href="{{ route('admin.users.index') }}" class="block rounded-xl px-4 py-3 hover:bg-stone-800">Kullanicilar</a>
                @endif
                <a href="{{ route('admin.appointments.index') }}" class="block rounded-xl px-4 py-3 hover:bg-stone-800">Randevular</a>
                @if (auth()->user()?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici]))
                    <a href="{{ route('admin.studios.index') }}" class="block rounded-xl px-4 py-3 hover:bg-stone-800">Studyolar</a>
                @endif
            </nav>
        </aside>
        <main class="flex-1">
            <header class="flex items-center justify-between border-b border-stone-200 bg-white px-6 py-4">
                <div>
                    <div class="text-sm text-stone-500">{{ $title ?? 'Admin Panel' }}</div>
                    <div class="text-lg font-semibold">{{ auth()->user()?->fullName() ?: auth()->user()?->name }}</div>
                </div>
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button class="rounded-xl bg-stone-900 px-4 py-2 text-sm font-medium text-white">Cikis</button>
                </form>
            </header>
            <div class="px-6 py-6">
                @if (session('status'))
                    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
