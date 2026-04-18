@extends('layouts.app')

@section('title', 'Tickets')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Tickets</h1>
                <p class="text-sm text-slate-600 mt-1">Gestion centralizada de incidencias reportadas.</p>
            </div>
            <a href="{{ route('tickets.create') }}" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Nuevo ticket</a>
        </header>

        @if (session('status'))
            <div class="alert-success bg-green-100 border border-green-300 text-green-800 p-3 rounded-md">{{ session('status') }}</div>
        @endif

        <form method="GET" action="{{ route('tickets.index') }}" class="panel panel-pad bg-white border rounded-lg p-4 grid grid-cols-1 md:grid-cols-6 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar..." class="field border rounded-md p-2 md:col-span-2">

            <select name="state" class="field border rounded-md p-2">
                <option value="">Estado</option>
                @foreach (['open' => 'Abierto', 'in_progress' => 'En progreso', 'resolved' => 'Resuelto', 'rejected' => 'Rechazado'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['state'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="priority" class="field border rounded-md p-2">
                <option value="">Prioridad</option>
                @foreach (['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Crítica'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['priority'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="field border rounded-md p-2">
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="field border rounded-md p-2">

            <div class="md:col-span-6 flex gap-2">
                <button type="submit" class="btn-primary bg-slate-900 text-white px-3 py-2 rounded-md">Filtrar</button>
                <a href="{{ route('tickets.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Limpiar</a>
            </div>
        </form>

        <section class="panel bg-white border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-100">
                <tr>
                    <th class="text-left p-3">Titulo</th>
                    <th class="text-left p-3">Estado</th>
                    <th class="text-left p-3">Prioridad</th>
                    <th class="text-left p-3">Ubicacion</th>
                    <th class="text-left p-3">Creado</th>
                    <th class="text-left p-3">Acciones</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($tickets as $ticket)
                    <tr class="border-t border-slate-200">
                        <td class="p-3 font-medium">{{ $ticket->title }}</td>
                        <td class="p-3">{{ $ticket->state }}</td>
                        <td class="p-3">{{ $ticket->priority }}</td>
                        <td class="p-3">{{ $ticket->location?->name ?? 'N/A' }}</td>
                        <td class="p-3">{{ $ticket->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="p-3">
                            <a href="{{ route('tickets.show', $ticket) }}" class="text-blue-600 hover:underline">Ver detalle</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-4 text-slate-600">No hay tickets registrados.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <div>
            {{ $tickets->links() }}
        </div>
    </div>
@endsection
