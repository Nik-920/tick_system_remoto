@extends('layouts.app')

@section('title', 'Centro De Control Admin')

@section('content')
    <div class="role-dashboard role-dashboard-admin space-y-6">

        {{-- ===== HERO ===== --}}
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

        {{-- ===== QR + UBICACIONES ===== --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

            {{-- Salud QR --}}
            <section class="role-section">
                <header>
                    <h2>Salud QR por estado</h2>
                    <p>Semaforo de generacion y estabilidad QR institucional.</p>
                </header>

                <div class="grid grid-cols-2 gap-2 p-4">
                    <article class="dash-mini-card">
                        <p class="dash-mini-label">Pending</p>
                        <p class="dash-mini-val">{{ $qrStatusSummary['pending'] ?? 0 }}</p>
                    </article>
                    <article class="dash-mini-card">
                        <p class="dash-mini-label">Processing</p>
                        <p class="dash-mini-val dash-mini-val--amber">{{ $qrStatusSummary['processing'] ?? 0 }}</p>
                    </article>
                    <article class="dash-mini-card">
                        <p class="dash-mini-label">Failed</p>
                        <p class="dash-mini-val dash-mini-val--red">{{ $qrStatusSummary['failed'] ?? 0 }}</p>
                    </article>
                    <article class="dash-mini-card">
                        <p class="dash-mini-label">Ready</p>
                        <p class="dash-mini-val dash-mini-val--green">{{ $qrStatusSummary['ready'] ?? 0 }}</p>
                    </article>
                </div>
            </section>

            {{-- Top ubicaciones --}}
            <section class="role-section xl:col-span-2">
                <header>
                    <h2>Top ubicaciones con carga operativa</h2>
                    <p>Espacios con mayor volumen de incidencias abiertas/en progreso.</p>
                </header>

                <div class="p-4 space-y-2">
                    @forelse ($topLocationsByOpen as $location)
                        <article class="dash-location-card">
                            <div>
                                <p class="dash-location-name">{{ $location->name }}</p>
                                <p class="dash-location-meta">{{ $location->room_code }} · {{ $location->building }}</p>
                            </div>
                            <div class="dash-location-stats">
                                <p>Abiertos: <strong>{{ $location->open_tickets_count }}</strong></p>
                                <p>En progreso: <strong>{{ $location->in_progress_tickets_count }}</strong></p>
                            </div>
                        </article>
                    @empty
                        <p class="dash-empty">No hay ubicaciones con carga activa en este momento.</p>
                    @endforelse
                </div>
            </section>
        </div>

        {{-- ===== TABLAS ===== --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

            {{-- Radar QR --}}
            <section class="role-section">
                <header>
                    <h2>Radar de incidencias QR</h2>
                    <p>Ubicaciones con QR pendiente, procesando o fallido.</p>
                </header>
                <div class="table-wrap">
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
                                    <td>
                                        <span class="dash-qr-badge dash-qr-badge--{{ $location->qr_generation_status ?? 'pending' }}">
                                            {{ $location->qr_generation_status ?? 'pending' }}
                                        </span>
                                    </td>
                                    <td>{{ $location->tickets_count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="dash-empty">No hay incidencias QR activas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Actividad reciente --}}
            <section class="role-section">
                <header>
                    <h2>Actividad global reciente</h2>
                    <p>Ultimos movimientos sobre tickets en toda la plataforma.</p>
                </header>
                <div class="table-wrap">
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
                                    <td>
                                        <span class="dash-state-badge dash-state-badge--{{ $ticket->state }}">
                                            {{ $stateLabels[$ticket->state] ?? $ticket->state }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="dash-priority-badge dash-priority-badge--{{ $ticket->priority }}">
                                            {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                                        </span>
                                    </td>
                                    <td>{{ $ticket->location?->name ?? 'N/A' }}</td>
                                    <td><a href="{{ route('tickets.show', $ticket) }}">Ver</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="dash-empty">No hay actividad reciente para mostrar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

    </div>
@endsection
