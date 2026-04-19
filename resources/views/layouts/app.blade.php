<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Tick System Onn')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            :root {
                --slate-950: #020617;
                --slate-900: #0f172a;
                --slate-700: #334155;
                --slate-600: #475569;
                --slate-500: #64748b;
                --slate-300: #cbd5e1;
                --slate-200: #e2e8f0;
                --slate-100: #f1f5f9;
                --blue-600: #2563eb;
                --blue-700: #1d4ed8;
                --red-100: #fee2e2;
                --red-700: #b91c1c;
                --green-100: #dcfce7;
                --green-700: #166534;
            }

            * {
                box-sizing: border-box;
            }

            body.app-page {
                margin: 0;
                min-height: 100vh;
                color: var(--slate-900);
                background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
                font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif;
            }

            .app-nav {
                position: sticky;
                top: 0;
                z-index: 40;
                border-bottom: 1px solid var(--slate-200);
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(6px);
            }

            .app-nav-inner {
                width: min(1140px, calc(100% - 2rem));
                margin: 0 auto;
                min-height: 4.2rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
            }

            .brand {
                font-size: 1.05rem;
                font-weight: 800;
                letter-spacing: -0.01em;
                color: var(--slate-950);
                text-decoration: none;
            }

            .menu-links {
                display: flex;
                align-items: center;
                gap: 0.8rem;
            }

            .menu-link {
                text-decoration: none;
                color: var(--slate-700);
                font-size: 0.93rem;
                padding: 0.45rem 0.6rem;
                border-radius: 8px;
            }

            .menu-link:hover {
                color: var(--slate-900);
                background: var(--slate-100);
            }

            .app-shell {
                width: min(1140px, calc(100% - 2rem));
                margin: 0 auto;
                padding: 1.5rem 0 2rem;
            }

            .panel {
                background: #fff;
                border: 1px solid var(--slate-200);
                border-radius: 16px;
                box-shadow: 0 16px 26px -26px rgba(15, 23, 42, 0.65);
            }

            .panel-pad {
                padding: 1.1rem 1.2rem;
            }

            .field {
                width: 100%;
                border: 1px solid var(--slate-300);
                border-radius: 10px;
                background: #fff;
                padding: 0.62rem 0.7rem;
                color: var(--slate-900);
            }

            .field:focus {
                outline: 2px solid #bfdbfe;
                border-color: #60a5fa;
            }

            .btn-primary,
            .btn-secondary,
            .btn-danger {
                border-radius: 10px;
                padding: 0.6rem 0.95rem;
                font-size: 0.93rem;
                font-weight: 600;
                text-decoration: none;
                border: 0;
                cursor: pointer;
            }

            .btn-primary {
                background: var(--blue-600);
                color: #fff;
            }

            .btn-primary:hover {
                background: var(--blue-700);
            }

            .btn-secondary {
                background: #fff;
                color: var(--slate-700);
                border: 1px solid var(--slate-300);
            }

            .btn-secondary:hover {
                background: var(--slate-100);
            }

            .btn-danger {
                width: 100%;
                text-align: left;
                background: #fff;
                color: #991b1b;
                border: 1px solid #fecaca;
            }

            .btn-danger:hover {
                background: #fef2f2;
            }

            .alert-error,
            .alert-success {
                border-radius: 12px;
                padding: 0.8rem 0.95rem;
                font-size: 0.93rem;
            }

            .alert-error {
                background: var(--red-100);
                color: var(--red-700);
                border: 1px solid #fecaca;
            }

            .alert-success {
                background: var(--green-100);
                color: var(--green-700);
                border: 1px solid #bbf7d0;
            }

            .user-menu {
                position: relative;
            }

            .user-menu summary {
                list-style: none;
                display: flex;
                align-items: center;
                gap: 0.55rem;
                padding: 0.4rem 0.65rem;
                border-radius: 10px;
                border: 1px solid var(--slate-300);
                background: #fff;
                cursor: pointer;
                font-size: 0.85rem;
                color: var(--slate-700);
            }

            .user-menu summary::-webkit-details-marker {
                display: none;
            }

            .user-menu summary:hover {
                border-color: #94a3b8;
            }

            .user-menu[open] summary {
                border-color: #94a3b8;
            }

            .user-menu-panel {
                position: absolute;
                top: calc(100% + 0.45rem);
                right: 0;
                min-width: 260px;
                background: #fff;
                border: 1px solid var(--slate-200);
                border-radius: 12px;
                box-shadow: 0 14px 20px -20px rgba(15, 23, 42, 0.7);
                padding: 0.85rem;
                z-index: 50;
            }

            .user-name {
                font-size: 0.86rem;
                font-weight: 700;
                color: var(--slate-900);
            }

            .user-email {
                font-size: 0.79rem;
                color: var(--slate-500);
                margin-top: 0.2rem;
                margin-bottom: 0.7rem;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th,
            td {
                text-align: left;
                padding: 0.7rem 0.8rem;
                border-top: 1px solid var(--slate-200);
                font-size: 0.9rem;
            }

            thead th {
                border-top: 0;
                background: #f8fafc;
                color: var(--slate-600);
                font-size: 0.78rem;
                letter-spacing: 0.02em;
                text-transform: uppercase;
                font-weight: 700;
            }

            @media (max-width: 768px) {
                .app-nav-inner,
                .app-shell {
                    width: calc(100% - 1.2rem);
                }

                .menu-links {
                    gap: 0.4rem;
                }

                .user-menu summary .hide-mobile {
                    display: none;
                }
            }
        </style>
    @endif
</head>
<body class="app-page min-h-screen bg-slate-100 text-slate-900">
<header class="app-nav border-b border-slate-200 bg-white/90 backdrop-blur">
    <div class="app-nav-inner max-w-6xl mx-auto px-4 min-h-[4.2rem] flex items-center justify-between gap-4">
        <a href="{{ auth()->check() ? route('dashboard.index') : url('/') }}" class="brand text-lg font-black tracking-tight text-slate-950">Tick System Onn</a>

        <nav class="menu-links flex items-center gap-2 md:gap-3">
            @auth
                @can('viewAny', \App\Models\Ticket::class)
                    <a href="{{ route('dashboard.index') }}" class="menu-link text-sm text-slate-700 hover:bg-slate-100 rounded-md px-2.5 py-1.5">Dashboard</a>
                @endcan

                @can('viewAny', \App\Models\Ticket::class)
                    <a href="{{ route('tickets.index') }}" class="menu-link text-sm text-slate-700 hover:bg-slate-100 rounded-md px-2.5 py-1.5">Tickets</a>
                @endcan

                @can('create', \App\Models\Ticket::class)
                    <a href="{{ route('tickets.create') }}" class="menu-link text-sm text-slate-700 hover:bg-slate-100 rounded-md px-2.5 py-1.5">Nuevo</a>
                @endcan

                @can('create', \App\Models\Location::class)
                    <a href="{{ route('locations.index') }}" class="menu-link text-sm text-slate-700 hover:bg-slate-100 rounded-md px-2.5 py-1.5">Ubicaciones</a>
                @endcan

                @can('create', \App\Models\Category::class)
                    <a href="{{ route('categories.index') }}" class="menu-link text-sm text-slate-700 hover:bg-slate-100 rounded-md px-2.5 py-1.5">Categorias</a>
                @endcan

                @can('viewAny', \App\Models\User::class)
                    <a href="{{ route('users.index') }}" class="menu-link text-sm text-slate-700 hover:bg-slate-100 rounded-md px-2.5 py-1.5">Usuarios</a>
                @endcan

                <details class="user-menu relative">
                    <summary class="flex items-center gap-2 border border-slate-300 rounded-lg px-2.5 py-1.5 text-slate-700 text-sm cursor-pointer select-none">
                        <span class="hide-mobile max-w-[120px] truncate">{{ auth()->user()->name }}</span>
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </summary>

                    <div class="user-menu-panel absolute right-0 mt-2 min-w-[240px] bg-white border border-slate-200 rounded-xl shadow-xl shadow-slate-900/10 p-3">
                        <p class="user-name font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                        <p class="user-email text-xs text-slate-500 mb-3">{{ auth()->user()->email }}</p>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-danger w-full text-left border border-red-200 text-red-700 rounded-lg px-3 py-2 hover:bg-red-50">
                                Cerrar sesion
                            </button>
                        </form>

                        <a href="{{ route('profile.edit') }}" class="btn-secondary mt-2 block w-full text-left border border-slate-300 rounded-lg px-3 py-2 hover:bg-slate-100">
                            Perfil de usuario
                        </a>
                    </div>
                </details>
            @else
                <a href="{{ route('login') }}" class="menu-link text-sm text-slate-700 hover:bg-slate-100 rounded-md px-2.5 py-1.5">Iniciar sesion</a>
                <a href="{{ route('register') }}" class="btn-primary bg-blue-600 text-white rounded-lg px-3 py-2 text-sm hover:bg-blue-700">Registrarse</a>
            @endauth
        </nav>
    </div>
</header>

<main class="app-shell max-w-6xl mx-auto p-4 md:p-6">
    @yield('content')
</main>
</body>
</html>
