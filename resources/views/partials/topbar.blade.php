{{-- ============================================================
   TOPBAR — Barra superior horizontal
   ============================================================ --}}
<header class="admin-topbar" id="adminTopbar">
    <div class="topbar-inner">

        {{-- ── LEFT: Hamburger toggle ── --}}
        <div class="topbar-left">
            <button class="topbar-toggle" id="sidebarToggleBtn" data-toggle="sidebar" aria-label="Toggle sidebar">
                <x-lucide-menu width="20" height="20" stroke-width="2" />
            </button>
        </div>

        {{-- ── RIGHT: Actions ── --}}
        <div class="topbar-right">

            {{-- Idioma (visual placeholder) --}}
            <div class="topbar-item" title="Idioma">
                <button class="topbar-icon-btn" aria-label="Cambiar idioma">
                    <x-lucide-globe width="18" height="18" stroke-width="2" />
                    <span class="topbar-icon-label">ES</span>
                </button>
            </div>

            {{-- Notificaciones --}}
            @auth
                <div class="topbar-item topbar-notif-wrapper" title="Notificaciones" id="notifWrapper">
                    <button class="topbar-icon-btn" id="notifBtn" aria-label="Notificaciones">
                        <x-lucide-bell width="18" height="18" stroke-width="2" />
                        <span class="topbar-badge" id="notifBadge" style="display:none;">0</span>
                    </button>

                    {{-- Dropdown notificaciones --}}
                    <div class="notif-dropdown" id="notifDropdown" style="display:none;">
                        <div class="notif-dropdown-head">
                            <span class="notif-dropdown-title">Notificaciones</span>
                            <button class="notif-mark-all" id="notifMarkAll">Marcar todas como leídas</button>
                        </div>
                        <div class="notif-list" id="notifList">
                            <div class="notif-empty">Sin notificaciones</div>
                        </div>
                    </div>
                </div>
            @endauth

            {{-- Toggle tema claro/oscuro --}}
            <div class="topbar-item" title="Cambiar tema">
                <button class="topbar-icon-btn" id="themeToggleBtn" aria-label="Cambiar tema">
                    <x-lucide-sun class="theme-icon-light" width="18" height="18" stroke-width="2" />
                    <x-lucide-moon class="theme-icon-dark" width="18" height="18" stroke-width="2" />
                </button>
            </div>

            {{-- Separador --}}
            <div class="topbar-sep"></div>

            {{-- Perfil de usuario --}}
            @auth
                <details class="topbar-user-menu" id="topbarUserMenu">
                    <summary class="topbar-user-trigger">
                        <div class="topbar-avatar">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(explode(' ', auth()->user()->name)[1] ?? '', 0, 1)) }}
                        </div>
                        <div class="topbar-user-info">
                            <span class="topbar-user-name">{{ auth()->user()->name }}</span>
                            <span class="topbar-user-role">{{ ucfirst(auth()->user()->role ?? 'Usuario') }}</span>
                        </div>
                        <x-lucide-chevron-down class="topbar-chevron" width="14" height="14" stroke-width="2.5" />
                    </summary>

                    <div class="topbar-dropdown">
                        <div class="topbar-dropdown-head">
                            <div class="topbar-dropdown-avatar">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}{{ strtoupper(substr(explode(' ', auth()->user()->name)[1] ?? '', 0, 1)) }}
                            </div>
                            <div>
                                <p class="topbar-dropdown-name">{{ auth()->user()->name }}</p>
                                <p class="topbar-dropdown-email">{{ auth()->user()->email }}</p>
                            </div>
                        </div>

                        <div class="topbar-dropdown-body">
                            <a href="{{ route('profile.edit') }}" class="topbar-dropdown-item">
                                <x-lucide-user width="16" height="16" stroke-width="2" />
                                Perfil de usuario
                            </a>
                            <div class="topbar-dropdown-divider"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="topbar-dropdown-item topbar-dropdown-item--danger">
                                    <x-lucide-log-out width="16" height="16" stroke-width="2" />
                                    Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>
                </details>
            @endauth

        </div>
    </div>
</header>
