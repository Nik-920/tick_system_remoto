@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Dashboard</h1>
                <p class="text-base font-semibold text-slate-800 mt-1">{{ $headline }}</p>
                <p class="text-sm text-slate-600 mt-1">Perfil activo: {{ $roleLabel }}. {{ $subtitle }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @can('create', \App\Models\Ticket::class)
                    <a href="{{ route('tickets.create') }}" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Nuevo ticket</a>
                @endcan

                @can('create', \App\Models\Location::class)
                    <a href="{{ route('locations.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Ubicaciones</a>
                @endcan

                @can('create', \App\Models\Category::class)
                    <a href="{{ route('categories.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Categorias</a>
                @endcan
            </div>
        </header>

        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach ($kpis as $kpi)
                <article class="panel panel-pad space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $kpi['label'] }}</p>
                    <p class="text-3xl font-bold text-slate-900">{{ $kpi['value'] }}</p>
                    <p class="text-sm text-slate-600">{{ $kpi['hint'] }}</p>
                </article>
            @endforeach
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <section class="panel panel-pad">
                <h2 class="text-lg font-semibold text-slate-900 mb-3">Distribucion por estado</h2>

                @if (count($stateBreakdown) > 0)
                    <ul class="space-y-2">
                        @foreach ($stateBreakdown as $state => $total)
                            <li class="flex items-center justify-between border border-slate-200 rounded-md px-3 py-2 text-sm">
                                <span class="text-slate-700">{{ $stateLabels[$state] ?? $state }}</span>
                                <strong class="text-slate-900">{{ $total }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-slate-600">No hay datos de estado para mostrar.</p>
                @endif
            </section>

            <section class="panel panel-pad">
                <h2 class="text-lg font-semibold text-slate-900 mb-3">Distribucion por prioridad</h2>

                @if (count($priorityBreakdown) > 0)
                    <ul class="space-y-2">
                        @foreach ($priorityBreakdown as $priority => $total)
                            <li class="flex items-center justify-between border border-slate-200 rounded-md px-3 py-2 text-sm">
                                <span class="text-slate-700">{{ $priorityLabels[$priority] ?? $priority }}</span>
                                <strong class="text-slate-900">{{ $total }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-slate-600">No hay datos de prioridad para mostrar.</p>
                @endif
            </section>
        </div>

        @if ($showQrIssues)
            <section class="panel overflow-hidden">
                <header class="panel-pad border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900">QR con incidencias</h2>
                    <p class="text-sm text-slate-600 mt-1">Ubicaciones con generacion QR en estado pendiente, en proceso o fallido.</p>
                </header>

                <table>
                    <thead>
                    <tr>
                        <th>Ubicacion</th>
                        <th>Aula</th>
                        <th>Estado QR</th>
                        <th>Tickets</th>
                        <th>Error</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($qrIssues as $location)
                        <tr>
                            <td>{{ $location->name }}</td>
                            <td>{{ $location->room_code }}</td>
                            <td>{{ $location->qr_generation_status ?? 'pending' }}</td>
                            <td>{{ $location->tickets_count }}</td>
                            <td>{{ $location->qr_last_error ?? 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-slate-600">No hay incidencias QR activas.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        @endif

        <section class="panel overflow-hidden">
            <header class="panel-pad border-b border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900">Tickets recientes</h2>
                <p class="text-sm text-slate-600 mt-1">Ultima actividad visible para tu perfil.</p>
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
                        <td>
                            <a href="{{ route('tickets.show', $ticket) }}" class="text-blue-600 hover:underline">Ver detalle</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-slate-600">No hay tickets recientes para mostrar.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
