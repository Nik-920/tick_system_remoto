@extends('layouts.app')

@section('title', 'Ubicaciones')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Ubicaciones</h1>
                <p class="text-sm text-slate-600 mt-1">Gestion operativa de espacios y estado de codigos QR.</p>
            </div>
            <a href="{{ route('locations.create') }}" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Nueva ubicacion</a>
        </header>

        @if (session('status'))
            <div class="alert-success bg-green-100 border border-green-300 text-green-800 p-3 rounded-md">{{ session('status') }}</div>
        @endif

        <form method="GET" action="{{ route('locations.index') }}" class="panel panel-pad grid grid-cols-1 md:grid-cols-5 gap-3">
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar por nombre, edificio o aula" class="field md:col-span-2">
            <input type="text" name="building" value="{{ $filters['building'] ?? '' }}" placeholder="Edificio" class="field">
            <input type="text" name="floor" value="{{ $filters['floor'] ?? '' }}" placeholder="Piso" class="field">
            <select name="is_active" class="field">
                <option value="">Estado</option>
                <option value="1" @selected(($filters['is_active'] ?? null) === true || ($filters['is_active'] ?? null) === '1')>Activa</option>
                <option value="0" @selected(($filters['is_active'] ?? null) === false || ($filters['is_active'] ?? null) === '0')>Inactiva</option>
            </select>

            <div class="md:col-span-5 flex gap-2">
                <button type="submit" class="btn-primary bg-slate-900 text-white px-3 py-2 rounded-md">Filtrar</button>
                <a href="{{ route('locations.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Limpiar</a>
            </div>
        </form>

        <section class="panel overflow-hidden">
            <table>
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Edificio</th>
                    <th>Piso</th>
                    <th>Aula</th>
                    <th>QR</th>
                    <th>Activa</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($locations as $location)
                    <tr>
                        <td>{{ $location->name }}</td>
                        <td>{{ $location->building }}</td>
                        <td>{{ $location->floor ?? 'N/A' }}</td>
                        <td>{{ $location->room_code }}</td>
                        <td>{{ $location->qr_generation_status ?? 'pending' }}</td>
                        <td>{{ $location->is_active ? 'Si' : 'No' }}</td>
                        <td>
                            <a href="{{ route('locations.edit', $location) }}" class="text-blue-600 hover:underline">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-slate-600">No hay ubicaciones registradas.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <div>
            {{ $locations->links() }}
        </div>
    </div>
@endsection
