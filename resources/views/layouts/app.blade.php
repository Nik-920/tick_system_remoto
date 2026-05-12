<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Tick System Onn')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800,900" rel="stylesheet" />

    {{-- Theme: apply before first paint to avoid flash --}}
    <script>
        (function(){
            var t = localStorage.getItem('tick-theme');
            if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
        })();
    </script>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
    <style>
        /* ============================================================
           TICK SYSTEM ONN — INLINE FALLBACK (sin Vite)
           Layout: Sidebar + Topbar
           ============================================================ */

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-w: 260px;
            --topbar-h: 62px;
            --sidebar-bg: #ffffff;
            --sidebar-border: #e9ecef;
            --sidebar-text: #6c757d;
            --sidebar-text-hover: #313a46;
            --sidebar-heading-color: #adb5bd;
            --sidebar-active-bg: #f0f6ff;
            --sidebar-active-text: #3b82f6;
            --sidebar-active-border: #3b82f6;
            --topbar-bg: #ffffff;
            --topbar-border: #e9ecef;
            --topbar-text: #6c757d;
            --topbar-icon-color: #6c757d;
            --topbar-icon-hover: #313a46;
            --content-bg: #f1f4f8;

            --radius: 14px;
            --radius-sm: 10px;
            --radius-xs: 8px;
            --shadow-xs: 0 1px 3px rgba(15,23,42,0.07), 0 1px 2px rgba(15,23,42,0.04);
            --shadow-sm: 0 4px 12px rgba(15,23,42,0.08), 0 2px 4px rgba(15,23,42,0.04);
            --shadow-md: 0 8px 24px rgba(15,23,42,0.10), 0 4px 8px rgba(15,23,42,0.06);
            --shadow-lg: 0 16px 40px rgba(15,23,42,0.14), 0 8px 16px rgba(15,23,42,0.08);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --bg-page: #f1f4f8;
            --bg-surface: #ffffff;
            --bg-soft: #f8fafc;
            --text-primary: #0d1829;
            --text-secondary: #334155;
            --text-muted: #64748b;
            --border-default: #e2e8f0;
            --border-light: #f1f5f9;
        }

        [data-theme="dark"] {
            --sidebar-bg: #1a2332;
            --sidebar-border: rgba(255,255,255,0.08);
            --sidebar-text: rgba(255,255,255,0.60);
            --sidebar-text-hover: rgba(255,255,255,0.90);
            --sidebar-heading-color: rgba(255,255,255,0.30);
            --sidebar-active-bg: rgba(59,130,246,0.14);
            --sidebar-active-text: #60a5fa;
            --sidebar-active-border: #60a5fa;
            --topbar-bg: #1e293b;
            --topbar-border: rgba(255,255,255,0.08);
            --topbar-text: rgba(255,255,255,0.60);
            --topbar-icon-color: rgba(255,255,255,0.50);
            --topbar-icon-hover: rgba(255,255,255,0.90);
            --content-bg: #0f172a;
            --bg-page: #0f172a;
            --bg-surface: #1e293b;
            --bg-soft: #263245;
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-default: rgba(255,255,255,0.10);
            --border-light: rgba(255,255,255,0.05);
        }

        body {
            font-family: 'Instrument Sans', system-ui, -apple-system, sans-serif;
            background: var(--content-bg);
            color: var(--text-primary);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Layout ── */
        .admin-layout { min-height: 100vh; }
        .admin-content-wrapper { display: flex; flex-direction: column; min-height: 100vh; min-width: 0; margin-left: var(--sidebar-w); transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .admin-layout.sidebar-collapsed .admin-content-wrapper { margin-left: 0; }
        .admin-main { flex: 1; padding: 1.5rem; max-width: 1280px; width: 100%; margin: 0 auto; }

        /* ── Sidebar ── */
        .admin-sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sidebar-w); background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex; flex-direction: column; z-index: 60;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }
        .sidebar-collapsed .admin-sidebar { transform: translateX(-100%); }

        .sidebar-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 1.25rem; height: var(--topbar-h);
            border-bottom: 1px solid var(--sidebar-border); flex-shrink: 0;
        }
        .sidebar-brand { display: flex; align-items: center; gap: 0.6rem; text-decoration: none; }
        .sidebar-brand-icon {
            width: 34px; height: 34px; border-radius: 9px;
            background: linear-gradient(135deg, #1d4ed8, #60a5fa);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 14px rgba(59,130,246,0.45);
        }
        .sidebar-brand-text { font-size: 1.05rem; font-weight: 900; color: var(--sidebar-text-hover); white-space: nowrap; }
        .sidebar-brand-text span { color: #3b82f6; }
        [data-theme="dark"] .sidebar-brand-text { color: #ffffff; }

        .sidebar-close {
            display: none; background: none; border: none;
            color: var(--sidebar-text); cursor: pointer; padding: 0.3rem; border-radius: 6px;
        }

        .sidebar-nav { flex: 1; overflow-y: auto; padding: 0.75rem 0; }
        .sidebar-section { margin-bottom: 0.5rem; }
        .sidebar-heading {
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; color: var(--sidebar-heading-color);
            padding: 0.6rem 1.25rem 0.35rem; margin: 0;
        }
        .sidebar-menu { list-style: none; margin: 0; padding: 0; }
        .sidebar-link {
            display: flex; align-items: center; gap: 0.65rem;
            padding: 0.55rem 1.25rem; margin: 1px 0.6rem; border-radius: 8px;
            text-decoration: none; color: var(--sidebar-text);
            font-size: 0.875rem; font-weight: 500; transition: all 0.15s ease;
            border-left: 3px solid transparent;
        }
        .sidebar-link:hover { color: var(--sidebar-text-hover); background: rgba(0,0,0,0.04); }
        [data-theme="dark"] .sidebar-link:hover { background: rgba(255,255,255,0.06); }
        .sidebar-link.active {
            color: var(--sidebar-active-text); background: var(--sidebar-active-bg);
            font-weight: 600; border-left-color: var(--sidebar-active-border);
        }
        .sidebar-icon { flex-shrink: 0; opacity: 0.75; }
        .sidebar-link.active .sidebar-icon { opacity: 1; }

        /* ── Topbar ── */
        .admin-topbar {
            position: sticky; top: 0; z-index: 50;
            height: var(--topbar-h); background: var(--topbar-bg);
            border-bottom: 1px solid var(--topbar-border); flex-shrink: 0;
        }
        .topbar-inner { display: flex; align-items: center; justify-content: space-between; height: 100%; padding: 0 1.25rem; gap: 1rem; }
        .topbar-left { display: flex; align-items: center; gap: 0.75rem; }
        .topbar-right { display: flex; align-items: center; gap: 0.35rem; }

        .topbar-toggle, .topbar-icon-btn {
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 8px;
            border: none; background: none; color: var(--topbar-icon-color);
            cursor: pointer; transition: all 0.15s ease;
        }
        .topbar-toggle:hover, .topbar-icon-btn:hover {
            background: rgba(0,0,0,0.06); color: var(--topbar-icon-hover);
        }
        [data-theme="dark"] .topbar-toggle:hover,
        [data-theme="dark"] .topbar-icon-btn:hover { background: rgba(255,255,255,0.08); }

        .topbar-icon-btn { position: relative; }
        .topbar-icon-label { font-size: 0.7rem; font-weight: 700; }
        .topbar-badge {
            position: absolute; top: 4px; right: 4px; min-width: 16px; height: 16px;
            border-radius: 99px; background: #ef4444; color: white;
            font-size: 0.6rem; font-weight: 700; display: flex;
            align-items: center; justify-content: center; padding: 0 4px;
        }
        .topbar-sep { width: 1px; height: 24px; background: var(--topbar-border); margin: 0 0.4rem; }

        .theme-icon-dark { display: none; }
        .theme-icon-light { display: block; }
        [data-theme="dark"] .theme-icon-dark { display: block; }
        [data-theme="dark"] .theme-icon-light { display: none; }

        .topbar-user-menu { position: relative; }
        .topbar-user-trigger {
            list-style: none; display: flex; align-items: center; gap: 0.5rem;
            padding: 0.35rem 0.55rem; border-radius: 10px;
            border: 1px solid var(--topbar-border); background: transparent;
            cursor: pointer; transition: all 0.15s ease; user-select: none;
        }
        .topbar-user-trigger::-webkit-details-marker { display: none; }
        .topbar-user-trigger:hover { background: rgba(0,0,0,0.04); }
        [data-theme="dark"] .topbar-user-trigger { border-color: rgba(255,255,255,0.12); }
        [data-theme="dark"] .topbar-user-trigger:hover { background: rgba(255,255,255,0.06); }

        .topbar-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #1d4ed8, #60a5fa);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.72rem; font-weight: 800; color: white;
            box-shadow: 0 2px 8px rgba(29,78,216,0.35);
        }
        .topbar-user-info { display: flex; flex-direction: column; text-align: left; }
        .topbar-user-name {
            font-size: 0.82rem; font-weight: 700; color: var(--topbar-text);
            max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; line-height: 1.2;
        }
        [data-theme="dark"] .topbar-user-name { color: rgba(255,255,255,0.9); }
        .topbar-user-role { font-size: 0.68rem; color: var(--sidebar-heading-color); line-height: 1.2; }
        .topbar-chevron { color: var(--topbar-icon-color); transition: transform 0.2s ease; }
        .topbar-user-menu[open] .topbar-chevron { transform: rotate(180deg); }

        .topbar-dropdown {
            position: absolute; top: calc(100% + 0.5rem); right: 0;
            min-width: 260px; background: var(--bg-surface);
            border: 1px solid var(--border-default); border-radius: 14px;
            box-shadow: var(--shadow-lg); z-index: 100; overflow: hidden;
            animation: dropdownSlide 0.15s ease both;
        }
        @keyframes dropdownSlide {
            from { opacity: 0; transform: translateY(-6px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .topbar-dropdown-head {
            padding: 1rem; border-bottom: 1px solid var(--border-default);
            display: flex; align-items: center; gap: 0.75rem; background: var(--bg-soft);
        }
        .topbar-dropdown-avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg, #1d4ed8, #60a5fa);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; font-weight: 800; color: white;
            box-shadow: 0 4px 10px rgba(29,78,216,0.3);
        }
        .topbar-dropdown-name { font-size: 0.88rem; font-weight: 800; color: var(--text-primary); margin: 0; }
        .topbar-dropdown-email { font-size: 0.75rem; color: var(--text-muted); margin: 0.1rem 0 0; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .topbar-dropdown-body { padding: 0.5rem; }
        .topbar-dropdown-item {
            display: flex; align-items: center; gap: 0.6rem;
            padding: 0.6rem 0.75rem; border-radius: 9px;
            text-decoration: none; font-size: 0.85rem; font-weight: 600;
            color: var(--text-secondary); transition: all 0.12s ease;
            border: none; background: none; cursor: pointer; width: 100%; text-align: left; font-family: inherit;
        }
        .topbar-dropdown-item:hover { background: var(--bg-soft); color: var(--text-primary); }
        .topbar-dropdown-item--danger { color: #b91c1c; }
        .topbar-dropdown-item--danger:hover { background: #fff1f2; color: #991b1b; }
        [data-theme="dark"] .topbar-dropdown-item--danger:hover { background: rgba(239,68,68,0.1); }
        .topbar-dropdown-divider { height: 1px; background: var(--border-light); margin: 0.25rem 0.6rem; }

        /* ── Overlay ── */
        .sidebar-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.45); z-index: 55; opacity: 0; transition: opacity 0.3s ease;
        }
        .sidebar-overlay.active { display: block; opacity: 1; }

        /* ── Base components ── */
        .panel { background: var(--bg-surface); border: 1px solid var(--border-default); border-radius: var(--radius); box-shadow: var(--shadow-xs); }
        .panel-pad { padding: 1.1rem 1.375rem; }
        .field { width: 100%; border: 1px solid var(--border-default); border-radius: var(--radius-xs); background: var(--bg-surface); padding: 0.65rem 0.8rem; font-size: 0.875rem; color: var(--text-primary); font-family: inherit; transition: var(--transition); appearance: none; }
        .field:hover { border-color: #cbd5e1; }
        .field:focus { outline: 2px solid #3b82f6; outline-offset: 0; border-color: #3b82f6; }

        .btn-primary {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
            background: #1d4ed8; color: white; border: none; border-radius: var(--radius-xs);
            padding: 0.6rem 1.1rem; font-size: 0.875rem; font-weight: 700; font-family: inherit;
            cursor: pointer; text-decoration: none; transition: var(--transition); white-space: nowrap;
        }
        .btn-primary:hover { background: #1e3a8a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(29,78,216,0.3); }

        .btn-secondary {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
            background: var(--bg-surface); color: var(--text-secondary);
            border: 1px solid var(--border-default); border-radius: var(--radius-xs);
            padding: 0.6rem 1.1rem; font-size: 0.875rem; font-weight: 700; font-family: inherit;
            cursor: pointer; text-decoration: none; transition: var(--transition); white-space: nowrap;
        }
        .btn-secondary:hover { background: var(--bg-soft); border-color: #cbd5e1; }

        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: var(--radius-xs); padding: 0.8rem 1rem; font-size: 0.875rem; font-weight: 600; }
        .alert-error { background: #fff1f2; color: #b91c1c; border: 1px solid #fecaca; border-radius: var(--radius-xs); padding: 0.8rem 1rem; font-size: 0.875rem; }

        .overflow-hidden { overflow: hidden; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; }

        /* ── Dark mode — panels ── */
        [data-theme="dark"] .panel { background: var(--bg-surface); border-color: var(--border-default); }
        [data-theme="dark"] .field { background: var(--bg-soft); border-color: var(--border-default); color: var(--text-primary); }
        [data-theme="dark"] .btn-secondary { background: var(--bg-soft); border-color: var(--border-default); color: var(--text-secondary); }

        /* ── Animations ── */
        @keyframes dashboardFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes dashboardRise {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Responsive ── */
        @media (max-width: 767px) {
            .admin-content-wrapper { margin-left: 0; }
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.sidebar-open { transform: translateX(0); }
            .sidebar-close { display: flex; }
            .admin-main { padding: 1rem; }
            .topbar-user-info { display: none; }
            .topbar-icon-label { display: none; }
        }
        @media (min-width: 768px) and (max-width: 1023px) {
            :root { --sidebar-w: 220px; }
            .admin-main { padding: 1.25rem; }
            .topbar-user-role { display: none; }
        }
    </style>
    @endif
</head>

<body>

@auth
{{-- ===== ADMIN LAYOUT (authenticated) ===== --}}
<div class="admin-layout" id="adminLayout">

    {{-- Sidebar --}}
    @include('partials.sidebar')

    {{-- Content wrapper: topbar + main --}}
    <div class="admin-content-wrapper">
        @include('partials.topbar')

        <main class="admin-main">
            @yield('content')
        </main>
    </div>

</div>

{{-- Mobile sidebar overlay --}}
<div class="sidebar-overlay" id="sidebarOverlay"></div>

@else
{{-- ===== GUEST LAYOUT ===== --}}
<main class="app-shell" style="max-width:1280px;margin:0 auto;padding:2rem 1.5rem;">
    @yield('content')
</main>
@endauth

{{-- ===== Layout JS fallback (when Vite is not compiled) ===== --}}
@if (!file_exists(public_path('build/manifest.json')) && !file_exists(public_path('hot')))
<script>
    (function() {
        var layout    = document.getElementById('adminLayout');
        var sidebar   = document.getElementById('adminSidebar');
        var overlay   = document.getElementById('sidebarOverlay');
        var toggleBtn = document.getElementById('sidebarToggleBtn');
        var closeBtn  = document.getElementById('sidebarCloseBtn');
        var themeBtn  = document.getElementById('themeToggleBtn');

        if (!layout || !sidebar) return;

        function isMobile() { return window.innerWidth < 768; }

        function openMobile() {
            sidebar.classList.add('sidebar-open');
            if (overlay) overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeMobile() {
            sidebar.classList.remove('sidebar-open');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                if (isMobile()) { openMobile(); }
                else {
                    layout.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('tick-sidebar', layout.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
                }
            });
        }

        if (closeBtn) closeBtn.addEventListener('click', closeMobile);
        if (overlay) overlay.addEventListener('click', closeMobile);

        if (!isMobile() && localStorage.getItem('tick-sidebar') === 'collapsed') {
            layout.classList.add('sidebar-collapsed');
        }

        window.addEventListener('resize', function() {
            if (!isMobile() && sidebar.classList.contains('sidebar-open')) closeMobile();
        });

        if (themeBtn) {
            themeBtn.addEventListener('click', function() {
                var html = document.documentElement;
                var current = html.getAttribute('data-theme') || 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-theme', next);
                localStorage.setItem('tick-theme', next);
            });
        }

        document.addEventListener('click', function(e) {
            document.querySelectorAll('details.topbar-user-menu[open]').forEach(function(d) {
                if (!d.contains(e.target)) d.removeAttribute('open');
            });
        });
    })();
</script>
@endif

</body>
</html>
