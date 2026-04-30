<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin Panel' }}</title>
    @php
        $adminApiToken = session('admin_api_token');
        if (auth()->check() && (! is_string($adminApiToken) || $adminApiToken === '')) {
            $adminApiToken = auth()->user()->issueApiToken();
            session(['admin_api_token' => $adminApiToken]);
        }
    @endphp
    <meta name="admin-api-base" content="/api">
    <meta name="admin-api-token" content="{{ $adminApiToken }}">
    <meta name="admin-user-role" content="{{ auth()->user()?->role?->value }}">
    <meta name="admin-can-manage-structure" content="{{ auth()->user()?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici]) ? '1' : '0' }}">
    <meta name="admin-is-admin" content="{{ auth()->user()?->hasRole(\App\Enums\UserRole::Admin) ? '1' : '0' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-stage">
    <div class="ambient-orb ambient-orb--gold"></div>
    <div class="ambient-orb ambient-orb--blue"></div>
    <div class="ambient-orb ambient-orb--orange"></div>
    <div class="admin-shell">
        <aside class="admin-sidebar hidden px-6 py-8 text-stone-100 lg:block">
            <div class="admin-sidebar__brand mb-8 rounded-[1.7rem] p-5">
                <div class="section-eyebrow">Operasyon merkezi</div>
                <div class="mt-3 text-3xl font-semibold">Admin Panel</div>
                <p class="mt-3 text-sm leading-6 text-slate-300">Dukkanlarini, studyolarini ve randevu operasyonunu tek merkezden guvenle yonet.</p>
            </div>
            <nav class="space-y-2 text-sm">
                <a href="{{ route('admin.dashboard') }}" class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">Dashboard</a>
                @if (auth()->user()?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici]))
                    <a href="{{ route('admin.shops.index') }}" class="admin-nav-link {{ request()->routeIs('admin.shops.*') ? 'is-active' : '' }}">Dukkanlar</a>
                    <a href="{{ route('admin.users.index') }}" class="admin-nav-link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">Kullanicilar</a>
                @endif
                <a href="{{ route('admin.appointments.index') }}" class="admin-nav-link {{ request()->routeIs('admin.appointments.*') ? 'is-active' : '' }}">Randevular</a>
                @if (auth()->user()?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici]))
                    <a href="{{ route('admin.studios.index') }}" class="admin-nav-link {{ request()->routeIs('admin.studios.*') ? 'is-active' : '' }}">Studyolar</a>
                @endif
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-topbar">
                <div>
                    <div class="section-eyebrow">{{ $title ?? 'Admin Panel' }}</div>
                    <div class="mt-2 text-2xl font-semibold">{{ auth()->user()?->fullName() ?: auth()->user()?->name }}</div>
                    <div class="mt-2 text-sm text-muted">Tum ekip, randevu ve lokasyon hareketleri bu ekranda anlik olarak takip edilir.</div>
                </div>
                <div class="action-row">
                    <span class="badge-pill">{{ auth()->user()?->role?->value }}</span>
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button class="button-secondary">Cikis</button>
                    </form>
                </div>
            </header>
            <div class="page-shell">
                @if (session('status'))
                    <div class="toast toast--success">
                        <div class="text-sm font-semibold">Bilgi</div>
                        <div class="mt-1 text-sm text-slate-300">{{ session('status') }}</div>
                    </div>
                @endif
                @yield('content')
            </div>
            <div class="toast-stack" id="admin-toast-root"></div>
        </main>
    </div>
</body>
</html>
