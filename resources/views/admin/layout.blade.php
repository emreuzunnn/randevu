<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin Panel' }} — Randevu</title>
    @php
        $adminApiToken = session('admin_api_token');
        if (auth()->check() && (! is_string($adminApiToken) || $adminApiToken === '')) {
            $adminApiToken = auth()->user()->issueApiToken();
            session(['admin_api_token' => $adminApiToken]);
        }
        $u         = auth()->user();
        $userRole  = $u?->role?->value ?? '';
        $roleLabels = [
            'admin'       => 'Admin',
            'yonetici'    => 'Yönetici',
            'studio_admin'=> 'Stüdyo Yöneticisi',
            'supervisor'  => 'Süpervizör',
            'designer'    => 'Tasarımcı',
            'artist'      => 'Artist',
            'info'        => 'Info',
            'sofor'       => 'Şoför',
            'calisan'     => 'Çalışan',
        ];
        $roleLabel   = $roleLabels[$userRole] ?? ucfirst($userRole);
        $userName    = $u?->fullName() ?: $u?->name;
        $userInitial = strtoupper(mb_substr($userName ?? 'A', 0, 1));

        $isAdmin        = $u?->hasRole(\App\Enums\UserRole::Admin);
        $isYonetici     = $u?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici]);
        $isStudioAdmin  = $u?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici, \App\Enums\UserRole::StudioAdmin]);
        $canManageUsers = $isStudioAdmin;
        $isSupervisor   = $u?->hasRole(\App\Enums\UserRole::Supervisor);

        $roleBadgeClass = match($userRole) {
            'admin'        => 'badge-pill--danger',
            'yonetici'     => 'badge-pill--warning',
            'studio_admin' => 'badge-pill--purple',
            'supervisor'   => 'badge-pill--info',
            'designer'     => 'badge-pill--teal',
            'artist'       => 'badge-pill--success',
            'sofor'        => 'badge-pill--warning',
            default        => '',
        };
    @endphp
    <meta name="admin-api-base"             content="/api">
    <meta name="admin-api-token"            content="{{ $adminApiToken }}">
    <meta name="admin-user-role"            content="{{ $userRole }}">
    <meta name="admin-can-manage-structure" content="{{ $isYonetici ? '1' : '0' }}">
    <meta name="admin-is-admin"             content="{{ $isAdmin ? '1' : '0' }}">
    <meta name="admin-is-studio-admin"      content="{{ $u?->hasRole(\App\Enums\UserRole::StudioAdmin) ? '1' : '0' }}">
    <meta name="admin-is-supervisor"        content="{{ $isSupervisor ? '1' : '0' }}">
    <meta name="admin-can-manage-users"     content="{{ $canManageUsers ? '1' : '0' }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-stage">
    <div class="ambient-orb ambient-orb--gold"></div>
    <div class="ambient-orb ambient-orb--blue"></div>
    <div class="ambient-orb ambient-orb--orange"></div>

    <div class="admin-shell">
        {{-- ── Sidebar ── --}}
        <aside class="admin-sidebar">
            <div class="admin-sidebar__brand">
                <div class="section-eyebrow">
                    @if($isAdmin) Platform Admin
                    @elseif($isYonetici) Operasyon Merkezi
                    @elseif($isStudioAdmin) Stüdyo Yönetim
                    @else Personel Paneli
                    @endif
                </div>
                <div class="mt-2 text-lg font-bold tracking-tight" style="color:var(--text-main)">Randevu Panel</div>
                <p class="mt-1.5 text-xs leading-5" style="color:var(--text-muted)">
                    @if($isStudioAdmin) Stüdyonuzu, ekibinizi ve randevularınızı yönetin.
                    @else Randevu operasyonunuzu buradan yönetin.
                    @endif
                </p>
            </div>

            <nav class="admin-sidebar__nav">
                <div class="admin-nav-section">Genel</div>

                <a href="{{ route('admin.dashboard') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    Dashboard
                </a>

                {{-- Admin-only: Şirketler --}}
                @if ($isAdmin)
                    <a href="{{ route('admin.companies.index') }}"
                       class="admin-nav-link {{ request()->routeIs('admin.companies.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 21h18"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M9 16h1"/>
                            <path d="M14 8h1"/><path d="M14 12h1"/><path d="M14 16h1"/>
                            <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/>
                        </svg>
                        Şirketler
                    </a>
                @endif

                {{-- Admin + Yönetici: Yönetim bölümü --}}
                @if ($isYonetici)
                    <div class="admin-nav-section">Yönetim</div>

                    <a href="{{ route('admin.shops.index') }}"
                       class="admin-nav-link {{ request()->routeIs('admin.shops.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        Dükkanlar
                    </a>
                @endif

                {{-- Admin + Yönetici + Stüdyo Admin: Stüdyolar & Kullanıcılar --}}
                @if ($isStudioAdmin)
                    @if (! $isYonetici)
                        <div class="admin-nav-section">Stüdyom</div>
                    @endif

                    <a href="{{ route('admin.studios.index') }}"
                       class="admin-nav-link {{ request()->routeIs('admin.studios.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="23 7 16 12 23 17 23 7"/>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                        </svg>
                        Stüdyolar
                    </a>

                    <a href="{{ route('admin.users.index') }}"
                       class="admin-nav-link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        Kullanıcılar
                    </a>
                @endif

                <div class="admin-nav-section">Operasyon</div>

                <a href="{{ route('admin.appointments.index') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.appointments.*') ? 'is-active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Randevular
                </a>
            </nav>

            {{-- Sidebar footer --}}
            <div style="padding: 0 1.1rem 1.1rem;">
                <div style="border-radius:1rem;border:1px solid rgba(255,255,255,0.07);padding:0.75rem 0.9rem;background:rgba(255,255,255,0.025)">
                    <div class="text-xs font-semibold" style="color:var(--text-main)">{{ $userName }}</div>
                    <div class="mt-0.5 text-xs" style="color:var(--text-subtle)">{{ $roleLabel }}</div>
                </div>
            </div>
        </aside>

        {{-- ── Main ── --}}
        <main class="admin-main">
            <header class="admin-topbar">
                <div>
                    <div class="section-eyebrow">{{ $title ?? 'Admin Panel' }}</div>
                    <div class="mt-1 text-sm font-medium" style="color:var(--text-muted)">
                        @if($isAdmin) Tüm şirket, dükkan, stüdyo ve randevu hareketleri.
                        @elseif($isYonetici) Stüdyolar, personel ve randevu operasyonu.
                        @elseif($isStudioAdmin) Stüdyonuzun ekibi ve randevu akışı.
                        @else Randevu ve operasyon hareketleri.
                        @endif
                    </div>
                </div>
                <div class="action-row">
                    <div class="topbar-user">
                        <div class="topbar-avatar">{{ $userInitial }}</div>
                        <div>
                            <div class="topbar-user-name">{{ $userName }}</div>
                            <div class="topbar-user-role">
                                <span class="badge-pill {{ $roleBadgeClass }}" style="font-size:0.6rem;padding:0.18rem 0.5rem">{{ $roleLabel }}</span>
                            </div>
                        </div>
                    </div>
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button class="button-ghost" style="font-size:0.78rem;padding:0.48rem 0.9rem">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            Çıkış
                        </button>
                    </form>
                </div>
            </header>

            <div class="page-shell">
                @if (session('status'))
                    <div class="toast toast--success" style="position:static;min-width:auto;max-width:none;animation:none;backdrop-filter:none">
                        <div class="text-sm font-semibold">Bilgi</div>
                        <div class="mt-1 text-sm" style="color:var(--text-muted)">{{ session('status') }}</div>
                    </div>
                @endif
                @yield('content')
            </div>

            <div class="toast-stack" id="admin-toast-root"></div>
        </main>
    </div>
</body>
</html>
