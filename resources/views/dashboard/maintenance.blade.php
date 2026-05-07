@extends('layouts.app')

@section('title', 'Consola Maintenance')

@section('content')
    <div class="role-dashboard role-dashboard-maintenance">

        {{-- ── HERO ── --}}
        <section class="dash-hero dash-hero-maintenance panel panel-pad">
            <div class="dash-hero-inner">
                <div class="dash-hero-text">
                    <p class="dash-overline dash-overline-maintenance">{{ $hero['badge'] }}</p>
                    <h1 class="dash-title">{{ $hero['title'] }}</h1>
                    <p class="dash-subtitle">{{ $hero['subtitle'] }}</p>
                    <p class="dash-role-label">Perfil operativo: {{ $roleLabel }}</p>
                </div>
                <div class="dash-actions">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['href'] }}"
                           class="{{ $action['variant'] === 'primary' ? 'dash-btn dash-btn-maintenance-primary' : 'dash-btn dash-btn-maintenance-secondary' }}">
                            {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── KPIs ── --}}
        <section class="dash-kpi-grid dash-kpi-grid-5">
            @foreach ($kpis as $kpi)
                <article class="dash-kpi-card dash-kpi-card-maintenance">
                    <p class="dash-kpi-label dash-kpi-label-maintenance">{{ $kpi['label'] }}</p>
                    <p class="dash-kpi-value">{{ $kpi['value'] }}</p>
                    <p class="dash-kpi-hint">{{ $kpi['hint'] }}</p>
                </article>
            @endforeach
        </section>

        {{-- ── COLA + PRODUCTIVIDAD ── --}}
        <div class="dash-grid-maintenance">
            <section class="dash-card panel panel-pad dash-col-3">
                <header class="dash-card-header">
                    <h2 class="dash-card-title">Cola operativa priorizada</h2>
                    <p class="dash-card-note">Ordenada por criticidad y antigüedad para ejecución inmediata.</p>
                </header>
                <div class="dash-item-list">
                    @forelse ($workloadQueue as $ticket)
                        <article class="dash-item-card">
                            <div class="dash-item-row">
                                <div>
                                    <p class="dash-cell-primary">{{ $ticket->title }}</p>
                                    <p class="dash-item-meta">Reportado por {{ $ticket->reporter?->name ?? '—' }} · {{ $ticket->location?->name ?? '—' }}</p>
                                </div>
                                <a href="{{ route('tickets.show', $ticket) }}" class="dash-row-link dash-row-link-maintenance">Gestionar</a>
                            </div>
                            <div class="dash-item-chips">
                                <span class="tickets-chip tickets-chip-state-{{ $ticket->state }}">{{ $stateLabels[$ticket->state] ?? $ticket->state }}</span>
                                <span class="tickets-chip tickets-chip-priority-{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                                <span class="dash-chip-neutral">{{ $ticket->created_at?->diffForHumans() ?? '—' }}</span>
                            </div>
                        </article>
                    @empty
                        <p class="dash-empty-note">No tienes tickets activos en cola.</p>
                    @endforelse
                </div>
            </section>

            <section class="dash-card panel panel-pad dash-col-2">
                <header class="dash-card-header">
                    <h2 class="dash-card-title">Pulso de productividad</h2>
                    <p class="dash-card-note">Vista rápida de rendimiento y cierres recientes.</p>
                </header>
                <div class="dash-breakdown-stack">
                    <div class="dash-breakdown-box">
                        <h3 class="dash-breakdown-title">Estados activos</h3>
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
                        <h3 class="dash-breakdown-title">Carga por prioridad</h3>
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

        {{-- ── CIERRES RECIENTES ── --}}
        <section class="dash-table-card panel">
            <header class="dash-table-header">
                <div>
                    <h2 class="dash-card-title">Cierres recientes</h2>
                    <p class="dash-card-note">Últimos tickets que marcaste como resueltos.</p>
                </div>
                <a href="{{ route('tickets.index') }}" class="dash-link-action">Ver todos</a>
            </header>
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Ubicación</th>
                        <th>Prioridad</th>
                        <th>Resuelto</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentResolved as $ticket)
                        <tr>
                            <td><span class="dash-cell-primary">{{ $ticket->title }}</span></td>
                            <td><span class="dash-cell-meta">{{ $ticket->location?->name ?? '—' }}</span></td>
                            <td><span class="tickets-chip tickets-chip-priority-{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span></td>
                            <td><span class="dash-cell-meta">{{ $ticket->resolved_at?->diffForHumans() ?? '—' }}</span></td>
                            <td><a href="{{ route('tickets.show', $ticket) }}" class="dash-row-link dash-row-link-maintenance">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="dash-empty-note">Aún no registras tickets resueltos recientemente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

    </div>
@endsection