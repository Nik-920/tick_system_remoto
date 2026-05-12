{{-- ============================================================
   SIDEBAR — Navegación lateral principal
   Reutilizable: @include('partials.sidebar')
   ============================================================ --}}
<aside class="admin-sidebar" id="adminSidebar">

    {{-- ── Header: Logo ── --}}
    <div class="sidebar-header">
        <a href="{{ auth()->check() ? route('dashboard.index') : url('/') }}" class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <x-lucide-shield width="20" height="20" stroke="white" stroke-width="2.5" />
            </div>
            <span class="sidebar-brand-text">Tick System <span>Onn</span></span>
        </a>
        {{-- Close button (visible only on mobile) --}}
        <button class="sidebar-close" id="sidebarCloseBtn" aria-label="Cerrar menú">
            <x-lucide-x width="20" height="20" stroke-width="2" />
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
                        <x-lucide-layout-grid class="sidebar-icon" width="18" height="18" stroke-width="2" />
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
                        <x-lucide-file class="sidebar-icon" width="18" height="18" stroke-width="2" />
                        <span class="sidebar-label">Tickets</span>
                    </a>
                </li>
                @endcan
                @can('create', \App\Models\Ticket::class)
                <li>
                    <a href="{{ route('tickets.create') }}"
                       class="sidebar-link {{ request()->routeIs('tickets.create') ? 'active' : '' }}">
                        <x-lucide-plus-circle class="sidebar-icon" width="18" height="18" stroke-width="2" />
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
                        <x-lucide-map-pin class="sidebar-icon" width="18" height="18" stroke-width="2" />
                        <span class="sidebar-label">Ubicaciones</span>
                    </a>
                </li>
                @endcan
                @can('create', \App\Models\Category::class)
                <li>
                    <a href="{{ route('categories.index') }}"
                       class="sidebar-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                        <x-lucide-folder class="sidebar-icon" width="18" height="18" stroke-width="2" />
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
                        <x-lucide-users class="sidebar-icon" width="18" height="18" stroke-width="2" />
                        <span class="sidebar-label">Usuarios</span>
                    </a>
                </li>
            </ul>
        </div>
        @endcan

    </nav>
</aside>
