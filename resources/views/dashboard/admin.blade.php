@extends('layouts.app')

@section('title', 'Centro De Control Admin')

@section('content')
    <div class="role-dashboard role-dashboard-admin">

        {{-- ── HERO ── --}}
        <section class="dash-hero dash-hero-admin panel panel-pad">
            <div class="dash-hero-inner">
                <div class="dash-hero-text">
                    <p class="dash-overline dash-overline-admin">{{ $hero['badge'] }}</p>
                    <h1 class="dash-title">{{ $hero['title'] }}</h1>
                    <p class="dash-subtitle">{{ $hero['subtitle'] }}</p>
                    <p class="dash-role-label">Perfil operativo: {{ $roleLabel }}</p>
                </div>

                <div class="dash-hero-stat dash-hero-stat-admin">
                    <p class="dash-hero-stat-label">Tasa resolución 7 días</p>
                    <p class="dash-hero-stat-value">{{ $hero['resolutionRate7Days'] }}</p>
                </div>
            </div>

            <div class="dash-actions">
                @foreach ($quickActions as $action)
                    <a href="{{ $action['href'] }}"
                       class="{{ $action['variant'] === 'primary' ? 'dash-btn dash-btn-admin-primary' : 'dash-btn dash-btn-admin-secondary' }}">
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </section>

        {{-- ── KPIs ── --}}
        <section class="dash-kpi-grid">
            @foreach ($kpis as $kpi)
                <article class="dash-kpi-card dash-kpi-card-admin">
                    <p class="dash-kpi-label dash-kpi-label-admin">{{ $kpi['label'] }}</p>
                    <p class="dash-kpi-value">{{ $kpi['value'] }}</p>
                    <p class="dash-kpi-hint">{{ $kpi['hint'] }}</p>
                </article>
            @endforeach
        </section>

        {{-- ── QR + UBICACIONES ── --}}
        <div class="dash-grid-3">
            <section class="dash-card panel panel-pad">
                <header class="dash-card-header">
                    <h2 class="dash-card-title">Salud QR por estado</h2>
                    <p class="dash-card-note">Semáforo de generación y estabilidad QR institucional.</p>
                </header>
                <div class="dash-qr-grid">
                    <article class="dash-qr-item">
                        <p class="dash-qr-label">Pendiente</p>
                        <p class="dash-qr-value">{{ $qrStatusSummary['pending'] ?? 0 }}</p>
                    </article>
                    <article class="dash-qr-item">
                        <p class="dash-qr-label">Procesando</p>
                        <p class="dash-qr-value">{{ $qrStatusSummary['processing'] ?? 0 }}</p>
                    </article>
                    <article class="dash-qr-item dash-qr-item-danger">
                        <p class="dash-qr-label">Fallido</p>
                        <p class="dash-qr-value dash-qr-value-danger">{{ $qrStatusSummary['failed'] ?? 0 }}</p>
                    </article>
                    <article class="dash-qr-item dash-qr-item-success">
                        <p class="dash-qr-label">Listo</p>
                        <p class="dash-qr-value dash-qr-value-success">{{ $qrStatusSummary['ready'] ?? 0 }}</p>
                    </article>
                </div>
            </section>

            <section class="dash-card panel panel-pad dash-col-2">
                <header class="dash-card-header">
                    <h2 class="dash-card-title">Top ubicaciones con carga operativa</h2>
                    <p class="dash-card-note">Espacios con mayor volumen de incidencias abiertas/en progreso.</p>
                </header>
                <div class="dash-location-list">
                    @forelse ($topLocationsByOpen as $location)
                        <article class="dash-location-item">
                            <div>
                                <p class="dash-location-name">{{ $location->name }}</p>
                                <p class="dash-location-meta">{{ $location->room_code }} · {{ $location->building }}</p>
                            </div>
                            <div class="dash-location-counts">
                                <span class="dash-count-chip dash-count-open">{{ $location->open_tickets_count }} abiertos</span>
                                <span class="dash-count-chip dash-count-progress">{{ $location->in_progress_tickets_count }} en progreso</span>
                            </div>
                        </article>
                    @empty
                        <p class="dash-empty-note">No hay ubicaciones con carga activa.</p>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- ── TABLAS ── --}}
        <div class="dash-grid-2">
            <section class="dash-table-card panel">
                <header class="dash-table-header">
                    <div>
                        <h2 class="dash-card-title">Radar de incidencias QR</h2>
                        <p class="dash-card-note">Ubicaciones con QR pendiente, procesando o fallido.</p>
                    </div>
                </header>
                <table>
                    <thead>
                        <tr>
                            <th>Ubicación</th>
                            <th>Aula</th>
                            <th>Estado QR</th>
                            <th>Tickets</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($qrIssues as $location)
                            <tr>
                                <td><span class="dash-cell-primary">{{ $location->name }}</span></td>
                                <td><span class="dash-cell-meta">{{ $location->room_code }}</span></td>
                                <td>
                                    <span class="dash-qr-status-chip dash-qr-status-{{ $location->qr_generation_status ?? 'pending' }}">
                                        {{ $location->qr_generation_status ?? 'pending' }}
                                    </span>
                                </td>
                                <td><span class="dash-cell-meta">{{ $location->tickets_count }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="dash-empty-note">No hay incidencias QR activas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="dash-table-card panel">
                <header class="dash-table-header">
                    <div>
                        <h2 class="dash-card-title">Actividad global reciente</h2>
                        <p class="dash-card-note">Últimos movimientos sobre tickets en toda la plataforma.</p>
                    </div>
                    <a href="{{ route('tickets.index') }}" class="dash-link-action">Ver todos</a>
                </header>
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Ubicación</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentTickets as $ticket)
                            <tr>
                                <td><span class="dash-cell-primary">{{ $ticket->title }}</span></td>
                                <td>
                                    <span class="tickets-chip tickets-chip-state-{{ $ticket->state }}">
                                        {{ $stateLabels[$ticket->state] ?? $ticket->state }}
                                    </span>
                                </td>
                                <td>
                                    <span class="tickets-chip tickets-chip-priority-{{ $ticket->priority }}">
                                        {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                                    </span>
                                </td>
                                <td><span class="dash-cell-meta">{{ $ticket->location?->name ?? '—' }}</span></td>
                                <td><a href="{{ route('tickets.show', $ticket) }}" class="dash-row-link dash-row-link-admin">Ver</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="dash-empty-note">No hay actividad reciente.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

    </div>
@endsection