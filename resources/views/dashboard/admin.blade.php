@extends('layouts.app')

@section('title', 'Centro de Control — Admin')

@section('content')
<div class="role-dashboard role-dashboard-admin">

    {{-- ══════════════════════ HERO ══════════════════════ --}}
    <section class="role-hero role-hero-admin rd-hero" aria-labelledby="admin-hero-title">
        <div class="rd-hero-inner">
            <div class="rd-hero-copy">
                <span class="rd-badge rd-badge--emerald" role="status">
                    <span class="rd-badge-dot rd-badge-dot--emerald" aria-hidden="true"></span>
                    {{ $hero['badge'] }}
                </span>
                <h1 id="admin-hero-title" class="rd-hero-title">{{ $hero['title'] }}</h1>
                <p class="rd-hero-sub">{{ $hero['subtitle'] }}</p>
                <p class="rd-role-label">Perfil operativo: <strong>{{ $roleLabel }}</strong></p>
            </div>

            <div class="rd-hero-stat-card rd-hero-stat-card--emerald">
                <p class="rd-hero-stat-label">Tasa resolución 7 días</p>
                <p class="rd-hero-stat-value">{{ $hero['resolutionRate7Days'] }}</p>
            </div>
        </div>

        <div class="rd-hero-actions" role="navigation" aria-label="Acciones rápidas">
            @foreach ($quickActions as $action)
                <a
                    href="{{ $action['href'] }}"
                    class="{{ $action['variant'] === 'primary' ? 'rd-btn rd-btn--emerald' : 'rd-btn rd-btn--ghost-emerald' }}"
                >
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    </section>

    {{-- ══════════════════════ KPI GRID ══════════════════════ --}}
    <section aria-labelledby="admin-kpi-heading">
        <h2 id="admin-kpi-heading" class="sr-only">Indicadores clave</h2>
        <dl class="rd-kpi-grid">
            @foreach ($kpis as $i => $kpi)
                <article
                    class="rd-kpi-card rd-kpi-card--emerald role-kpi-card"
                    style="animation-delay: {{ $i * 40 }}ms"
                    aria-label="{{ $kpi['label'] }}: {{ $kpi['value'] }}"
                >
                    <dt class="rd-kpi-label">{{ $kpi['label'] }}</dt>
                    <dd class="rd-kpi-value">{{ $kpi['value'] }}</dd>
                    <p class="rd-kpi-hint">{{ $kpi['hint'] }}</p>
                </article>
            @endforeach
        </dl>
    </section>

    {{-- ══════════════════════ ROW: QR HEALTH + TOP LOCATIONS ══════════════════════ --}}
    <div class="rd-row rd-row--1-2">

        {{-- QR Status Summary --}}
        <section class="panel panel-pad rd-section-border--emerald" aria-labelledby="admin-qr-title">
            <header class="rd-section-header">
                <h2 id="admin-qr-title" class="rd-section-title">Salud QR por estado</h2>
                <p class="rd-section-sub">Semáforo de generación y estabilidad de códigos QR institucionales.</p>
            </header>

            <dl class="rd-qr-grid">
                <div class="rd-qr-cell">
                    <dt class="rd-qr-label rd-qr-label--slate">Pendiente</dt>
                    <dd class="rd-qr-val">{{ $qrStatusSummary['pending'] ?? 0 }}</dd>
                </div>
                <div class="rd-qr-cell">
                    <dt class="rd-qr-label rd-qr-label--blue">Procesando</dt>
                    <dd class="rd-qr-val">{{ $qrStatusSummary['processing'] ?? 0 }}</dd>
                </div>
                <div class="rd-qr-cell rd-qr-cell--alert">
                    <dt class="rd-qr-label rd-qr-label--red">Fallido</dt>
                    <dd class="rd-qr-val rd-qr-val--red">{{ $qrStatusSummary['failed'] ?? 0 }}</dd>
                </div>
                <div class="rd-qr-cell rd-qr-cell--ok">
                    <dt class="rd-qr-label rd-qr-label--emerald">Listo</dt>
                    <dd class="rd-qr-val rd-qr-val--emerald">{{ $qrStatusSummary['ready'] ?? 0 }}</dd>
                </div>
            </dl>

            {{-- State breakdown mini --}}
            @if(count($stateBreakdown) > 0)
            <div class="rd-mini-breakdown" aria-label="Distribución por estado">
                <p class="rd-mini-breakdown-title">Distribución global de tickets</p>
                <ul class="rd-mini-breakdown-list" role="list">
                    @foreach ($stateBreakdown as $state => $total)
                        <li class="rd-mini-breakdown-item">
                            <span class="rd-state-dot rd-state-dot--{{ $state }}" aria-hidden="true"></span>
                            <span class="rd-mini-breakdown-state">{{ $stateLabels[$state] ?? $state }}</span>
                            <strong class="rd-mini-breakdown-count">{{ $total }}</strong>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </section>

        {{-- Top Locations --}}
        <section class="panel panel-pad rd-section-border--emerald" aria-labelledby="admin-locations-title">
            <header class="rd-section-header">
                <h2 id="admin-locations-title" class="rd-section-title">Top ubicaciones con carga operativa</h2>
                <p class="rd-section-sub">Espacios con mayor volumen de incidencias abiertas o en progreso.</p>
            </header>

            <ul class="rd-location-list" role="list">
                @forelse ($topLocationsByOpen as $i => $location)
                    <li class="rd-location-item" style="animation-delay: {{ $i * 50 }}ms">
                        <div class="rd-location-rank" aria-hidden="true">{{ $i + 1 }}</div>
                        <div class="rd-location-info">
                            <p class="rd-location-name">{{ $location->name }}</p>
                            <p class="rd-location-meta">{{ $location->room_code }} · {{ $location->building }}</p>
                        </div>
                        <div class="rd-location-counts" aria-label="Abiertos: {{ $location->open_tickets_count }}, En progreso: {{ $location->in_progress_tickets_count }}">
                            <span class="rd-count-chip rd-count-chip--open">{{ $location->open_tickets_count }} abiertos</span>
                            <span class="rd-count-chip rd-count-chip--progress">{{ $location->in_progress_tickets_count }} progreso</span>
                        </div>
                    </li>
                @empty
                    <li class="rd-empty-state">Sin ubicaciones con carga activa en este momento.</li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- ══════════════════════ ROW: QR ISSUES TABLE + RECENT TICKETS TABLE ══════════════════════ --}}
    <div class="rd-row rd-row--equal">

        {{-- QR Issues --}}
        <section class="panel rd-section-border--emerald rd-table-section" aria-labelledby="admin-qrissues-title">
            <header class="rd-table-header">
                <div>
                    <h2 id="admin-qrissues-title" class="rd-section-title">Radar de incidencias QR</h2>
                    <p class="rd-section-sub">Ubicaciones con QR pendiente, procesando o fallido.</p>
                </div>
            </header>
            <div class="rd-table-wrap" role="region" aria-label="Tabla de incidencias QR" tabindex="0">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Ubicación</th>
                            <th scope="col">Aula</th>
                            <th scope="col">Estado QR</th>
                            <th scope="col" class="text-right">Tickets</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($qrIssues as $location)
                            <tr>
                                <td class="font-semibold text-slate-900">{{ $location->name }}</td>
                                <td class="text-slate-600">{{ $location->room_code }}</td>
                                <td>
                                    <span class="rd-qr-status-pill rd-qr-status-pill--{{ $location->qr_generation_status ?? 'pending' }}">
                                        {{ ucfirst($location->qr_generation_status ?? 'pending') }}
                                    </span>
                                </td>
                                <td class="text-right font-semibold text-slate-900">{{ $location->tickets_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="rd-table-empty">No hay incidencias QR activas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Recent Global Activity --}}
        <section class="panel rd-section-border--emerald rd-table-section" aria-labelledby="admin-recent-title">
            <header class="rd-table-header">
                <div>
                    <h2 id="admin-recent-title" class="rd-section-title">Actividad global reciente</h2>
                    <p class="rd-section-sub">Últimos movimientos sobre tickets en toda la plataforma.</p>
                </div>
                <a href="{{ route('tickets.index') }}" class="rd-table-link">Ver todos</a>
            </header>
            <div class="rd-table-wrap" role="region" aria-label="Tabla de actividad reciente" tabindex="0">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Título</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Prioridad</th>
                            <th scope="col">Ubicación</th>
                            <th scope="col" class="text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentTickets as $ticket)
                            <tr>
                                <td class="rd-table-title-cell">{{ $ticket->title }}</td>
                                <td>
                                    <span class="rd-state-pill rd-state-pill--{{ $ticket->state }}">
                                        {{ $stateLabels[$ticket->state] ?? $ticket->state }}
                                    </span>
                                </td>
                                <td>
                                    <span class="rd-priority-pill rd-priority-pill--{{ $ticket->priority }}">
                                        {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                                    </span>
                                </td>
                                <td class="text-slate-600">{{ $ticket->location?->name ?? 'N/A' }}</td>
                                <td class="text-right">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="rd-table-action rd-table-action--emerald">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="rd-table-empty">No hay actividad reciente para mostrar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

</div>
@endsection