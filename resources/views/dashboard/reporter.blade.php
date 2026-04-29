@extends('layouts.app')

@section('title', 'Centro Personal Reporter')

@section('content')
    <div class="role-dashboard role-dashboard-reporter space-y-6">
        <section class="role-hero role-hero-reporter panel panel-pad overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-700">{{ $hero['badge'] }}</p>
                    <h1 class="mt-2 text-3xl md:text-4xl font-black tracking-tight text-slate-900">{{ $hero['title'] }}</h1>
                    <p class="mt-2 text-slate-700 text-sm md:text-base">{{ $hero['subtitle'] }}</p>
                    <p class="mt-3 text-xs uppercase tracking-[0.12em] text-slate-500">Perfil operativo: {{ $roleLabel }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($quickActions as $action)
                        <a
                            href="{{ $action['href'] }}"
                            class="{{ $action['variant'] === 'primary' ? 'btn-primary bg-cyan-600 hover:bg-cyan-700 text-white' : 'btn-secondary border border-cyan-200 text-cyan-700 hover:bg-cyan-50' }}"
                        >
                            {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="role-kpi-grid grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ($kpis as $kpi)
                <article class="role-kpi-card panel panel-pad border border-cyan-100 bg-cyan-50/40 space-y-2">
                    <p class="text-xs uppercase tracking-[0.1em] text-cyan-700 font-semibold">{{ $kpi['label'] }}</p>
                    <p class="text-3xl font-black text-slate-900">{{ $kpi['value'] }}</p>
                    <p class="text-sm text-slate-600">{{ $kpi['hint'] }}</p>
                </article>
            @endforeach
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <section class="role-section panel panel-pad border border-cyan-100">
                <header class="mb-3">
                    <h2 class="text-lg font-bold text-slate-900">Mis alertas inmediatas</h2>
                    <p class="text-sm text-slate-600">Prioriza incidentes abiertos y sigue su asignacion.</p>
                </header>

                <div class="space-y-2">
                    @forelse ($attentionItems as $ticket)
                        <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $ticket->title }}</p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        {{ $ticket->location?->name ?? 'N/A' }} · {{ $ticket->category?->name ?? 'Sin categoria' }}
                                    </p>
                                </div>
                                <a href="{{ route('tickets.show', $ticket) }}" class="text-cyan-700 text-sm font-semibold hover:underline">Abrir</a>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">Estado: {{ $stateLabels[$ticket->state] ?? $ticket->state }}</span>
                                <span class="rounded-full bg-cyan-100 px-2 py-1 text-cyan-700">Prioridad: {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-600">Asignado: {{ $ticket->assignee?->name ?? 'Pendiente' }}</span>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-slate-600">No hay alertas activas por ahora.</p>
                    @endforelse
                </div>
            </section>

            <section class="role-section panel panel-pad border border-cyan-100">
                <header class="mb-3">
                    <h2 class="text-lg font-bold text-slate-900">Distribucion de mis reportes</h2>
                    <p class="text-sm text-slate-600">Lectura rapida de volumen por estado y prioridad.</p>
                </header>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <h3 class="text-sm font-semibold text-slate-800 mb-2">Por estado</h3>
                        @if (count($stateBreakdown) > 0)
                            <ul class="space-y-1">
                                @foreach ($stateBreakdown as $state => $total)
                                    <li class="flex items-center justify-between text-sm">
                                        <span class="text-slate-600">{{ $stateLabels[$state] ?? $state }}</span>
                                        <strong class="text-slate-900">{{ $total }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-slate-500">Sin datos.</p>
                        @endif
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <h3 class="text-sm font-semibold text-slate-800 mb-2">Por prioridad</h3>
                        @if (count($priorityBreakdown) > 0)
                            <ul class="space-y-1">
                                @foreach ($priorityBreakdown as $priority => $total)
                                    <li class="flex items-center justify-between text-sm">
                                        <span class="text-slate-600">{{ $priorityLabels[$priority] ?? $priority }}</span>
                                        <strong class="text-slate-900">{{ $total }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-slate-500">Sin datos.</p>
                        @endif
                    </div>
                </div>
            </section>
        </div>

        <section class="role-section panel overflow-hidden border border-cyan-100">
            <header class="panel-pad border-b border-slate-200">
                <h2 class="text-lg font-bold text-slate-900">Actividad reciente vinculada</h2>
                <p class="text-sm text-slate-600 mt-1">Tickets donde participas como reportero o responsable.</p>
            </header>

            <table>
                <thead>
                <tr>
                    <th>Titulo</th>
                    <th>Estado</th>
                    <th>Prioridad</th>
                    <th>Ubicacion</th>
                    <th>Actualizado</th>
                    <th>Accion</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($myRecentTimeline as $ticket)
                    <tr>
                        <td>{{ $ticket->title }}</td>
                        <td>{{ $stateLabels[$ticket->state] ?? $ticket->state }}</td>
                        <td>{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</td>
                        <td>{{ $ticket->location?->name ?? 'N/A' }}</td>
                        <td>{{ $ticket->updated_at?->diffForHumans() ?? 'N/A' }}</td>
                        <td><a href="{{ route('tickets.show', $ticket) }}" class="text-cyan-700 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-slate-600">No hay tickets relacionados para mostrar.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
