@extends('layouts.app')

@section('title', 'Centro Personal Reporter')

@section('content')
    <div class="role-dashboard role-dashboard-reporter">

        {{-- ── HERO ── --}}
        <section class="dash-hero dash-hero-reporter panel panel-pad">
            <div class="dash-hero-inner">
                <div class="dash-hero-text">
                    <p class="dash-overline dash-overline-reporter">{{ $hero['badge'] }}</p>
                    <h1 class="dash-title">{{ $hero['title'] }}</h1>
                    <p class="dash-subtitle">{{ $hero['subtitle'] }}</p>
                    <p class="dash-role-label">Perfil operativo: {{ $roleLabel }}</p>
                </div>
                <div class="dash-actions">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['href'] }}"
                           class="{{ $action['variant'] === 'primary' ? 'dash-btn dash-btn-reporter-primary' : 'dash-btn dash-btn-reporter-secondary' }}">
                            {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── KPIs ── --}}
        <section class="dash-kpi-grid">
            @foreach ($kpis as $kpi)
                <article class="dash-kpi-card dash-kpi-card-reporter">
                    <p class="dash-kpi-label dash-kpi-label-reporter">{{ $kpi['label'] }}</p>
                    <p class="dash-kpi-value">{{ $kpi['value'] }}</p>
                    <p class="dash-kpi-hint">{{ $kpi['hint'] }}</p>
                </article>
            @endforeach
        </section>

        {{-- ── ALERTAS + DISTRIBUCIÓN ── --}}
        <div class="dash-grid-2">
            <section class="dash-card panel panel-pad">
                <header class="dash-card-header">
                    <h2 class="dash-card-title">Mis alertas inmediatas</h2>
                    <p class="dash-card-note">Prioriza incidentes abiertos y sigue su asignación.</p>
                </header>
                <div class="dash-item-list">
                    @forelse ($attentionItems as $ticket)
                        <article class="dash-item-card">
                            <div class="dash-item-row">
                                <div>
                                    <p class="dash-cell-primary">{{ $ticket->title }}</p>
                                    <p class="dash-item-meta">{{ $ticket->location?->name ?? '—' }} · {{ $ticket->category?->name ?? 'Sin categoría' }}</p>
                                </div>
                                <a href="{{ route('tickets.show', $ticket) }}" class="dash-row-link dash-row-link-reporter">Abrir</a>
                            </div>
                            <div class="dash-item-chips">
                                <span class="tickets-chip tickets-chip-state-{{ $ticket->state }}">{{ $stateLabels[$ticket->state] ?? $ticket->state }}</span>
                                <span class="tickets-chip tickets-chip-priority-{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                                <span class="dash-chip-neutral">{{ $ticket->assignee?->name ?? 'Sin asignar' }}</span>
                            </div>
                        </article>
                    @empty
                        <p class="dash-empty-note">No hay alertas activas por ahora.</p>
                    @endforelse
                </div>
            </section>

            <section class="dash-card panel panel-pad">
                <header class="dash-card-header">
                    <h2 class="dash-card-title">Distribución de mis reportes</h2>
                    <p class="dash-card-note">Lectura rápida de volumen por estado y prioridad.</p>
                </header>
                <div class="dash-breakdown-grid">
                    <div class="dash-breakdown-box">
                        <h3 class="dash-breakdown-title">Por estado</h3>
                        @if (count($stateBreakdown) > 0)
                            <ul class="dash-breakdown-list">
                                @foreach ($stateBreakdown as $state => $total)
                                    <li class="dash-breakdown-row">
                                        <span class="dash-breakdown-label">{{ $stateLabels[$state] ?? $state }}</span>
                                        <strong class="dash-breakdown-value">{{ $total }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="dash-empty-note">Sin datos.</p>
                        @endif
                    </div>
                    <div class="dash-breakdown-box">
                        <h3 class="dash-breakdown-title">Por prioridad</h3>
                        @if (count($priorityBreakdown) > 0)
                            <ul class="dash-breakdown-list">
                                @foreach ($priorityBreakdown as $priority => $total)
                                    <li class="dash-breakdown-row">
                                        <span class="dash-breakdown-label">{{ $priorityLabels[$priority] ?? $priority }}</span>
                                        <strong class="dash-breakdown-value">{{ $total }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="dash-empty-note">Sin datos.</p>
                        @endif
                    </div>
                </div>
            </section>
        </div>

        {{-- ── TABLA ACTIVIDAD ── --}}
        <section class="dash-table-card panel">
            <header class="dash-table-header">
                <div>
                    <h2 class="dash-card-title">Actividad reciente vinculada</h2>
                    <p class="dash-card-note">Tickets donde participas como reportero o responsable.</p>
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
                        <th>Actualizado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($myRecentTimeline as $ticket)
                        <tr>
                            <td><span class="dash-cell-primary">{{ $ticket->title }}</span></td>
                            <td><span class="tickets-chip tickets-chip-state-{{ $ticket->state }}">{{ $stateLabels[$ticket->state] ?? $ticket->state }}</span></td>
                            <td><span class="tickets-chip tickets-chip-priority-{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span></td>
                            <td><span class="dash-cell-meta">{{ $ticket->location?->name ?? '—' }}</span></td>
                            <td><span class="dash-cell-meta">{{ $ticket->updated_at?->diffForHumans() ?? '—' }}</span></td>
                            <td><a href="{{ route('tickets.show', $ticket) }}" class="dash-row-link dash-row-link-reporter">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="dash-empty-note">No hay tickets relacionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

    </div>
@endsection