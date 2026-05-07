{{-- ============================================================
   TOPBAR — Barra superior horizontal
   Reutilizable: @include('partials.topbar')
   ============================================================ --}}
<header class="admin-topbar" id="adminTopbar">
    <div class="topbar-inner">

        {{-- ── LEFT: Hamburger toggle ── --}}
        <div class="topbar-left">
            <button class="topbar-toggle" id="sidebarToggleBtn" data-toggle="sidebar" aria-label="Toggle sidebar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
        </div>

        {{-- ── RIGHT: Actions ── --}}
        <div class="topbar-right">

            {{-- Idioma (visual placeholder) --}}
            <div class="topbar-item" title="Idioma">
                <button class="topbar-icon-btn" aria-label="Cambiar idioma">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    <span class="topbar-icon-label">ES</span>
                </button>
            </div>

            {{-- Notificaciones (visual placeholder) --}}
            <div class="topbar-item" title="Notificaciones">
                <button class="topbar-icon-btn" aria-label="Notificaciones">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <span class="topbar-badge">0</span>
                </button>
            </div>

            {{-- Toggle tema claro/oscuro --}}
            <div class="topbar-item" title="Cambiar tema">
                <button class="topbar-icon-btn" id="themeToggleBtn" aria-label="Cambiar tema">
                    {{-- Sol (visible en light mode) --}}
                    <svg class="theme-icon-light" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5"/>
                        <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                        <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                    </svg>
                    {{-- Luna (visible en dark mode) --}}
                    <svg class="theme-icon-dark" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
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
                    <svg class="topbar-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
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
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            Perfil de usuario
                        </a>

                        <div class="topbar-dropdown-divider"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="topbar-dropdown-item topbar-dropdown-item--danger">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                    <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                                </svg>
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
