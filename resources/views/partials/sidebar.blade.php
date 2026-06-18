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
            <span class="sidebar-brand-text">INCIDEX</span>
        </a>
        {{-- Close button (visible only on mobile) --}}
        <button class="sidebar-close" id="sidebarCloseBtn" aria-label="Cerrar menú">
            <x-lucide-x width="20" height="20" stroke-width="2" />
        </button>
    </div>

    {{-- ── Navigation ── --}}
    <nav class="sidebar-nav">

        @php
            // Reporter-only users get the redesigned reporter surfaces (dashboard,
            // board, history); every other role keeps the classic routes. The
            // original /dashboard and /tickets routes stay intact either way.
            $isReporterOnly = auth()->user()?->hasRole('reporter')
                && !auth()->user()?->hasAnyRole(['maintenance', 'admin', 'super_admin']);
        @endphp

        {{-- PRINCIPAL --}}
        @can('viewAny', \App\Models\Ticket::class)
        <div class="sidebar-section">
            <p class="sidebar-heading">Principal</p>
            <ul class="sidebar-menu">
                <li>
                    <a href="{{ $isReporterOnly ? route('reporter.dashboard') : route('dashboard.index') }}"
                       class="sidebar-link {{ ($isReporterOnly ? request()->routeIs('reporter.dashboard') : request()->routeIs('dashboard.*')) ? 'active' : '' }}">
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
                @php
                    $ticketsHref = $isReporterOnly ? route('reporter.tickets.index') : route('tickets.index');
                    $ticketsActive = $isReporterOnly
                        ? (request()->routeIs('reporter.tickets.index') || request()->routeIs('reporter.tickets.show'))
                        : (request()->routeIs('tickets.index') || request()->routeIs('tickets.show'));
                @endphp
                <li>
                    <a href="{{ $ticketsHref }}"
                       class="sidebar-link {{ $ticketsActive ? 'active' : '' }}">
                        <x-lucide-file class="sidebar-icon" width="18" height="18" stroke-width="2" />
                        <span class="sidebar-label">{{ $isReporterOnly ? 'Mis tickets' : 'Tickets' }}</span>
                    </a>
                </li>
                @endcan
                @role('maintenance')
                <li>
                    <a href="{{ route('tickets.assignments') }}"
                       class="sidebar-link {{ request()->routeIs('tickets.assignments') ? 'active' : '' }}">
                        <x-lucide-list-checks class="sidebar-icon" width="18" height="18" stroke-width="2" />
                        <span class="sidebar-label">Mis asignaciones</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('tickets.history') }}"
                       class="sidebar-link {{ request()->routeIs('tickets.history') ? 'active' : '' }}">
                        <x-lucide-history class="sidebar-icon" width="18" height="18" stroke-width="2" />
                        <span class="sidebar-label">Historial</span>
                    </a>
                </li>
                @endrole
                @can('create', \App\Models\Ticket::class)
                <li>
                    <a href="{{ route('tickets.create') }}"
                       class="sidebar-link {{ request()->routeIs('tickets.create') ? 'active' : '' }}">
                        <x-lucide-plus-circle class="sidebar-icon" width="18" height="18" stroke-width="2" />
                        <span class="sidebar-label">Crear Ticket</span>
                    </a>
                </li>
                @endcan
                {{-- Reporter-only "Historial" (own closed tickets). Maintenance has its
                     own /tickets/history above; admins use the classic Tickets list. --}}
                @if($isReporterOnly)
                <li>
                    <a href="{{ route('reporter.tickets.history') }}"
                       class="sidebar-link {{ request()->routeIs('reporter.tickets.history') ? 'active' : '' }}">
                        <x-lucide-history class="sidebar-icon" width="18" height="18" stroke-width="2" />
                        <span class="sidebar-label">Historial</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('reporter.community') }}"
                       class="sidebar-link {{ request()->routeIs('reporter.community') ? 'active' : '' }}">
                        <x-lucide-users class="sidebar-icon" width="18" height="18" stroke-width="2" />
                        <span class="sidebar-label">Comunidad</span>
                    </a>
                </li>
                @endif
            </ul>
        </div>
        @endcanany

        {{-- AYUDA (reporter-only) --}}
        @if($isReporterOnly)
        <div class="sidebar-section">
            <p class="sidebar-heading">Ayuda</p>
            <ul class="sidebar-menu">
                <li>
                    <a href="{{ route('reporter.guide') }}"
                       class="sidebar-link {{ request()->routeIs('reporter.guide') ? 'active' : '' }}">
                        <x-lucide-book-open class="sidebar-icon" width="18" height="18" stroke-width="2" />
                        <span class="sidebar-label">Guía de reporte</span>
                    </a>
                </li>
            </ul>
        </div>
        @endif

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
