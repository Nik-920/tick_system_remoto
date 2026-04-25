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

          *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
 
        html { font-size: 16px; scroll-behavior: smooth; }

          body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text-1);
            min-height: 100dvh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
 
            /* ============================================================
   TICKETS — estilos adicionales
   Pega esto dentro del bloque <style> en layouts/app.blade.php,
   justo después de los estilos que ya tienes.
   ============================================================ */

/* ── Página de tickets ── */
.tickets-page {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ── Hero / cabecera ── */
.tickets-hero-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.tickets-overline {
    font-size: 11px;
    color: var(--slate-500);
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-bottom: .3rem;
}

.tickets-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--slate-950);
    margin: .15rem 0 .4rem;
}

.tickets-subtitle {
    font-size: 0.88rem;
    color: var(--slate-600);
    max-width: 480px;
    line-height: 1.55;
}

/* ── KPI cards ── */
.tickets-kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .75rem;
}

.tickets-kpi-card {
    background: var(--slate-100);
    border-radius: 12px;
    padding: .85rem 1rem;
}

.tickets-kpi-label {
    font-size: 11px;
    color: var(--slate-500);
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: .3rem;
}

.tickets-kpi-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--slate-900);
    line-height: 1.1;
    margin-bottom: .25rem;
}

.tickets-kpi-note {
    font-size: 11px;
    color: var(--slate-500);
}

/* ── Cabecera de sección (filtros / tabla) ── */
.tickets-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-bottom: .85rem;
    border-bottom: 1px solid var(--slate-200);
    margin-bottom: .85rem;
}

.tickets-section-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--slate-900);
}

.tickets-section-note {
    font-size: 0.82rem;
    color: var(--slate-500);
    margin-top: .15rem;
}

.tickets-filter-counter {
    font-size: 11px;
    font-weight: 600;
    background: #eff6ff;
    color: #1d4ed8;
    padding: .25rem .7rem;
    border-radius: 20px;
    white-space: nowrap;
}

/* ── Grid de filtros ── */
.tickets-filter-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .65rem;
}

.tickets-field-label {
    display: block;
    font-size: 11px;
    color: var(--slate-500);
    margin-bottom: .3rem;
    letter-spacing: .04em;
    text-transform: uppercase;
}

.tickets-filter-actions {
    display: flex;
    gap: .5rem;
    align-items: flex-end;
}

.tickets-filter-actions .btn-primary,
.tickets-filter-actions .btn-secondary {
    flex: 1;
    text-align: center;
    display: inline-block;
}

/* ── Cabecera de la tabla ── */
.tickets-dataset-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .65rem 1rem;
    background: var(--slate-100);
    border-bottom: 1px solid var(--slate-200);
}

.tickets-dataset-count {
    font-size: 12px;
    color: var(--slate-600);
}

.tickets-dataset-chip {
    font-size: 11px;
    color: var(--slate-500);
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 20px;
    padding: .2rem .65rem;
}

/* ── Shell de tabla (overflow sin romper bordes) ── */
.tickets-table-shell {
    overflow: hidden;
}

