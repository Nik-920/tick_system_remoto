@extends('layouts.app')

@section('title', 'Centro De Control Mantenimiento')

@section('content')
    <div class="role-dashboard role-dashboard-maintenance space-y-6">

        {{-- ===== HERO ===== --}}
        <section class="role-hero role-hero-maintenance panel panel-pad overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-700">{{ $hero['badge'] }}</p>
                    <h1 class="mt-2 text-3xl md:text-4xl font-black tracking-tight text-slate-900">{{ $hero['title'] }}</h1>
                    <p class="mt-2 text-slate-700 text-sm md:text-base">{{ $hero['subtitle'] }}</p>
                    <p class="mt-3 text-xs uppercase tracking-[0.12em] text-slate-500">Perfil operativo: {{ $roleLabel }}</p>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-right">
                    <p class="text-xs uppercase tracking-[0.1em] font-semibold text-amber-700">Tiempo medio resolucion 30 dias</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ $avgResolutionHoursLast30Days }}h</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
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
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

            {{-- Cola operativa --}}
            <section class="role-section">
                <header>
                    <h2>Cola operativa priorizada</h2>
                    <p>Ordenada por criticidad y antigüedad para ejecución inmediata.</p>
                </header>

                <div class="p-4 space-y-2">
                    @forelse ($workloadQueue as $ticket)
                        <article class="dash-queue-card">
                            <div>
                                <p class="dash-queue-title">{{ $ticket->title }}</p>
                                <p class="dash-queue-meta">{{ $ticket->created_at?->diffForHumans() ?? 'N/A' }} · {{ $ticket->location?->name ?? 'N/A' }}</p>
                            </div>
                            <div class="dash-queue-badges">
                                <span class="dash-state-badge dash-state-badge--{{ $ticket->state }}">{{ $stateLabels[$ticket->state] ?? $ticket->state }}</span>
                                <span class="dash-priority-badge dash-priority-badge--{{ $ticket->priority }}">{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                            </div>
                            <a href="{{ route('tickets.show', $ticket) }}" class="dash-queue-link">Gestionar</a>
                        </article>
                    @empty
                        <p class="dash-empty">No tienes tickets activos en cola. ¡Bien hecho!</p>
                    @endforelse
                </div>
            </section>

            {{-- Pulso de productividad --}}
            <section class="role-section">
                <header>
                    <h2>Pulso de productividad</h2>
                    <p>Vista rápida de rendimiento y cierres recientes.</p>
                </header>

                <div class="grid grid-cols-2 gap-3 p-4">
                    <article class="dash-mini-card">
                        <p class="dash-mini-label">Estados activos</p>
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
                        <p class="dash-mini-label">Carga por prioridad</p>
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
            </section>
        </div>


        {{-- ===== TABLA DE CIERRES RECIENTES ===== --}}
        <section class="role-section">
            <header>
                <h2>Cierres recientes</h2>
                <p>Últimos tickets que marcaste como resueltos.</p>
            </header>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Ubicación</th>
                            <th>Prioridad</th>
                            <th>Resuelto</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentResolved as $ticket)
                            <tr>
                                <td>{{ $ticket->title }}</td>
                                <td>{{ $ticket->location?->name ?? 'N/A' }}</td>
                                <td>
                                    <span class="dash-priority-badge dash-priority-badge--{{ $ticket->priority }}">
                                        {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                                    </span>
                                </td>
                                <td>{{ $ticket->resolved_at?->diffForHumans() ?? 'N/A' }}</td>
                                <td><a href="{{ route('tickets.show', $ticket) }}">Ver</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="dash-empty">Aún no registras tickets resueltos recientemente.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>
@endsection