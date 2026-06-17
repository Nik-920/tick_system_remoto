@extends('layouts.app')

@section('title', 'Centro Personal Reporter')

@section('content')
    <div class="role-dashboard role-dashboard-reporter space-y-6">

        {{-- ===== HERO ===== --}}
        <section class="role-hero role-hero-reporter panel panel-pad overflow-hidden">
            <div class="role-hero__header flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <p class="role-hero__eyebrow text-xs font-semibold uppercase tracking-[0.14em] text-cyan-700">{{ $hero['badge'] }}</p>
                    <h1 class="role-hero__title mt-2 text-3xl md:text-4xl font-black tracking-tight text-slate-900">{{ $hero['title'] }}</h1>
                    <p class="role-hero__subtitle mt-2 text-slate-700 text-sm md:text-base">{{ $hero['subtitle'] }}</p>
                    <p class="role-hero__meta mt-3 text-xs uppercase tracking-[0.12em] text-slate-500">Perfil operativo: {{ $roleLabel }}</p>
                </div>

                <div class="role-hero__metric-box rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-right flex flex-col items-end justify-center">
                    <div class="flex items-center gap-2 text-cyan-700 mb-1">
                        <x-lucide-qr-code width="18" height="18" stroke-width="2" />
                        <p class="role-hero__metric-label text-xs uppercase tracking-[0.1em] font-semibold">Reporte rápido</p>
                    </div>
                    <p class="role-hero__metric-value text-sm font-medium text-slate-800">Escanea el QR de la ubicación</p>
                </div>
            </div>

            <div class="role-hero__actions mt-4 flex flex-wrap items-center gap-2">
                @foreach ($quickActions as $action)
                    <a href="{{ $action['href'] }}"
                       class="{{ $action['variant'] === 'primary' ? 'btn-primary' : 'btn-secondary' }}">
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </section>

        {{-- ===== KPIs ===== --}}
        <section class="role-kpi-grid grid grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ($kpis as $kpi)
                <article class="role-kpi-card">
                    <p>{{ $kpi['label'] }}</p>
                    <p>{{ $kpi['value'] }}</p>
                    <p>{{ $kpi['hint'] }}</p>
                </article>
            @endforeach
        </section>

        {{-- ===== COLA OPERATIVA + PRODUCTIVIDAD ===== --}}
        <div class="reporter-main-grid">

            {{-- Alertas inmediatas --}}
            <section class="role-section role-section-panel reporter-attention">
                <header>
                    <h2>Mis alertas inmediatas</h2>
                    <p>Prioriza incidencias abiertas y sigue su asignación.</p>
                </header>

                <div class="dash-queue-list">
                    @forelse ($attentionItems as $ticket)
                        <article class="dash-queue-card">
                            <div class="dash-queue-row">
                                <div class="dash-queue-info">
                                    <p class="dash-queue-title">{{ $ticket->title }}</p>
                                    <p class="dash-queue-meta">
                                        {{ $ticket->created_at?->diffForHumans() ?? 'N/A' }} 
                                        @if($ticket->location)
                                            · {{ $ticket->location->name }}
                                        @endif
                                    </p>
                                </div>
                                <div class="dash-queue-controls">
                                    <div class="dash-queue-badges">
                                        <span class="dash-state-badge dash-state-badge--{{ $ticket->state }}">{{ $stateLabels[$ticket->state] ?? $ticket->state }}</span>
                                        <span class="dash-priority-badge dash-priority-badge--{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                                    </div>
                                    <a href="{{ route('tickets.show', $ticket) }}" class="dash-queue-link">Abrir</a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="dash-empty-state">
                            <p class="dash-empty-title">No tienes alertas pendientes.</p>
                            <p class="dash-empty-subtitle">Tus tickets activos aparecerán aquí.</p>
                        </div>
                    @endforelse
                </div>

                @if ($attentionItems->count() > 0)
                    <a href="{{ route('reporter.tickets.index') }}" class="reporter-attention-footer">Ver todos mis tickets</a>
                @endif
            </section>

            {{-- Pulso de productividad (Distribución) --}}
            <div class="reporter-side-stack">
                <section class="role-section role-section-panel reporter-summary">
                    <header>
                        <h2>Distribución de mis reportes</h2>
                        <p>Lectura rápida de volumen por estado y prioridad.</p>
                    </header>

                    <div class="dash-distribution-body">
                        <div class="dash-distribution-grid">
                            <article class="dash-mini-card">
                                <p class="dash-mini-label">Por estado</p>
                                @if (count($stateBreakdown) > 0)
                                    <ul class="space-y-1 text-xs">
                                        @foreach ($stateBreakdown as $state => $total)
                                            <li class="flex justify-between">
                                                <span>{{ $stateLabels[$state] ?? $state }}</span>
                                                <strong>{{ $total }}</strong>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p>Sin datos</p>
                                @endif
                            </article>

                            <article class="dash-mini-card">
                                <p class="dash-mini-label">Por prioridad</p>
                                @if (count($priorityBreakdown) > 0)
                                    <ul class="space-y-1 text-xs">
                                        @foreach ($priorityBreakdown as $priority => $total)
                                            <li class="flex justify-between">
                                                <span>{{ $priorityLabels[$priority] ?? $priority }}</span>
                                                <strong>{{ $total }}</strong>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p>Sin datos</p>
                                @endif
                            </article>
                        </div>
                        <p class="dash-distribution-note">Resumen basado en tus tickets reportados.</p>
                    </div>
                </section>

                <section class="role-section role-section-panel reporter-quick-actions">
                    <header>
                        <h2>Acciones rápidas</h2>
                        <p>Atajos directos para reportar o revisar avances.</p>
                    </header>

                    <div class="reporter-actions-body">
                        @foreach ($quickActions as $action)
                            <a href="{{ $action['href'] }}"
                               class="{{ $action['variant'] === 'primary' ? 'btn-primary reporter-action-btn' : 'btn-secondary reporter-action-btn' }}">
                                {{ $action['label'] }}
                            </a>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>


        {{-- ===== TABLA DE ACTIVIDAD RECIENTE ===== --}}
        <section class="role-section">
            <header>
                <h2>Actividad reciente vinculada</h2>
                <p>Tickets donde participas como reportero o responsable.</p>
            </header>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Ubicación</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                            <th>Actualizado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($myRecentTimeline as $ticket)
                            <tr>
                                <td>{{ $ticket->title }}</td>
                                <td>{{ $ticket->location?->name ?? 'N/A' }}</td>
                                <td>
                                    <span class="dash-priority-badge dash-priority-badge--{{ $ticket->priority }}">
                                        {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                                    </span>
                                </td>
                                <td>
                                    <span class="dash-state-badge dash-state-badge--{{ $ticket->state }}">
                                        {{ $stateLabels[$ticket->state] ?? $ticket->state }}
                                    </span>
                                </td>
                                <td>{{ $ticket->updated_at?->diffForHumans() ?? 'N/A' }}</td>
                                <td><a href="{{ route('tickets.show', $ticket) }}">Ver</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="dash-empty">No hay tickets relacionados para mostrar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>
@endsection