/* ── Chips de estado y prioridad ── */
.tickets-chip {
    display: inline-flex;
    align-items: center;
    padding: .22rem .65rem;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

/* Estado */
.tickets-chip-state-open        { background: #eff6ff; color: #1e40af; }
.tickets-chip-state-in_progress { background: #fef3c7; color: #92400e; }
.tickets-chip-state-resolved    { background: #f0fdf4; color: #166534; }
.tickets-chip-state-rejected    { background: #fef2f2; color: #991b1b; }

/* Prioridad */
.tickets-chip-priority-low      { background: #f0fdf4; color: #166534; }
.tickets-chip-priority-medium   { background: #fef3c7; color: #92400e; }
.tickets-chip-priority-high     { background: #fff7ed; color: #c2410c; }
.tickets-chip-priority-critical { background: #fef2f2; color: #991b1b; }

/* ── Celdas de texto ── */
.tickets-title-cell {
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--slate-900);
}

.tickets-meta-cell {
    font-size: 0.83rem;
    color: var(--slate-600);
}

/* ── Acciones de fila ── */
.tickets-actions {
    display: flex;
    gap: .4rem;
}

.tickets-action-link {
    font-size: 12px;
    font-weight: 600;
    color: #2563eb;
    text-decoration: none;
    padding: .25rem .65rem;
    border-radius: 8px;
    border: 1px solid #bfdbfe;
    background: #fff;
    transition: background .15s;
}

.tickets-action-link:hover {
    background: #eff6ff;
}

/* ── Estado vacío ── */
.tickets-empty-state {
    text-align: center;
    padding: 2.5rem 1rem;
}

.tickets-empty-title {
    font-weight: 700;
    font-size: 1rem;
    color: var(--slate-900);
    margin-bottom: .5rem;
}

.tickets-empty-note {
    font-size: 0.85rem;
    color: var(--slate-500);
    max-width: 420px;
    margin: 0 auto 1.2rem;
    line-height: 1.55;
}

/* ── Paginación ── */
.tickets-pagination {
    display: flex;
    justify-content: center;
    padding: .5rem 0 .25rem;
}

/* Sobreescribe los links de paginación de Laravel */
.tickets-pagination nav {
    display: flex;
    align-items: center;
    gap: .3rem;
}

.tickets-pagination [aria-label="Pagination Navigation"] span,
.tickets-pagination [aria-label="Pagination Navigation"] a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid var(--slate-200);
    background: #fff;
    color: var(--slate-700);
    text-decoration: none;
    padding: 0 .5rem;
}

.tickets-pagination [aria-current="page"] span {
    background: var(--blue-600);
    color: #fff;
    border-color: var(--blue-600);
}

/* ── Alerta de éxito ── */
.alert-success {
    background: var(--green-100);
    color: var(--green-700);
    border: 1px solid #bbf7d0;
    border-radius: 12px;
    padding: .8rem .95rem;
    font-size: 0.88rem;
}

/* ── Responsive ── */
@media (max-width: 900px) {
    .tickets-filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .tickets-kpi-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 600px) {
    .tickets-hero-head {
        flex-direction: column;
    }
    .tickets-filter-grid {
        grid-template-columns: 1fr;
    }
    .tickets-kpi-grid {
        grid-template-columns: 1fr;
    }
    .tickets-filter-actions {
        flex-direction: column;
    }
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
            /* ============================================================
   DASHBOARD — estilos para los 3 roles (admin, reporter, maintenance)
   Pega esto dentro del bloque <style> en layouts/app.blade.php,
   justo después de los estilos existentes.
   ============================================================ */

/* ── Contenedor base ── */
.role-dashboard {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    animation: dashboardFadeIn 280ms ease-out;
}

/* ── HERO ── */
.dash-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
}

.dash-hero::after {
    content: '';
    position: absolute;
    inset: auto -3rem -4rem auto;
    width: 10rem;
    height: 10rem;
    border-radius: 999px;
    opacity: 0.15;
    z-index: -1;
    filter: blur(2px);
}

/* Admin — verde esmeralda */
.dash-hero-admin {
    background: linear-gradient(135deg, rgba(236,253,245,.96), rgba(240,253,250,.92));
    border: 1px solid #a7f3d0;
}
.dash-hero-admin::after {
    background: radial-gradient(circle, #047857 0%, #10b981 55%, transparent 75%);
}

/* Reporter — cyan */
.dash-hero-reporter {
    background: linear-gradient(135deg, rgba(236,254,255,.96), rgba(239,246,255,.92));
    border: 1px solid #a5f3fc;
}
.dash-hero-reporter::after {
    background: radial-gradient(circle, #0891b2 0%, #22d3ee 55%, transparent 75%);
}

/* Maintenance — ámbar */
.dash-hero-maintenance {
    background: linear-gradient(135deg, rgba(255,251,235,.96), rgba(255,247,237,.92));
    border: 1px solid #fde68a;
}
.dash-hero-maintenance::after {
    background: radial-gradient(circle, #d97706 0%, #f59e0b 55%, transparent 75%);
}

.dash-hero-inner {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.25rem;
    flex-wrap: wrap;
}

.dash-hero-text {
    max-width: 600px;
}

.dash-overline {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .12em;
    margin-bottom: .35rem;
}
.dash-overline-admin       { color: #065f46; }
.dash-overline-reporter    { color: #164e63; }
.dash-overline-maintenance { color: #92400e; }

.dash-title {
    font-size: clamp(1.5rem, 2.5vw, 2rem);
    font-weight: 800;
    color: var(--slate-950);
    line-height: 1.15;
    margin: 0 0 .4rem;
}

.dash-subtitle {
    font-size: .9rem;
    color: var(--slate-700);
    line-height: 1.55;
    margin: 0 0 .5rem;
}

.dash-role-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--slate-500);
}

/* Stat box (solo admin) */
.dash-hero-stat {
    border-radius: 14px;
    padding: .85rem 1.1rem;
    text-align: right;
    flex-shrink: 0;
}
.dash-hero-stat-admin {
    background: rgba(236,253,245,.8);
    border: 1px solid #6ee7b7;
}
.dash-hero-stat-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #065f46;
    font-weight: 700;
}
.dash-hero-stat-value {
    font-size: 1.9rem;
    font-weight: 800;
    color: var(--slate-900);
    margin-top: .2rem;
}

/* Acciones del hero */
.dash-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    margin-top: 1rem;
}

.dash-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: .55rem 1rem;
    border-radius: 10px;
    font-size: .88rem;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

/* Admin buttons */
.dash-btn-admin-primary   { background: #065f46; color: #fff; }
.dash-btn-admin-primary:hover { background: #047857; }
.dash-btn-admin-secondary { background: #fff; color: #065f46; border: 1px solid #a7f3d0; }
.dash-btn-admin-secondary:hover { background: #ecfdf5; }

/* Reporter buttons */
.dash-btn-reporter-primary   { background: #0891b2; color: #fff; }
.dash-btn-reporter-primary:hover { background: #0e7490; }
.dash-btn-reporter-secondary { background: #fff; color: #164e63; border: 1px solid #a5f3fc; }
.dash-btn-reporter-secondary:hover { background: #ecfeff; }

/* Maintenance buttons */
.dash-btn-maintenance-primary   { background: #d97706; color: #fff; }
.dash-btn-maintenance-primary:hover { background: #b45309; }
.dash-btn-maintenance-secondary { background: #fff; color: #92400e; border: 1px solid #fde68a; }
.dash-btn-maintenance-secondary:hover { background: #fffbeb; }

/* ── KPI GRID ── */
.dash-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: .75rem;
}

.dash-kpi-grid-5 {
    grid-template-columns: repeat(2, 1fr);
}

.dash-kpi-card {
    border-radius: 14px;
    padding: .9rem 1rem;
    animation: dashboardRise 320ms ease-out;
}

.dash-kpi-card-admin       { background: rgba(236,253,245,.6); border: 1px solid #a7f3d0; }
.dash-kpi-card-reporter    { background: rgba(236,254,255,.6); border: 1px solid #a5f3fc; }
.dash-kpi-card-maintenance { background: rgba(255,251,235,.6); border: 1px solid #fde68a; }

.dash-kpi-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    margin-bottom: .35rem;
}
.dash-kpi-label-admin       { color: #065f46; }
.dash-kpi-label-reporter    { color: #164e63; }
.dash-kpi-label-maintenance { color: #92400e; }

.dash-kpi-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--slate-900);
    line-height: 1.1;
    margin-bottom: .25rem;
}

.dash-kpi-hint {
    font-size: .8rem;
    color: var(--slate-500);
    line-height: 1.4;
}

/* ── GRIDS DE SECCIONES ── */
.dash-grid-2 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.dash-grid-3 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.dash-grid-maintenance {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

/* ── CARDS GENÉRICAS ── */
.dash-card {
    display: flex;
    flex-direction: column;
    gap: .75rem;
}

.dash-card-header {
    padding-bottom: .75rem;
    border-bottom: 1px solid var(--slate-200);
}

.dash-card-title {
    font-size: .97rem;
    font-weight: 700;
    color: var(--slate-900);
    margin: 0 0 .18rem;
}

.dash-card-note {
    font-size: .82rem;
    color: var(--slate-500);
    margin: 0;
}

/* ── QR GRID ── */
.dash-qr-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
}

.dash-qr-item {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 12px;
    padding: .75rem .85rem;
}

.dash-qr-item-danger  { border-color: #fecaca; background: #fff5f5; }
.dash-qr-item-success { border-color: #bbf7d0; background: #f0fdf4; }

.dash-qr-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--slate-500);
    font-weight: 600;
    margin-bottom: .3rem;
}

.dash-qr-value {
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--slate-900);
}
.dash-qr-value-danger  { color: #b91c1c; }
.dash-qr-value-success { color: #166534; }

/* ── LISTA DE UBICACIONES ── */
.dash-location-list {
    display: flex;
    flex-direction: column;
    gap: .5rem;
}

.dash-location-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 12px;
    padding: .7rem .9rem;
}

.dash-location-name {
    font-size: .9rem;
    font-weight: 700;
    color: var(--slate-900);
    margin: 0 0 .15rem;
}

.dash-location-meta {
    font-size: .78rem;
    color: var(--slate-500);
    margin: 0;
}

.dash-location-counts {
    display: flex;
    flex-direction: column;
    gap: .25rem;
    align-items: flex-end;
}

.dash-count-chip {
    font-size: 11px;
    font-weight: 600;
    border-radius: 20px;
    padding: .18rem .55rem;
    white-space: nowrap;
}
.dash-count-open     { background: #dbeafe; color: #1d4ed8; }
.dash-count-progress { background: #fef3c7; color: #92400e; }

/* ── TABLA CARDS ── */
.dash-table-card {
    overflow: hidden;
}

.dash-table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: .9rem 1.1rem;
    background: var(--slate-100);
    border-bottom: 1px solid var(--slate-200);
}

.dash-link-action {
    font-size: .8rem;
    font-weight: 700;
    color: var(--blue-600);
    text-decoration: none;
    white-space: nowrap;
    padding: .3rem .65rem;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    background: #eff6ff;
}
.dash-link-action:hover { background: #dbeafe; }

/* Celdas de tabla */
.dash-cell-primary {
    font-weight: 600;
    font-size: .88rem;
    color: var(--slate-900);
}

.dash-cell-meta {
    font-size: .83rem;
    color: var(--slate-500);
}

.dash-empty-note {
    font-size: .85rem;
    color: var(--slate-500);
    padding: .5rem 0;
    text-align: center;
}

/* Links de acción en filas de tabla */
.dash-row-link {
    font-size: .8rem;
    font-weight: 700;
    text-decoration: none;
    padding: .28rem .65rem;
    border-radius: 8px;
    border: 1px solid transparent;
    white-space: nowrap;
}

.dash-row-link-admin       { color: #065f46; border-color: #a7f3d0; background: #ecfdf5; }
.dash-row-link-admin:hover { background: #d1fae5; }

.dash-row-link-reporter       { color: #0891b2; border-color: #a5f3fc; background: #ecfeff; }
.dash-row-link-reporter:hover { background: #cffafe; }

.dash-row-link-maintenance       { color: #d97706; border-color: #fde68a; background: #fffbeb; }
.dash-row-link-maintenance:hover { background: #fef3c7; }

/* Chip estado QR en tabla */
.dash-qr-status-chip {
    display: inline-flex;
    align-items: center;
    padding: .2rem .6rem;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: capitalize;
}
.dash-qr-status-pending    { background: #e2e8f0; color: #475569; }
.dash-qr-status-processing { background: #fef3c7; color: #92400e; }
.dash-qr-status-ready      { background: #dcfce7; color: #166534; }
.dash-qr-status-failed     { background: #fee2e2; color: #991b1b; }

/* ── ITEMS DE LISTA (alertas / cola) ── */
.dash-item-list {
    display: flex;
    flex-direction: column;
    gap: .5rem;
}

.dash-item-card {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 12px;
    padding: .75rem .9rem;
}

.dash-item-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .5rem;
}

.dash-item-meta {
    font-size: .78rem;
    color: var(--slate-500);
    margin: .18rem 0 0;
}

.dash-item-chips {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}

.dash-chip-neutral {
    display: inline-flex;
    align-items: center;
    padding: .2rem .6rem;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: var(--slate-100);
    color: var(--slate-600);
}

/* ── BREAKDOWN (estado/prioridad) ── */
.dash-breakdown-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .6rem;
}

.dash-breakdown-stack {
    display: flex;
    flex-direction: column;
    gap: .6rem;
}

.dash-breakdown-box {
    background: #fff;
    border: 1px solid var(--slate-200);
    border-radius: 12px;
    padding: .8rem .9rem;
}

.dash-breakdown-title {
    font-size: .82rem;
    font-weight: 700;
    color: var(--slate-800);
    margin: 0 0 .5rem;
}

.dash-breakdown-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: .35rem;
}

.dash-breakdown-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: .85rem;
}

.dash-breakdown-label { color: var(--slate-600); }
.dash-breakdown-value { color: var(--slate-900); font-weight: 700; }

/* ── RESPONSIVE ── */
@media (min-width: 640px) {
    .dash-kpi-grid       { grid-template-columns: repeat(4, 1fr); }
    .dash-kpi-grid-5     { grid-template-columns: repeat(3, 1fr); }
}

@media (min-width: 900px) {
    .dash-grid-2          { grid-template-columns: 1fr 1fr; }
    .dash-grid-3          { grid-template-columns: 1fr 2fr; }
    .dash-grid-maintenance{ grid-template-columns: 3fr 2fr; }
    .dash-kpi-grid-5      { grid-template-columns: repeat(5, 1fr); }
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
