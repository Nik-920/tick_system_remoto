@extends('layouts.app')

@section('title', 'Consola Maintenance')

@section('content')
    <div class="role-dashboard role-dashboard-maintenance space-y-6">

        {{-- HERO --}}
        <section class="role-hero role-hero-maintenance panel panel-pad overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-600 dark:text-amber-400">{{ $hero['badge'] }}</p>
                    <h1 class="mt-2 text-3xl md:text-4xl font-black tracking-tight text-slate-900 dark:text-white">{{ $hero['title'] }}</h1>
                    <p class="mt-2 text-sm md:text-base text-slate-700 dark:text-slate-300">{{ $hero['subtitle'] }}</p>
                    <p class="mt-3 text-xs uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Perfil operativo: {{ $roleLabel }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @foreach ($quickActions as $action)
                        
                            href="{{ $action['href'] }}"
                            class="{{ $action['variant'] === 'primary'
                                ? 'btn-primary bg-amber-600 hover:bg-amber-700 text-white dark:bg-amber-500 dark:hover:bg-amber-600'
                                : 'btn-secondary border border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-600 dark:text-amber-400 dark:hover:bg-amber-900/30' }}"
                        >
                            {{ $action['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- KPIs --}}
        <section class="role-kpi-grid grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
            @foreach ($kpis as $kpi)
                <article class="role-kpi-card panel panel-pad border border-amber-100 dark:border-amber-800 bg-amber-50/40 dark:bg-amber-900/20 space-y-2">
                    <p class="text-xs uppercase tracking-[0.1em] font-semibold text-amber-700 dark:text-amber-400">{{ $kpi['label'] }}</p>
                    <p class="text-3xl font-black text-slate-900 dark:text-white">{{ $kpi['value'] }}</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ $kpi['hint'] }}</p>
                </article>
            @endforeach
        </section>

        {{-- COLA + PULSO --}}
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">

            {{-- Cola operativa --}}
            <section class="role-section panel panel-pad border border-amber-100 dark:border-amber-800 xl:col-span-3">
                <header class="mb-3">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Cola operativa priorizada</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Ordenada por criticidad y antiguedad para ejecucion inmediata.</p>
                </header>

                <div class="space-y-2">
                    @forelse ($workloadQueue as $ticket)
                        <article class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ $ticket->title }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        Reportado por {{ $ticket->reporter?->name ?? 'N/A' }} · {{ $ticket->location?->name ?? 'N/A' }}
                                    </p>
                                </div>
                                <a href="{{ route('tickets.show', $ticket) }}" class="text-amber-700 dark:text-amber-400 text-sm font-semibold hover:underline">Gestionar</a>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-1 text-slate-700 dark:text-slate-300">Estado: {{ $stateLabels[$ticket->state] ?? $ticket->state }}</span>
                                <span class="rounded-full bg-amber-100 dark:bg-amber-900/40 px-2 py-1 text-amber-700 dark:text-amber-400">Prioridad: {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</span>
                                <span class="rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-1 text-slate-600 dark:text-slate-400">Creado: {{ $ticket->created_at?->diffForHumans() ?? 'N/A' }}</span>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-slate-600 dark:text-slate-400">No tienes tickets activos en cola. ¡Bien hecho!</p>
                    @endforelse
                </div>
            </section>

            {{-- Pulso de productividad --}}
            <section class="role-section panel panel-pad border border-amber-100 dark:border-amber-800 xl:col-span-2">
                <header class="mb-3">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Pulso de productividad</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Vista rapida de rendimiento y cierres recientes.</p>
                </header>

                <div class="grid grid-cols-1 gap-2">
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-2">Estados activos</h3>
                        @if (count($stateBreakdown) > 0)
                            <ul class="space-y-1">
                                @foreach ($stateBreakdown as $state => $total)
                                    <li class="flex items-center justify-between text-sm">
                                        <span class="text-slate-600 dark:text-slate-400">{{ $stateLabels[$state] ?? $state }}</span>
                                        <strong class="text-slate-900 dark:text-white">{{ $total }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-slate-500 dark:text-slate-400">Sin datos.</p>
                        @endif
                    </div>

                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-3">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-2">Carga por prioridad</h3>
                        @if (count($priorityBreakdown) > 0)
                            <ul class="space-y-1">
                                @foreach ($priorityBreakdown as $priority => $total)
                                    <li class="flex items-center justify-between text-sm">
                                        <span class="text-slate-600 dark:text-slate-400">{{ $priorityLabels[$priority] ?? $priority }}</span>
                                        <strong class="text-slate-900 dark:text-white">{{ $total }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-slate-500 dark:text-slate-400">Sin datos.</p>
                        @endif
                    </div>
                </div>
            </section>
        </div>

        {{-- CIERRES RECIENTES --}}
        <section class="role-section panel overflow-hidden border border-amber-100 dark:border-amber-800">
            <header class="panel-pad border-b border-slate-200 dark:border-slate-700">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Cierres recientes</h2>
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Ultimos tickets que marcaste como resueltos.</p>
            </header>

            <table>
                <thead>
                    <tr>
                        <th>Titulo</th>
                        <th>Ubicacion</th>
                        <th>Prioridad</th>
                        <th>Resuelto</th>
                        <th>Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentResolved as $ticket)
                        <tr>
                            <td>{{ $ticket->title }}</td>
                            <td>{{ $ticket->location?->name ?? 'N/A' }}</td>
                            <td>{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</td>
                            <td>{{ $ticket->resolved_at?->diffForHumans() ?? 'N/A' }}</td>
                            <td><a href="{{ route('tickets.show', $ticket) }}" class="text-amber-700 dark:text-amber-400 hover:underline">Ver</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-slate-600 dark:text-slate-400">Aun no registras tickets resueltos recientemente.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

    </div>
@endsection