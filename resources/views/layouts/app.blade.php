<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Incidencias OPS') — Sistema de Incidencias</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800|dm-mono:400,500" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body { margin: 0; font-family: 'Instrument Sans', system-ui, sans-serif; background: #f1f5f9; color: #0f172a; }
        </style>
    @endif
</head>
<body class="app-body">

{{-- ════════════════════════════════ NAVBAR ════════════════════════════════ --}}
<header class="app-nav" role="banner">
    <div class="app-nav-inner">

        {{-- Brand --}}
        <a href="{{ auth()->check() ? route('dashboard.index') : url('/') }}" class="app-brand" aria-label="Incidencias OPS — Inicio">
            <span class="app-brand-icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="28" height="28" rx="7" fill="#1e40af"/>
                    <path d="M14 6.5L14 10M14 18v3.5M6.5 14H10M18 14h3.5" stroke="#93c5fd" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="14" cy="14" r="3.5" fill="#3b82f6" stroke="#bfdbfe" stroke-width="1.5"/>
                    <circle cx="14" cy="14" r="1.2" fill="#fff"/>
                </svg>
            </span>
            <span class="app-brand-name">
                Incidencias <span class="app-brand-ops">OPS</span>
            </span>
        </a>

        @auth
        {{-- Primary nav links --}}
        <nav class="app-nav-links" aria-label="Navegación principal">
            @can('viewAny', \App\Models\Ticket::class)
                <a href="{{ route('dashboard.index') }}" class="app-nav-link {{ request()->routeIs('dashboard.*') ? 'app-nav-link--active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    Dashboard
                </a>
            @endcan

            @can('viewAny', \App\Models\Ticket::class)
                <a href="{{ route('tickets.index') }}" class="app-nav-link {{ request()->routeIs('tickets.*') ? 'app-nav-link--active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3L14.5 4z"/>
                        <circle cx="12" cy="13" r="3"/>
                    </svg>
                    Tickets
                </a>
            @endcan

            @can('create', \App\Models\Ticket::class)
                <a href="{{ route('tickets.create') }}" class="app-nav-link app-nav-link--create {{ request()->routeIs('tickets.create') ? 'app-nav-link--active' : '' }}">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    Nuevo ticket
                </a>
            @endcan

            @can('create', \App\Models\Location::class)
                <a href="{{ route('locations.index') }}" class="app-nav-link {{ request()->routeIs('locations.*') ? 'app-nav-link--active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                    </svg>
                    Ubicaciones
                </a>
            @endcan

            @can('create', \App\Models\Category::class)
                <a href="{{ route('categories.index') }}" class="app-nav-link {{ request()->routeIs('categories.*') ? 'app-nav-link--active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 6h18M3 12h18M3 18h18"/>
                    </svg>
                    Categorías
                </a>
            @endcan

            @can('viewAny', \App\Models\User::class)
                <a href="{{ route('users.index') }}" class="app-nav-link {{ request()->routeIs('users.*') ? 'app-nav-link--active' : '' }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Usuarios
                </a>
            @endcan
        </nav>

        {{-- User menu --}}
        <div class="app-user-area">
            <details class="app-user-menu">
                <summary class="app-user-trigger" aria-label="Menú de usuario — {{ auth()->user()->name }}">
                    <span class="app-user-avatar" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </span>
                    <span class="app-user-name">{{ auth()->user()->name }}</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 9l6 6 6-6"/>
                    </svg>
                </summary>

                <div class="app-user-panel" role="menu">
                    <div class="app-user-panel-info">
                        <p class="app-user-panel-name">{{ auth()->user()->name }}</p>
                        <p class="app-user-panel-email">{{ auth()->user()->email }}</p>
                    </div>

                    <a href="{{ route('profile.edit') }}" class="app-user-panel-link" role="menuitem">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        Perfil de usuario
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="app-user-panel-form">
                        @csrf
                        <button type="submit" class="app-user-panel-logout" role="menuitem">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </details>
        </div>
        @else
        <div class="app-nav-guest">
            <a href="{{ route('login') }}" class="app-nav-btn app-nav-btn--ghost">Iniciar sesión</a>
            <a href="{{ route('register') }}" class="app-nav-btn app-nav-btn--primary">Registrarse</a>
        </div>
        @endauth
    </div>
</header>

{{-- ════════════════════════════════ MAIN ════════════════════════════════ --}}
<main class="app-shell" id="main-content">
    @yield('content')
</main>

</body>
</html>