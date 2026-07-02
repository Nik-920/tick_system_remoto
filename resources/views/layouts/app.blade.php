<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    {{-- OWASP: Cabeceras de seguridad básicas en HTML --}}
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    <title>@yield('title', 'INCIDEX')</title>

    {{-- Firebase user meta --}}
    @auth
        <meta name="firebase-user-id" content="{{ auth()->id() }}">
    @endauth

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800,900" rel="stylesheet" />

    {{-- Theme: apply before first paint to avoid FOUC --}}
    <script>
        (function () {
            function getThemeCookie() {
                var m = document.cookie.match(/(?:^|;\s*)tick-theme=(light|dark)/);
                return m ? m[1] : null;
            }
            var t;
            try { t = localStorage.getItem('tick-theme'); } catch (e) {}
            if (!t) t = getThemeCookie();
            if (!t) t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- No inline fallback styles. Build assets required. --}}
    @endif
</head>

<body>

@include('partials.upload-error-modal')

@auth
    <div class="admin-layout" id="adminLayout">
        @include('partials.sidebar')
        <div class="admin-content-wrapper">
            @include('partials.topbar')
            <main class="admin-main">
                @yield('content')
            </main>
        </div>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
@else
    <main class="app-shell" style="max-width:1280px;margin:0 auto;padding:2rem 1.5rem;">
        @yield('content')
    </main>
@endauth

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
            function openMobile() { sidebar.classList.add('sidebar-open'); if (overlay) overlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
            function closeMobile() { sidebar.classList.remove('sidebar-open'); if (overlay) overlay.classList.remove('active'); document.body.style.overflow = ''; }

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    if (isMobile()) { openMobile(); }
                    else { layout.classList.toggle('sidebar-collapsed'); localStorage.setItem('tick-sidebar', layout.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded'); }
                });
            }

            if (closeBtn) closeBtn.addEventListener('click', closeMobile);
            if (overlay) overlay.addEventListener('click', closeMobile);
            if (!isMobile() && localStorage.getItem('tick-sidebar') === 'collapsed') { layout.classList.add('sidebar-collapsed'); }
            window.addEventListener('resize', function() { if (!isMobile() && sidebar.classList.contains('sidebar-open')) closeMobile(); });

            if (themeBtn) {
                function applyThemeFallback(theme) {
                    document.documentElement.setAttribute('data-theme', theme);
                    try { localStorage.setItem('tick-theme', theme); } catch(e) {}
                    document.cookie = 'tick-theme=' + theme + ';path=/;max-age=31536000;SameSite=Lax';
                    themeBtn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
                }
                themeBtn.addEventListener('click', function() {
                    var current = document.documentElement.getAttribute('data-theme') || 'light';
                    applyThemeFallback(current === 'dark' ? 'light' : 'dark');
                });
                // Set initial aria-pressed
                applyThemeFallback(document.documentElement.getAttribute('data-theme') || 'light');
            }

            document.addEventListener('click', function(e) {
                document.querySelectorAll('details.topbar-user-menu[open]').forEach(function(d) {
                    if (!d.contains(e.target)) d.removeAttribute('open');
                });
            });
        })();
    </script>
@endif

<x-confirm-modal />
</body>
</html>
