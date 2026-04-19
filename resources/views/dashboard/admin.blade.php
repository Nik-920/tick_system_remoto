@extends('layouts.app')

@section('title', 'Centro De Control Admin')

@section('content')
    <div class="role-dashboard role-dashboard-admin space-y-6">
        <section class="role-hero role-hero-admin panel panel-pad overflow-hidden">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700">{{ $hero['badge'] }}</p>
                    <h1 class="mt-2 text-3xl md:text-4xl font-black tracking-tight text-slate-900">{{ $hero['title'] }}</h1>
                    <p class="mt-2 text-slate-700 text-sm md:text-base">{{ $hero['subtitle'] }}</p>
                    <p class="mt-3 text-xs uppercase tracking-[0.12em] text-slate-500">Perfil operativo: {{ $roleLabel }}</p>
                </div>

                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-right">
                    <p class="text-xs uppercase tracking-[0.1em] font-semibold text-emerald-700">Tasa resolucion 7 dias</p>
                    <p class="text-2xl font-black text-slate-900 mt-1">{{ $hero['resolutionRate7Days'] }}</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @foreach ($quickActions as $action)
                    <a
                        href="{{ $action['href'] }}"
                        class="{{ $action['variant'] === 'primary' ? 'btn-primary bg-emerald-700 hover:bg-emerald-800 text-white' : 'btn-secondary border border-emerald-200 text-emerald-800 hover:bg-emerald-50' }}"
                    >
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </section>

        <section class="role-kpi-grid grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ($kpis as $kpi)
                <article class="role-kpi-card panel panel-pad border border-emerald-100 bg-emerald-50/35 space-y-2">
                    <p class="text-xs uppercase tracking-[0.1em] text-emerald-700 font-semibold">{{ $kpi['label'] }}</p>
                    <p class="text-3xl font-black text-slate-900">{{ $kpi['value'] }}</p>
                    <p class="text-sm text-slate-600">{{ $kpi['hint'] }}</p>
                </article>
            @endforeach
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <section class="role-section panel panel-pad border border-emerald-100">
                <header class="mb-3">
                    <h2 class="text-lg font-bold text-slate-900">Salud QR por estado</h2>
                    <p class="text-sm text-slate-600">Semaforo de generacion y estabilidad QR institucional.</p>
                </header>

                <div class="grid grid-cols-2 gap-2">
                    <article class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Pending</p>
                        <p class="text-2xl font-black text-slate-900 mt-1">{{ $qrStatusSummary['pending'] ?? 0 }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Processing</p>
                        <p class="text-2xl font-black text-slate-900 mt-1">{{ $qrStatusSummary['processing'] ?? 0 }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Failed</p>
                        <p class="text-2xl font-black text-red-700 mt-1">{{ $qrStatusSummary['failed'] ?? 0 }}</p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-xs uppercase tracking-[0.1em] text-slate-500">Ready</p>
                        <p class="text-2xl font-black text-emerald-700 mt-1">{{ $qrStatusSummary['ready'] ?? 0 }}</p>
                    </article>
                </div>
            </section>

            <section class="role-section panel panel-pad border border-emerald-100 xl:col-span-2">
                <header class="mb-3">
                    <h2 class="text-lg font-bold text-slate-900">Top ubicaciones con carga operativa</h2>
                    <p class="text-sm text-slate-600">Espacios con mayor volumen de incidencias abiertas/en progreso.</p>
                </header>

                <div class="space-y-2">
                    @forelse ($topLocationsByOpen as $location)
                        <article class="rounded-xl border border-slate-200 bg-white px-3 py-2">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $location->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $location->room_code }} · {{ $location->building }}</p>
                                </div>
                                <div class="text-right text-xs">
                                    <p class="text-slate-700">Abiertos: <strong>{{ $location->open_tickets_count }}</strong></p>
                                    <p class="text-slate-700">En progreso: <strong>{{ $location->in_progress_tickets_count }}</strong></p>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-slate-600">No hay ubicaciones con carga activa en este momento.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <section class="role-section panel overflow-hidden border border-emerald-100">
                <header class="panel-pad border-b border-slate-200">
                    <h2 class="text-lg font-bold text-slate-900">Radar de incidencias QR</h2>
                    <p class="text-sm text-slate-600 mt-1">Ubicaciones con QR pendiente, procesando o fallido.</p>
                </header>

                <table>
                    <thead>
                    <tr>
                        <th>Ubicacion</th>
                        <th>Aula</th>
                        <th>Estado QR</th>
                        <th>Tickets</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($qrIssues as $location)
                        <tr>
                            <td>{{ $location->name }}</td>
                            <td>{{ $location->room_code }}</td>
                            <td>{{ $location->qr_generation_status ?? 'pending' }}</td>
                            <td>{{ $location->tickets_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-slate-600">No hay incidencias QR activas.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </section>

            <section class="role-section panel overflow-hidden border border-emerald-100">
                <header class="panel-pad border-b border-slate-200">
                    <h2 class="text-lg font-bold text-slate-900">Actividad global reciente</h2>
                    <p class="text-sm text-slate-600 mt-1">Ultimos movimientos sobre tickets en toda la plataforma.</p>
                </header>

                <table>
                    <thead>
                    <tr>
                        <th>Titulo</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Ubicacion</th>
                        <th>Accion</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($recentTickets as $ticket)
                        <tr>
                            <td>{{ $ticket->title }}</td>
                            <td>{{ $stateLabels[$ticket->state] ?? $ticket->state }}</td>
                            <td>{{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}</td>
                            <td>{{ $ticket->location?->name ?? 'N/A' }}</td>
                            <td><a href="{{ route('tickets.show', $ticket) }}" class="text-emerald-700 hover:underline">Ver</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-slate-600">No hay actividad reciente para mostrar.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
@endsection
