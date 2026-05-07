<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Tick System Onn')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800,900" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
    <style>
        /* ============================================================
           TICK SYSTEM ONN — LAYOUT BASE + NAVBAR
           ============================================================ */

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy-950: #0a1628;
            --navy-900: #0f1e3d;
            --navy-800: #1a2f5a;
            --navy-700: #1e3a8a;
            --blue-600: #1d4ed8;
            --blue-500: #3b82f6;
            --blue-400: #60a5fa;
            --blue-100: #dbeafe;
            --blue-50:  #eff6ff;
            --slate-950: #020617;
            --slate-900: #0f172a;
            --slate-700: #334155;
            --slate-600: #475569;
            --slate-500: #64748b;
            --slate-400: #94a3b8;
            --slate-300: #cbd5e1;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;
            --slate-50:  #f8fafc;
            --red-700: #b91c1c;
            --red-100: #fee2e2;
            --green-700: #166534;
            --green-100: #dcfce7;

            --radius: 14px;
            --radius-sm: 10px;
            --radius-xs: 8px;
            --shadow-xs: 0 1px 3px rgba(15,23,42,0.07), 0 1px 2px rgba(15,23,42,0.04);
            --shadow-sm: 0 4px 12px rgba(15,23,42,0.08), 0 2px 4px rgba(15,23,42,0.04);
            --shadow-md: 0 8px 24px rgba(15,23,42,0.10), 0 4px 8px rgba(15,23,42,0.06);
            --shadow-lg: 0 16px 40px rgba(15,23,42,0.14), 0 8px 16px rgba(15,23,42,0.08);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);

            --bg-page:    #eef2f7;
            --bg-surface: #ffffff;
            --bg-soft:    #f8fafc;
            --text-primary:   #0d1829;
            --text-secondary: #334155;
            --text-muted:     #64748b;
            --border-default: #e2e8f0;
            --border-light:   #f1f5f9;
        }

        body {
            font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif;
            background: var(--bg-page);
            color: var(--text-primary);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================= */
        /* 🧭 NAVBAR                     */
        /* ============================= */
        .app-nav {
            position: sticky;
            top: 0;
            z-index: 50;
            background: var(--navy-900);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 4px 20px rgba(10, 22, 40, 0.4);
        }

        /* Línea decorativa top */
        .app-nav::before {
            content: "";
            display: block;
            height: 2px;
            background: linear-gradient(90deg, #1d4ed8, #60a5fa, #1d4ed8);
            background-size: 200% 100%;
            animation: navShimmer 4s linear infinite;
        }

        @keyframes navShimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        .app-nav-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
        }

        /* Brand */
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
            flex-shrink: 0;
        }

        .nav-brand-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #1d4ed8, #60a5fa);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 12px rgba(59,130,246,0.5);
            flex-shrink: 0;
        }

        .nav-brand-text {
            font-size: 1rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            color: white;
        }

        .nav-brand-text span {
            color: #60a5fa;
        }

        /* Links nav */
        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex: 1;
            justify-content: center;
        }

        .nav-link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            text-decoration: none;
            color: rgba(255,255,255,0.65);
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.42rem 0.75rem;
            border-radius: var(--radius-xs);
            transition: var(--transition);
            white-space: nowrap;
            position: relative;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.08);
        }

        /* Link activo — detectado por URL */
        .nav-link.active {
            color: white;
            background: rgba(59,130,246,0.2);
            border: 1px solid rgba(59,130,246,0.3);
        }

        .nav-link.active::after {
            content: "";
            position: absolute;
            bottom: -1px;
            left: 50%;
            transform: translateX(-50%);
            width: 16px;
            height: 2px;
            background: #60a5fa;
            border-radius: 999px;
        }

        /* Separador */
        .nav-sep {
            width: 1px;
            height: 18px;
            background: rgba(255,255,255,0.12);
            margin: 0 0.25rem;
            flex-shrink: 0;
        }

        /* ============================= */
        /* 👤 USER MENU                  */
        /* ============================= */
        .nav-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .user-menu {
            position: relative;
        }

        .user-menu summary {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.38rem 0.65rem 0.38rem 0.45rem;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.08);
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
        }

        .user-menu summary::-webkit-details-marker { display: none; }

        .user-menu summary:hover {
            background: rgba(255,255,255,0.14);
            border-color: rgba(255,255,255,0.25);
        }

        .user-menu[open] summary {
            background: rgba(255,255,255,0.14);
            border-color: rgba(255,255,255,0.3);
        }

        /* Avatar con iniciales */
        .user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: linear-gradient(135deg, #1d4ed8, #60a5fa);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 900;
            color: white;
            letter-spacing: 0.02em;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(29,78,216,0.4);
        }

        .user-name-text {
            font-size: 0.84rem;
            font-weight: 700;
            color: rgba(255,255,255,0.9);
            max-width: 110px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-chevron {
            color: rgba(255,255,255,0.5);
            transition: transform 0.2s ease;
            flex-shrink: 0;
        }

        .user-menu[open] .user-chevron {
            transform: rotate(180deg);
        }

        /* Dropdown panel */
        .user-menu-panel {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            min-width: 260px;
            background: white;
            border: 1px solid var(--border-default);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            z-index: 100;
            overflow: hidden;
            animation: dropdownIn 0.15s ease both;
        }

        @keyframes dropdownIn {
            from { opacity: 0; transform: translateY(-6px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .user-menu-head {
            padding: 1rem 1rem 0.75rem;
            background: linear-gradient(135deg, #f0f7ff, #eff6ff);
            border-bottom: 1px solid var(--border-default);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-menu-avatar-lg {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #1d4ed8, #60a5fa);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 900;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(29,78,216,0.3);
        }

        .user-menu-info-name {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .user-menu-info-email {
            font-size: 0.76rem;
            color: var(--text-muted);
            margin-top: 0.1rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 170px;
        }

        .user-menu-body {
            padding: 0.6rem;
            display: grid;
            gap: 0.25rem;
        }

        .user-menu-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.6rem 0.75rem;
            border-radius: 9px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            transition: var(--transition);
            border: none;
            background: none;
            cursor: pointer;
            width: 100%;
            text-align: left;
        }

        .user-menu-item:hover {
            background: var(--slate-50);
            color: var(--text-primary);
        }

        .user-menu-item--danger {
            color: #b91c1c;
        }

        .user-menu-item--danger:hover {
            background: #fff1f2;
            color: #991b1b;
        }

        .user-menu-item-icon {
            width: 28px;
            height: 28px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .user-menu-item-icon--blue  { background: #eff6ff; color: #1e40af; }
        .user-menu-item-icon--red   { background: #fff1f2; color: #b91c1c; }

        .user-menu-divider {
            height: 1px;
            background: var(--border-light);
            margin: 0.25rem 0.6rem;
        }

        /* ============================= */
        /* 📱 HAMBURGER MOBILE           */
        /* ============================= */
        .nav-hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            background: none;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            padding: 0.45rem 0.5rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .nav-hamburger:hover {
            background: rgba(255,255,255,0.1);
        }

        .nav-hamburger span {
            display: block;
            width: 18px;
            height: 2px;
            background: rgba(255,255,255,0.8);
            border-radius: 999px;
            transition: var(--transition);
        }

        /* Mobile drawer */
        .nav-mobile-drawer {
            display: none;
            flex-direction: column;
            gap: 0.25rem;
            padding: 0.75rem 1rem 1rem;
            border-top: 1px solid rgba(255,255,255,0.08);
            background: var(--navy-950);
        }

        .nav-mobile-drawer.open {
            display: flex;
        }

        .nav-mobile-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
            font-weight: 600;
            padding: 0.65rem 0.85rem;
            border-radius: 10px;
            transition: var(--transition);
        }

        .nav-mobile-link:hover,
        .nav-mobile-link.active {
            color: white;
            background: rgba(59,130,246,0.15);
        }

        .nav-mobile-sep {
            height: 1px;
            background: rgba(255,255,255,0.08);
            margin: 0.35rem 0;
        }

        /* ============================= */
        /* 🧱 LAYOUT PRINCIPAL           */
        /* ============================= */
        .app-shell {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 1.75rem 1.5rem 3rem;
        }

        /* ============================= */
        /* 🎨 COMPONENTES BASE           */
        /* ============================= */
        .panel {
            background: var(--bg-surface);
            border: 1px solid var(--border-default);
            border-radius: var(--radius);
            box-shadow: var(--shadow-xs);
        }

        .panel-pad { padding: 1.1rem 1.375rem; }

        .field {
            width: 100%;
            border: 1px solid var(--border-default);
            border-radius: var(--radius-xs);
            background: white;
            padding: 0.65rem 0.8rem;
            font-size: 0.875rem;
            color: var(--text-primary);
            font-family: inherit;
            transition: var(--transition);
            -webkit-appearance: none;
            appearance: none;
        }

        .field:hover  { border-color: #cbd5e1; }
        .field:focus  { outline: 2px solid #3b82f6; outline-offset: 0; border-color: #3b82f6; }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            background: #1d4ed8;
            color: white;
            border: none;
            border-radius: var(--radius-xs);
            padding: 0.6rem 1.1rem;
            font-size: 0.875rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-primary:hover {
            background: #1e3a8a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(29,78,216,0.3);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            background: white;
            color: var(--text-secondary);
            border: 1px solid var(--border-default);
            border-radius: var(--radius-xs);
            padding: 0.6rem 1.1rem;
            font-size: 0.875rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            white-space: nowrap;
        }

        .btn-secondary:hover {
            background: var(--slate-50);
            border-color: #cbd5e1;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-radius: var(--radius-xs);
            padding: 0.8rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .alert-error {
            background: #fff1f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: var(--radius-xs);
            padding: 0.8rem 1rem;
            font-size: 0.875rem;
        }

        .overflow-hidden { overflow: hidden; }
        .table-wrap { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; }

        /* ============================= */
        /* ✨ ANIMACIONES                */
        /* ============================= */
        @keyframes dashboardFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes dashboardRise {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ============================= */
        /* 📱 RESPONSIVE NAVBAR          */
        /* ============================= */
        @media (max-width: 767px) {
            .app-nav-inner { padding: 0 1rem; height: 54px; }
            .nav-links { display: none; }
            .nav-hamburger { display: flex; }
            .user-name-text { display: none; }
            .app-shell { padding: 1.25rem 1rem 2rem; }
        }

        @media (min-width: 768px) and (max-width: 1023px) {
            .nav-link { font-size: 0.82rem; padding: 0.38rem 0.6rem; }
            .app-shell { padding: 1.5rem 1.25rem 2.5rem; }
        }
    </style>
    @endif
</head>

<body>

{{-- ===== NAVBAR ===== --}}
<header class="app-nav">
    <div class="app-nav-inner">

        {{-- Brand --}}
        <a href="{{ auth()->check() ? route('dashboard.index') : url('/') }}" class="nav-brand">
            <div class="nav-brand-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <span class="nav-brand-text">Tick System <span>Onn</span></span>
        </a>

        {{-- Links desktop --}}
        @auth
        <nav class="nav-links">
            @can('viewAny', \App\Models\Ticket::class)
                <a href="{{ route('dashboard.index') }}"
                   class="nav-link {{ request()->routeIs('dashboard.*') ? 'active' : '' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    Dashboard
                </a>
            @endcan

            @can('viewAny', \App\Models\Ticket::class)
                <a href="{{ route('tickets.index') }}"
                   class="nav-link {{ request()->routeIs('tickets.*') ? 'active' : '' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Tickets
                </a>
            @endcan

            @can('create', \App\Models\Ticket::class)
                <a href="{{ route('tickets.create') }}"
                   class="nav-link {{ request()->routeIs('tickets.create') ? 'active' : '' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    Nuevo
                </a>
            @endcan

            <div class="nav-sep"></div>

            @can('create', \App\Models\Location::class)
                <a href="{{ route('locations.index') }}"
                   class="nav-link {{ request()->routeIs('locations.*') ? 'active' : '' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Ubicaciones
                </a>
            @endcan

            @can('create', \App\Models\Category::class)
                <a href="{{ route('categories.index') }}"
                   class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    Categorías
                </a>
            @endcan

            @can('viewAny', \App\Models\User::class)
                <a href="{{ route('users.index') }}"
                   class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Usuarios
                </a>
            @endcan
        </nav>
        @endauth

        {{-- Derecha: user menu + hamburger --}}
        <div class="nav-right">
            @auth
            {{-- User menu --}}
            <details class="user-menu">
                <summary>
                    <div class="user-avatar">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(explode(' ', auth()->user()->name)[1] ?? '', 0, 1)) }}
                    </div>
                    <span class="user-name-text">{{ auth()->user()->name }}</span>
                    <svg class="user-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </summary>

                <div class="user-menu-panel">
                    {{-- Head con avatar grande --}}
                    <div class="user-menu-head">
                        <div class="user-menu-avatar-lg">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(explode(' ', auth()->user()->name)[1] ?? '', 0, 1)) }}
                        </div>
                        <div style="min-width:0;">
                            <p class="user-menu-info-name">{{ auth()->user()->name }}</p>
                            <p class="user-menu-info-email">{{ auth()->user()->email }}</p>
                        </div>
                    </div>

                    {{-- Items --}}
                    <div class="user-menu-body">
                        <a href="{{ route('profile.edit') }}" class="user-menu-item">
                            <span class="user-menu-item-icon user-menu-item-icon--blue">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </span>
                            Perfil de usuario
                        </a>

                        <div class="user-menu-divider"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="user-menu-item user-menu-item--danger">
                                <span class="user-menu-item-icon user-menu-item-icon--red">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                </span>
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </details>

            {{-- Hamburger mobile --}}
            <button class="nav-hamburger" id="navHamburger" aria-label="Menú">
                <span></span>
                <span></span>
                <span></span>
            </button>
            @else
                <a href="{{ route('login') }}" class="btn-secondary" style="font-size:.85rem;padding:.45rem .9rem;">Iniciar sesión</a>
            @endauth
        </div>
    </div>

    {{-- Mobile drawer --}}
    @auth
    <div class="nav-mobile-drawer" id="navDrawer">
        @can('viewAny', \App\Models\Ticket::class)
            <a href="{{ route('dashboard.index') }}" class="nav-mobile-link {{ request()->routeIs('dashboard.*') ? 'active' : '' }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Dashboard
            </a>
        @endcan
        @can('viewAny', \App\Models\Ticket::class)
            <a href="{{ route('tickets.index') }}" class="nav-mobile-link {{ request()->routeIs('tickets.*') ? 'active' : '' }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Tickets
            </a>
        @endcan
        @can('create', \App\Models\Ticket::class)
            <a href="{{ route('tickets.create') }}" class="nav-mobile-link {{ request()->routeIs('tickets.create') ? 'active' : '' }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Nuevo ticket
            </a>
        @endcan
        <div class="nav-mobile-sep"></div>
        @can('create', \App\Models\Location::class)
            <a href="{{ route('locations.index') }}" class="nav-mobile-link {{ request()->routeIs('locations.*') ? 'active' : '' }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Ubicaciones
            </a>
        @endcan
        @can('create', \App\Models\Category::class)
            <a href="{{ route('categories.index') }}" class="nav-mobile-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                Categorías
            </a>
        @endcan
        @can('viewAny', \App\Models\User::class)
            <a href="{{ route('users.index') }}" class="nav-mobile-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Usuarios
            </a>
        @endcan
        <div class="nav-mobile-sep"></div>
        <a href="{{ route('profile.edit') }}" class="nav-mobile-link">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Perfil
        </a>
        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="nav-mobile-link" style="width:100%;background:none;border:none;cursor:pointer;color:#fca5a5;font-family:inherit;font-weight:600;font-size:.9rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Cerrar sesión
            </button>
        </form>
    </div>
    @endauth
</header>

{{-- ===== MAIN CONTENT ===== --}}
<main class="app-shell">
    @yield('content')
</main>

{{-- Hamburger JS --}}
<script>
    (function() {
        const btn = document.getElementById('navHamburger');
        const drawer = document.getElementById('navDrawer');
        if (!btn || !drawer) return;
        btn.addEventListener('click', function() {
            drawer.classList.toggle('open');
            const spans = btn.querySelectorAll('span');
            const isOpen = drawer.classList.contains('open');
            spans[0].style.transform = isOpen ? 'translateY(7px) rotate(45deg)' : '';
            spans[1].style.opacity  = isOpen ? '0' : '';
            spans[2].style.transform = isOpen ? 'translateY(-7px) rotate(-45deg)' : '';
        });
        // Cerrar dropdown al click fuera
        document.addEventListener('click', function(e) {
            document.querySelectorAll('details.user-menu[open]').forEach(function(d) {
                if (!d.contains(e.target)) d.removeAttribute('open');
            });
        });
    })();
</script>

</body>
</html>
