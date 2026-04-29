@extends('layouts.app')

@section('title', 'Centro Personal Reporter')

@section('content')
<div class="role-dashboard role-dashboard-reporter">

    {{-- ══════════════════════ HERO ══════════════════════ --}}
    <section class="role-hero role-hero-reporter rd-hero" aria-labelledby="reporter-hero-title">
        <div class="rd-hero-inner">
            <div class="rd-hero-copy">
                <span class="rd-badge rd-badge--cyan" role="status">
                    <span class="rd-badge-dot rd-badge-dot--cyan" aria-hidden="true"></span>
                    {{ $hero['badge'] }}
                </span>
                <h1 id="reporter-hero-title" class="rd-hero-title">{{ $hero['title'] }}</h1>
                <p class="rd-hero-sub">{{ $hero['subtitle'] }}</p>
                <p class="rd-role-label">Perfil operativo: <strong>{{ $roleLabel }}</strong></p>
            </div>

            {{-- Quick ticket tip --}}
            <div class="rd-hero-tip">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h.01M17 7h.01M7 17h.01M17 17h.01M12 12h.01"/></svg>
                <p>Escanea el QR de la ubicación para reportar una incidencia en segundos.</p>
            </div>
        </div>

        <div class="rd-hero-actions" role="navigation" aria-label="Acciones rápidas">
            @foreach ($quickActions as $action)
                <a
                    href="{{ $action['href'] }}"
                    class="{{ $action['variant'] === 'primary' ? 'rd-btn rd-btn--cyan' : 'rd-btn rd-btn--ghost-cyan' }}"
                >
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    </section>

    {{-- ══════════════════════ KPI GRID ══════════════════════ --}}
    <section aria-labelledby="reporter-kpi-heading">
        <h2 id="reporter-kpi-heading" class="sr-only">Indicadores clave</h2>
        <dl class="rd-kpi-grid">
            @foreach ($kpis as $i => $kpi)
                <article
                    class="rd-kpi-card rd-kpi-card--cyan role-kpi-card"
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

    {{-- ══════════════════════ ROW: ALERTS + BREAKDOWN ══════════════════════ --}}
    <div class="rd-row rd-row--equal">

        {{-- Attention Items --}}
        <section class="panel panel-pad rd-section-border--cyan" aria-labelledby="reporter-alerts-title">
            <header class="rd-section-header rd-section-header--between">
                <div>
                    <h2 id="reporter-alerts-title" class="rd-section-title">Mis alertas inmediatas</h2>
                    <p class="rd-section-sub">Prioriza incidencias abiertas y sigue su asignación.</p>
                </div>
                @if(count($attentionItems) > 0)
                    <span class="rd-queue-count rd-queue-count--cyan" aria-label="{{ count($attentionItems) }} alertas activas">{{ count($attentionItems) }}</span>
                @endif
            </header>

            <ul class="rd-queue-list" role="list" aria-label="Alertas de tickets activos">
                @forelse ($attentionItems as $i => $ticket)
                    <li class="rd-queue-item" style="animation-delay: {{ $i * 35 }}ms">
                        <div class="rd-queue-item-top">
                            <div class="rd-queue-item-info">
                                <p class="rd-queue-item-title">{{ $ticket->title }}</p>
                                <p class="rd-queue-item-meta">
                                    @if($ticket->location)
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        {{ $ticket->location->name }}
                                    @endif
                                    @if($ticket->category)
                                        &nbsp;·&nbsp;{{ $ticket->category->name }}
                                    @endif
                                </p>
                            </div>
                            <a href="{{ route('tickets.show', $ticket) }}" class="rd-queue-action rd-queue-action--cyan">Abrir</a>
                        </div>
                        <div class="rd-queue-tags">
                            <span class="rd-state-pill rd-state-pill--{{ $ticket->state }}">{{ $stateLabels[$ticket->state] ?? $ticket->state }}</span>
                            <span class="rd-priority-pill rd-priority-pill--{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                            <span class="rd-tag-plain">
                                Asignado: {{ $ticket->assignee?->name ?? 'Pendiente' }}
                            </span>
                        </div>
                    </li>
                @empty
                    <li class="rd-empty-state rd-empty-state--success">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        No hay alertas activas por ahora.
                    </li>
                @endforelse
            </ul>
        </section>

        {{-- Distribution --}}
        <section class="panel panel-pad rd-section-border--cyan" aria-labelledby="reporter-dist-title">
            <header class="rd-section-header">
                <h2 id="reporter-dist-title" class="rd-section-title">Distribución de mis reportes</h2>
                <p class="rd-section-sub">Lectura rápida de volumen por estado y prioridad.</p>
            </header>

            <div class="rd-breakdown-stack">
                <div class="rd-breakdown-card" aria-labelledby="reporter-states-heading">
                    <h3 id="reporter-states-heading" class="rd-breakdown-title">Por estado</h3>
                    @if (count($stateBreakdown) > 0)
                        <ul class="rd-breakdown-list" role="list">
                            @foreach ($stateBreakdown as $state => $total)
                                <li class="rd-breakdown-row">
                                    <span class="rd-state-dot rd-state-dot--{{ $state }}" aria-hidden="true"></span>
                                    <span class="rd-breakdown-label">{{ $stateLabels[$state] ?? $state }}</span>
                                    <div class="rd-breakdown-bar-wrap" aria-hidden="true">
                                        <div class="rd-breakdown-bar rd-breakdown-bar--cyan" style="width: {{ min(100, ($total / max(array_values($stateBreakdown))) * 100) }}%"></div>
                                    </div>
                                    <strong class="rd-breakdown-count">{{ $total }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="rd-empty-inline">Sin datos disponibles.</p>
                    @endif
                </div>

                <div class="rd-breakdown-card" aria-labelledby="reporter-priority-heading">
                    <h3 id="reporter-priority-heading" class="rd-breakdown-title">Por prioridad</h3>
                    @if (count($priorityBreakdown) > 0)
                        <ul class="rd-breakdown-list" role="list">
                            @foreach ($priorityBreakdown as $priority => $total)
                                <li class="rd-breakdown-row">
                                    <span class="rd-priority-dot rd-priority-dot--{{ $priority }}" aria-hidden="true"></span>
                                    <span class="rd-breakdown-label">{{ $priorityLabels[$priority] ?? $priority }}</span>
                                    <div class="rd-breakdown-bar-wrap" aria-hidden="true">
                                        <div class="rd-breakdown-bar rd-breakdown-bar--cyan" style="width: {{ min(100, ($total / max(array_values($priorityBreakdown))) * 100) }}%"></div>
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

    {{-- ══════════════════════ RECENT TIMELINE TABLE ══════════════════════ --}}
    <section class="panel rd-section-border--cyan rd-table-section" aria-labelledby="reporter-timeline-title">
        <header class="rd-table-header">
            <div>
                <h2 id="reporter-timeline-title" class="rd-section-title">Actividad reciente vinculada</h2>
                <p class="rd-section-sub">Tickets donde participas como reportero o responsable.</p>
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
                        <th scope="col">Actualizado</th>
                        <th scope="col" class="text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($myRecentTimeline as $ticket)
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
                            <td class="rd-table-time">{{ $ticket->updated_at?->diffForHumans() ?? 'N/A' }}</td>
                            <td class="text-right">
                                <a href="{{ route('tickets.show', $ticket) }}" class="rd-table-action rd-table-action--cyan">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="rd-table-empty">No hay tickets relacionados para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>
@endsection