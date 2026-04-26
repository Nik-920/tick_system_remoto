<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Autenticación') — Incidencias OPS</title>
    <meta name="description" content="Sistema de Reporte de Incidencias — Acceso operativo seguro">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800|dm-mono:400,500" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>
            /* Fallback minimal styles when Vite build not present */
            *, *::before, *::after { box-sizing: border-box; }
            body { margin: 0; font-family: 'Instrument Sans', system-ui, sans-serif; background: #f1f5f9; }
        </style>
    @endif
</head>
<body class="auth-body">

<div class="auth-shell">

    {{-- ══════════════════ LEFT PANEL — Ops Identity ══════════════════ --}}
    <aside class="auth-panel-left" aria-hidden="true">
        {{-- Background grid --}}
        <div class="auth-left-grid"></div>
        {{-- Glow --}}
        <div class="auth-left-glow"></div>

        <div class="auth-left-content">
            {{-- Brand mark --}}
            <a href="{{ url('/') }}" class="auth-left-brand" tabindex="-1">
                <span class="auth-left-brand-icon">
                    <svg width="32" height="32" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="28" height="28" rx="7" fill="#1e40af"/>
                        <path d="M14 6.5L14 10M14 18v3.5M6.5 14H10M18 14h3.5" stroke="#93c5fd" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="14" cy="14" r="3.5" fill="#3b82f6" stroke="#bfdbfe" stroke-width="1.5"/>
                        <circle cx="14" cy="14" r="1.2" fill="#fff"/>
                    </svg>
                </span>
                <span class="auth-left-brand-name">Incidencias <span class="auth-left-brand-ops">OPS</span></span>
            </a>

            {{-- Main copy --}}
            <div class="auth-left-copy">
                <p class="auth-left-overline">Plataforma operativa</p>
                <h2 class="auth-left-headline">Control total sobre cada incidente</h2>
                <p class="auth-left-sub">Reporte, asignación y resolución de incidencias en tiempo real con trazabilidad completa.</p>
            </div>

            {{-- Status indicators --}}
            <div class="auth-left-status-group">
                <div class="auth-left-status-item">
                    <span class="auth-left-status-dot auth-left-status-dot--green"></span>
                    <span class="auth-left-status-label">Sistema operativo</span>
                </div>
                <div class="auth-left-status-item">
                    <span class="auth-left-status-dot auth-left-status-dot--blue"></span>
                    <span class="auth-left-status-label">Tickets activos en curso</span>
                </div>
                <div class="auth-left-status-item">
                    <span class="auth-left-status-dot auth-left-status-dot--amber"></span>
                    <span class="auth-left-status-label">Monitoreo 24/7</span>
                </div>
            </div>

            {{-- Stat strip --}}
            <dl class="auth-left-stats">
                <div class="auth-left-stat">
                    <dt class="auth-left-stat-label">Respuesta</dt>
                    <dd class="auth-left-stat-value">&lt; 5 min</dd>
                </div>
                <div class="auth-left-stat-sep"></div>
                <div class="auth-left-stat">
                    <dt class="auth-left-stat-label">Trazabilidad</dt>
                    <dd class="auth-left-stat-value">100%</dd>
                </div>
                <div class="auth-left-stat-sep"></div>
                <div class="auth-left-stat">
                    <dt class="auth-left-stat-label">Disponible</dt>
                    <dd class="auth-left-stat-value">24 / 7</dd>
                </div>
            </dl>
        </div>

        {{-- Bottom back link --}}
        <div class="auth-left-footer">
            <a href="{{ url('/') }}" class="auth-left-back-link">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M19 12H5M12 5l-7 7 7 7"/>
                </svg>
                Volver al inicio
            </a>
        </div>
    </aside>

    {{-- ══════════════════ RIGHT PANEL — Form ══════════════════ --}}
    <main class="auth-panel-right" id="main-content">
        {{-- Mobile-only header --}}
        <div class="auth-mobile-header">
            <a href="{{ url('/') }}" class="auth-mobile-brand">
                <svg width="22" height="22" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect width="28" height="28" rx="7" fill="#1e40af"/>
                    <circle cx="14" cy="14" r="3.5" fill="#3b82f6" stroke="#bfdbfe" stroke-width="1.5"/>
                    <circle cx="14" cy="14" r="1.2" fill="#fff"/>
                </svg>
                <span>Incidencias <strong>OPS</strong></span>
            </a>
        </div>

        <div class="auth-form-wrap">
            @yield('content')
        </div>

        {{-- Mobile back link --}}
        <div class="auth-mobile-footer">
            <a href="{{ url('/') }}" class="auth-mobile-back">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M19 12H5M12 5l-7 7 7 7"/>
                </svg>
                Volver al inicio
            </a>
        </div>
    </main>

</div>

</body>
</html>