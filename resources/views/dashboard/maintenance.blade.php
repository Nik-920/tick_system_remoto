@extends('layouts.app')

@section('title', 'Consola Mantenimiento')

@section('content')
<div class="role-dashboard role-dashboard-maintenance">

    {{-- ══════════════════════ HERO ══════════════════════ --}}
    <section class="role-hero role-hero-maintenance rd-hero" aria-labelledby="maint-hero-title">
        <div class="rd-hero-inner">
            <div class="rd-hero-copy">
                <span class="rd-badge rd-badge--amber" role="status">
                    <span class="rd-badge-dot rd-badge-dot--amber" aria-hidden="true"></span>
                    {{ $hero['badge'] }}
                </span>
                <h1 id="maint-hero-title" class="rd-hero-title">{{ $hero['title'] }}</h1>
                <p class="rd-hero-sub">{{ $hero['subtitle'] }}</p>
                <p class="rd-role-label">Perfil operativo: <strong>{{ $roleLabel }}</strong></p>
            </div>

            <div class="rd-hero-stat-card rd-hero-stat-card--amber">
                <p class="rd-hero-stat-label">Tiempo medio resolución</p>
                <p class="rd-hero-stat-value">{{ $avgResolutionHoursLast30Days }}h</p>
                <p class="rd-hero-stat-note">últimos 30 días</p>
            </div>
        </div>

        <div class="rd-hero-actions" role="navigation" aria-label="Acciones rápidas">
            @foreach ($quickActions as $action)
                <a
                    href="{{ $action['href'] }}"
                    class="{{ $action['variant'] === 'primary' ? 'rd-btn rd-btn--amber' : 'rd-btn rd-btn--ghost-amber' }}"
                >
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    </section>

    {{-- ══════════════════════ KPI GRID ══════════════════════ --}}
    <section aria-labelledby="maint-kpi-heading">
        <h2 id="maint-kpi-heading" class="sr-only">Indicadores clave</h2>
        <dl class="rd-kpi-grid rd-kpi-grid--5">
            @foreach ($kpis as $i => $kpi)
                <article
                    class="rd-kpi-card rd-kpi-card--amber role-kpi-card"
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

    {{-- ══════════════════════ ROW: WORK QUEUE + PRODUCTIVITY ══════════════════════ --}}
    <div class="rd-row rd-row--3-2">

        {{-- Work Queue --}}
        <section class="panel panel-pad rd-section-border--amber" aria-labelledby="maint-queue-title">
            <header class="rd-section-header rd-section-header--between">
                <div>
                    <h2 id="maint-queue-title" class="rd-section-title">Cola operativa priorizada</h2>
                    <p class="rd-section-sub">Ordenada por criticidad y antigüedad para ejecución inmediata.</p>
                </div>
                @if(count($workloadQueue) > 0)
                    <span class="rd-queue-count" aria-label="{{ count($workloadQueue) }} tickets en cola">{{ count($workloadQueue) }}</span>
                @endif
            </header>

            <ul class="rd-queue-list" role="list" aria-label="Cola de tickets asignados">
                @forelse ($workloadQueue as $i => $ticket)
                    <li class="rd-queue-item" style="animation-delay: {{ $i * 35 }}ms">
                        <div class="rd-queue-item-top">
                            <div class="rd-queue-item-info">
                                <p class="rd-queue-item-title">{{ $ticket->title }}</p>
                                <p class="rd-queue-item-meta">
                                    <x-lucide-clock width="11" height="11" stroke-width="2.5" aria-hidden="true" />
                                    {{ $ticket->created_at?->diffForHumans() ?? 'N/A' }}
                                    @if($ticket->location)
                                        &nbsp;·&nbsp;
                                        <x-lucide-map-pin width="11" height="11" stroke-width="2.5" aria-hidden="true" />
                                        {{ $ticket->location->name }}
                                    @endif
                                </p>
                            </div>
                            <a href="{{ route('tickets.show', $ticket) }}" class="rd-queue-action rd-queue-action--amber">Gestionar</a>
                        </div>
                        <div class="rd-queue-tags">
                            <span class="rd-state-pill rd-state-pill--{{ $ticket->state }}">{{ $stateLabels[$ticket->state] ?? $ticket->state }}</span>
                            <span class="rd-priority-pill rd-priority-pill--{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                            @if($ticket->reporter)
                                <span class="rd-tag-plain">Reportó: {{ $ticket->reporter->name }}</span>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="rd-empty-state rd-empty-state--success">
                        <x-lucide-check-circle width="20" height="20" stroke-width="2" aria-hidden="true" />
                        No tienes tickets activos en cola. ¡Bien hecho!
                    </li>
                @endforelse
            </ul>
        </section>

        {{-- Productivity Pulse --}}
        <section class="panel panel-pad rd-section-border--amber" aria-labelledby="maint-productivity-title">
            <header class="rd-section-header">
                <h2 id="maint-productivity-title" class="rd-section-title">Pulso de productividad</h2>
                <p class="rd-section-sub">Vista rápida de rendimiento y cierres recientes.</p>
            </header>

            <div class="rd-breakdown-stack">
                <div class="rd-breakdown-card" aria-labelledby="maint-states-heading">
                    <h3 id="maint-states-heading" class="rd-breakdown-title">Estados activos</h3>
                    @if (count($stateBreakdown) > 0)
                        <ul class="rd-breakdown-list" role="list">
                            @foreach ($stateBreakdown as $state => $total)
                                <li class="rd-breakdown-row">
                                    <span class="rd-state-dot rd-state-dot--{{ $state }}" aria-hidden="true"></span>
                                    <span class="rd-breakdown-label">{{ $stateLabels[$state] ?? $state }}</span>
                                    <div class="rd-breakdown-bar-wrap" aria-hidden="true">
                                        <div class="rd-breakdown-bar rd-breakdown-bar--amber" style="width: {{ min(100, ($total / max(array_values($stateBreakdown))) * 100) }}%"></div>
                                    </div>
                                    <strong class="rd-breakdown-count">{{ $total }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="rd-empty-inline">Sin datos disponibles.</p>
                    @endif
                </div>

                <div class="rd-breakdown-card" aria-labelledby="maint-priority-heading">
                    <h3 id="maint-priority-heading" class="rd-breakdown-title">Carga por prioridad</h3>
                    @if (count($priorityBreakdown) > 0)
                        <ul class="rd-breakdown-list" role="list">
                            @foreach ($priorityBreakdown as $priority => $total)
                                <li class="rd-breakdown-row">
                                    <span class="rd-priority-dot rd-priority-dot--{{ $priority }}" aria-hidden="true"></span>
                                    <span class="rd-breakdown-label">{{ $priorityLabels[$priority] ?? $priority }}</span>
                                    <div class="rd-breakdown-bar-wrap" aria-hidden="true">
                                        <div class="rd-breakdown-bar rd-breakdown-bar--amber" style="width: {{ min(100, ($total / max(array_values($priorityBreakdown))) * 100) }}%"></div>
                                    </div>
                                    <strong class="rd-breakdown-count">{{ $total }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="rd-empty-inline">Sin datos disponibles.</p>
                    @endif
                </div>
            </div>
        </section>
    </div>

    {{-- ══════════════════════ RECENT RESOLVED TABLE ══════════════════════ --}}
    <section class="panel rd-section-border--amber rd-table-section" aria-labelledby="maint-resolved-title">
        <header class="rd-table-header">
            <div>
                <h2 id="maint-resolved-title" class="rd-section-title">Cierres recientes</h2>
                <p class="rd-section-sub">Últimos tickets que marcaste como resueltos.</p>
            </div>
            <a href="{{ route('tickets.index') }}" class="rd-table-link">Ver historial</a>
        </header>
        <div class="rd-table-wrap" role="region" aria-label="Tabla de tickets resueltos" tabindex="0">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Título</th>
                        <th scope="col">Ubicación</th>
                        <th scope="col">Prioridad</th>
                        <th scope="col">Resuelto</th>
                        <th scope="col" class="text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentResolved as $ticket)
                        <tr>
                            <td class="rd-table-title-cell">{{ $ticket->title }}</td>
                            <td class="text-slate-600">{{ $ticket->location?->name ?? 'N/A' }}</td>
                            <td>
                                <span class="rd-priority-pill rd-priority-pill--{{ $ticket->priority }}">
                                    {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                                </span>
                            </td>
                            <td class="rd-table-time">{{ $ticket->resolved_at?->diffForHumans() ?? 'N/A' }}</td>
                            <td class="text-right">
                                <a href="{{ route('tickets.show', $ticket) }}" class="rd-table-action rd-table-action--amber">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="rd-table-empty">Aún no registras tickets resueltos recientemente.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>
@endsection