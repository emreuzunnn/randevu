<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Yönetim Paneli' }} — TattooDesk Business</title>
    @php
        $adminApiToken = session('admin_api_token');
        if (auth()->check() && (! is_string($adminApiToken) || $adminApiToken === '')) {
            $adminApiToken = auth()->user()->issueApiToken();
            session(['admin_api_token' => $adminApiToken]);
        }
        $u         = auth()->user();
        $userRole  = $u?->role?->value ?? '';
        $roleLabels = [
            'admin'      => 'Platform Admin',
            'yonetici'   => 'Yönetici',
            'supervisor' => 'Süpervizör',
            'designer'   => 'Tasarımcı',
            'artist'     => 'Artist',
            'info'       => 'Info',
            'sofor'      => 'Şoför',
            'calisan'    => 'Çalışan',
            'kullanici_rol' => 'Kullanıcı (Rol)',
            'kullanici'  => 'Kullanıcı',
        ];
        $roleLabel   = $roleLabels[$userRole] ?? ucfirst($userRole);
        $userName    = $u?->fullName() ?: $u?->name;
        $userInitial = strtoupper(mb_substr($userName ?? 'A', 0, 1));

        $isAdmin          = $u?->hasRole(\App\Enums\UserRole::Admin);
        $isYonetici       = $u?->hasRole(\App\Enums\UserRole::Yonetici);
        $isSupervisor     = $u?->hasRole(\App\Enums\UserRole::Supervisor);
        $isSofor          = $u?->hasRole(\App\Enums\UserRole::Sofor);
        $isRegularUser    = $u?->hasAnyRole([\App\Enums\UserRole::Kullanici, \App\Enums\UserRole::KullaniciRol]);
        $canManageShops   = $u?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici]);
        $canManageStudios = $u?->hasAnyRole([\App\Enums\UserRole::Admin, \App\Enums\UserRole::Yonetici, \App\Enums\UserRole::Supervisor]);
        $canManageUsers   = $canManageStudios;
        $primaryStudio    = $u?->studios()->with('company')->first();
        $managedCompany   = $isYonetici ? $u?->managedCompanies()->first() : null;
        $businessName     = match(true) {
            $isAdmin => 'TattooDesk Platform',
            $managedCompany !== null => $managedCompany->name,
            $primaryStudio?->company !== null => $primaryStudio->company->name,
            $primaryStudio !== null => $primaryStudio->name,
            default => 'TattooDesk Business',
        };
        $businessScope = match(true) {
            $isAdmin => 'Tüm şirketler',
            $isYonetici => 'Şirket yönetimi',
            $isSupervisor => 'Stüdyo operasyonu',
            $isSofor => 'Transfer operasyonu',
            default => $roleLabel,
        };

        $roleBadgeClass = match($userRole) {
            'admin'      => 'badge-pill--danger',
            'yonetici'   => 'badge-pill--warning',
            'supervisor' => 'badge-pill--info',
            'designer'   => 'badge-pill--teal',
            'artist'     => 'badge-pill--success',
            'sofor'      => 'badge-pill--warning',
            default      => '',
        };

        $topbarContext = match(true) {
            $isAdmin      => 'Platform geneli yönetim',
            $isYonetici   => 'Şirket operasyon merkezi',
            $isSupervisor => 'Stüdyo ve ekip yönetimi',
            $isSofor      => 'Transfer operasyon paneli',
            $isRegularUser => 'Kullanıcı paneli',
            default       => 'Operasyon paneli',
        };
    @endphp
    <meta name="admin-api-base"             content="/api">
    <meta name="admin-api-token"            content="{{ $adminApiToken }}">
    <meta name="admin-user-id"              content="{{ $u?->id }}">
    <meta name="admin-user-role"            content="{{ $userRole }}">
    <meta name="admin-can-manage-structure" content="{{ $canManageShops ? '1' : '0' }}">
    <meta name="admin-can-manage-shops"     content="{{ $canManageShops ? '1' : '0' }}">
    <meta name="admin-can-manage-studios"   content="{{ $canManageStudios ? '1' : '0' }}">
    <meta name="admin-is-admin"             content="{{ $isAdmin ? '1' : '0' }}">
    <meta name="admin-is-studio-admin"      content="{{ $canManageStudios && ! $canManageShops ? '1' : '0' }}">
    <meta name="admin-is-supervisor"        content="{{ $isSupervisor ? '1' : '0' }}">
    <meta name="admin-can-manage-users"     content="{{ $canManageUsers ? '1' : '0' }}">
    <meta name="firebase-web-api-key"       content="{{ config('services.firebase.web.apiKey') }}">
    <meta name="firebase-web-auth-domain"   content="{{ config('services.firebase.web.authDomain') }}">
    <meta name="firebase-web-project-id"    content="{{ config('services.firebase.web.projectId') }}">
    <meta name="firebase-web-storage-bucket" content="{{ config('services.firebase.web.storageBucket') }}">
    <meta name="firebase-web-sender-id"     content="{{ config('services.firebase.web.messagingSenderId') }}">
    <meta name="firebase-web-app-id"        content="{{ config('services.firebase.web.appId') }}">
    <meta name="firebase-web-measurement-id" content="{{ config('services.firebase.web.measurementId') }}">
    <meta name="firebase-web-vapid-key"     content="{{ config('services.firebase.web_vapid_key') }}">
    <script>
        try {
            if (localStorage.getItem('adminSidebarCollapsed') === '1') {
                document.documentElement.classList.add('admin-sidebar-collapsed');
            }
        } catch (error) {}
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..800;1,14..32,300..800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-stage">

    <div class="admin-shell">

        {{-- ── Sidebar ─────────────────────────────────────────── --}}
        <aside class="admin-sidebar">

            {{-- Brand --}}
            <div class="admin-sidebar__brand">
                <div class="sidebar-brand-row">
                    <div class="sidebar-logo">
                        <div class="sidebar-logo__mark">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>
                        <div class="sidebar-logo__copy">
                            <div class="sidebar-logo__text">TattooDesk</div>
                            <div class="sidebar-logo__sub">
                                Business Management
                            </div>
                        </div>
                    </div>
                    <button type="button" class="sidebar-toggle" id="admin-sidebar-toggle" aria-label="Menüyü daralt" aria-expanded="true" title="Menüyü daralt">
                        <svg class="sidebar-toggle__collapse" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m15 18-6-6 6-6"/>
                        </svg>
                        <svg class="sidebar-toggle__expand" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m9 18 6-6-6-6"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Navigation --}}
            <nav class="admin-sidebar__nav">

                <div class="admin-nav-section">Genel</div>

                <a href="{{ route('admin.dashboard') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    Genel Bakış
                </a>

                @if($isAdmin)
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

                @if($canManageStudios)
                    @if(! $canManageShops)
                        <div class="admin-nav-section">Stüdyom</div>
                    @endif
                    <a href="{{ route('admin.studios.index') }}"
                       class="admin-nav-link {{ request()->routeIs('admin.studios.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="23 7 16 12 23 17 23 7"/>
                            <rect x="1" y="5" width="15" height="14" rx="2"/>
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

                @unless($isRegularUser)
                    <a href="{{ route('admin.appointments.index') }}"
                       class="admin-nav-link {{ request()->routeIs('admin.appointments.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                            <line x1="8" y1="14" x2="8" y2="14" stroke-width="2.5"/>
                            <line x1="12" y1="14" x2="12" y2="14" stroke-width="2.5"/>
                        </svg>
                        Randevular
                    </a>
                    <a href="{{ route('admin.tickets.index') }}"
                       class="admin-nav-link {{ request()->routeIs('admin.tickets.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 9a3 3 0 0 0 0 6v3a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-3a3 3 0 0 0 0-6V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/>
                            <path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>
                        </svg>
                        Biletler
                    </a>
                    @if($isAdmin || $isYonetici)
                        <a href="{{ route('admin.ticket-pdf-template.index') }}"
                           class="admin-nav-link {{ request()->routeIs('admin.ticket-pdf-template.*') ? 'is-active' : '' }}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <path d="M14 2v6h6"/>
                                <path d="M8 13h8"/>
                                <path d="M8 17h5"/>
                            </svg>
                            PDF Şablonu
                        </a>
                    @endif
                    <a href="{{ route('admin.earnings.index') }}"
                       class="admin-nav-link {{ request()->routeIs('admin.earnings.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2"/>
                            <path d="M16 13h4"/><path d="M2 10h20"/>
                        </svg>
                        Hakedişler
                    </a>
                    @if($isAdmin || $isYonetici)
                        <a href="{{ route('admin.hotel-revenues.index') }}"
                           class="admin-nav-link {{ request()->routeIs('admin.hotel-revenues.*') ? 'is-active' : '' }}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 21h18"/>
                                <path d="M5 21V8l7-5 7 5v13"/>
                                <path d="M9 21v-6h6v6"/>
                                <path d="M9 10h.01"/><path d="M15 10h.01"/>
                            </svg>
                            Otel Ciroları
                        </a>
                        <a href="{{ route('admin.old-customers.index') }}"
                           class="admin-nav-link {{ request()->routeIs('admin.old-customers.*') ? 'is-active' : '' }}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 12a9 9 0 1 0 3-6.7"/>
                                <path d="M3 3v6h6"/>
                                <path d="M12 7v5l3 2"/>
                            </svg>
                            Eski Müşteriler
                        </a>
                    @endif
                @endunless

                <a href="{{ route('admin.appointment-requests.index') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.appointment-requests.*') ? 'is-active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h7"/>
                        <path d="M16 3h5v5"/>
                        <path d="m15 9 6-6"/>
                    </svg>
                    Talepler
                </a>

                <a href="{{ route('admin.my-notifications.index') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.my-notifications.*') ? 'is-active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    Bildirimlerim
                </a>

                @if($isAdmin)
                    <a href="{{ route('admin.notifications.index') }}"
                       class="admin-nav-link {{ request()->routeIs('admin.notifications.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        Bildirim Logları
                    </a>
                    <a href="{{ route('admin.content-reports.index') }}"
                       class="admin-nav-link {{ request()->routeIs('admin.content-reports.*') ? 'is-active' : '' }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 9v4"/><path d="M12 17h.01"/>
                            <path d="M10.3 2.9 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.9a2 2 0 0 0-3.4 0Z"/>
                        </svg>
                        Şikayetler
                    </a>
                @endif

                <div class="admin-nav-section">Hesap</div>

                <a href="{{ route('admin.profile.index') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.profile.*') ? 'is-active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21a8 8 0 0 0-16 0"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Profil
                </a>

                <a href="{{ route('admin.settings.index') }}"
                   class="admin-nav-link {{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.74v-.51a2 2 0 0 1 1-1.72l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    Ayarlar
                </a>

            </nav>

            {{-- Sidebar footer: user info --}}
            <div class="sidebar-user">
                <div class="sidebar-user__avatar">{{ $userInitial }}</div>
                <div style="min-width:0;flex:1">
                    <div class="sidebar-user__name">{{ $userName }}</div>
                    <div class="sidebar-user__role">
                        <span class="badge-pill {{ $roleBadgeClass }}" style="font-size:0.58rem;padding:0.15rem 0.5rem">{{ $roleLabel }}</span>
                    </div>
                </div>
            </div>

        </aside>

        {{-- ── Main ──────────────────────────────────────────────── --}}
        <main class="admin-main">

            {{-- Topbar --}}
            <header class="admin-topbar">
                <div class="topbar-context">
                    <div class="topbar-breadcrumb">{{ $businessScope }} / {{ $title ?? 'Genel Bakış' }}</div>
                    <div class="topbar-title">{{ $businessName }}</div>
                    <div class="topbar-subtitle">{{ $topbarContext }}</div>
                </div>
                <div class="topbar-right">
                    <div class="business-status" title="Sistem durumu">
                        <span class="state-dot state-dot--success"></span>
                        <span>Operasyon aktif</span>
                    </div>
                    <div class="topbar-date">{{ now()->format('d.m.Y') }}</div>
                    <div class="topbar-user">
                        <div class="topbar-avatar">{{ $userInitial }}</div>
                        <div>
                            <div class="topbar-user-name">{{ $userName }}</div>
                            <div class="topbar-user-role">
                                <span class="badge-pill {{ $roleBadgeClass }}" style="font-size:0.58rem;padding:0.15rem 0.5rem">{{ $roleLabel }}</span>
                            </div>
                        </div>
                    </div>
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button class="button-ghost" style="font-size:0.78rem;padding:0.5rem 0.85rem;gap:0.4rem">
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

            {{-- Page content --}}
            <div class="page-shell">
                @if(session('status'))
                    <div class="toast toast--success" style="position:static;min-width:auto;max-width:none;animation:none;backdrop-filter:none;margin-bottom:0">
                        <div class="text-sm font-semibold" style="color:var(--text-main)">Bilgi</div>
                        <div class="mt-1 text-sm" style="color:var(--text-muted)">{{ session('status') }}</div>
                    </div>
                @endif
                @yield('content')
            </div>

        </main>
    </div>

    <div class="toast-stack" id="admin-toast-root"></div>

    <script>
        (() => {
            const toggle = document.getElementById('admin-sidebar-toggle');
            if (!toggle) return;

            const syncToggle = () => {
                const isCollapsed = document.documentElement.classList.contains('admin-sidebar-collapsed');
                toggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                toggle.setAttribute('aria-label', isCollapsed ? 'Menüyü genişlet' : 'Menüyü daralt');
                toggle.setAttribute('title', isCollapsed ? 'Menüyü genişlet' : 'Menüyü daralt');
            };

            syncToggle();
            toggle.addEventListener('click', () => {
                const isCollapsed = document.documentElement.classList.toggle('admin-sidebar-collapsed');
                try {
                    localStorage.setItem('adminSidebarCollapsed', isCollapsed ? '1' : '0');
                } catch (error) {}
                syncToggle();
            });
        })();
    </script>
</body>
</html>
