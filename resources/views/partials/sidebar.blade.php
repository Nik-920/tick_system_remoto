{{-- ============================================================
   SIDEBAR — Navegación lateral principal
   Reutilizable: @include('partials.sidebar')
   ============================================================ --}}
<aside class="admin-sidebar" id="adminSidebar">

    {{-- ── Header: Logo ── --}}
    <div class="sidebar-header">
        <a href="{{ auth()->check() ? route('dashboard.index') : url('/') }}" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <span class="sidebar-brand-text">Tick System <span>Onn</span></span>
        </a>
        {{-- Close button (visible only on mobile) --}}
        <button class="sidebar-close" id="sidebarCloseBtn" aria-label="Cerrar menú">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    {{-- ── Navigation ── --}}
    <nav class="sidebar-nav">

        {{-- PRINCIPAL --}}
        @can('viewAny', \App\Models\Ticket::class)
        <div class="sidebar-section">
            <p class="sidebar-heading">Principal</p>
            <ul class="sidebar-menu">
                <li>
                    <a href="{{ route('dashboard.index') }}"
                       class="sidebar-link {{ request()->routeIs('dashboard.*') ? 'active' : '' }}">
                        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
                        </svg>
                        <span class="sidebar-label">Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>
        @endcan

        {{-- OPERACIONES --}}
        @canany(['viewAny', 'create'], \App\Models\Ticket::class)
        <div class="sidebar-section">
            <p class="sidebar-heading">Operaciones</p>
            <ul class="sidebar-menu">
                @can('viewAny', \App\Models\Ticket::class)
                <li>
                    <a href="{{ route('tickets.index') }}"
                       class="sidebar-link {{ request()->routeIs('tickets.index') || request()->routeIs('tickets.show') ? 'active' : '' }}">
                        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <span class="sidebar-label">Tickets</span>
                    </a>
                </li>
                @endcan
                @can('create', \App\Models\Ticket::class)
                <li>
                    <a href="{{ route('tickets.create') }}"
                       class="sidebar-link {{ request()->routeIs('tickets.create') ? 'active' : '' }}">
                        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/>
                            <line x1="8" y1="12" x2="16" y2="12"/>
                        </svg>
                        <span class="sidebar-label">Crear Ticket</span>
                    </a>
                </li>
                @endcan
            </ul>
        </div>
        @endcanany

        {{-- GESTIÓN --}}
        @if(auth()->user()?->can('create', \App\Models\Location::class) || auth()->user()?->can('create', \App\Models\Category::class))
        <div class="sidebar-section">
            <p class="sidebar-heading">Gestión</p>
            <ul class="sidebar-menu">
                @can('create', \App\Models\Location::class)
                <li>
                    <a href="{{ route('locations.index') }}"
                       class="sidebar-link {{ request()->routeIs('locations.*') ? 'active' : '' }}">
                        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <span class="sidebar-label">Ubicaciones</span>
                    </a>
                </li>
                @endcan
                @can('create', \App\Models\Category::class)
                <li>
                    <a href="{{ route('categories.index') }}"
                       class="sidebar-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span class="sidebar-label">Categorías</span>
                    </a>
                </li>
                @endcan
            </ul>
        </div>
        @endif

        {{-- ADMINISTRACIÓN --}}
        @can('viewAny', \App\Models\User::class)
        <div class="sidebar-section">
            <p class="sidebar-heading">Administración</p>
            <ul class="sidebar-menu">
                <li>
                    <a href="{{ route('users.index') }}"
                       class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <span class="sidebar-label">Usuarios</span>
                    </a>
                </li>
            </ul>
        </div>
        @endcan

    </nav>
</aside>
