@extends('layouts.app')

@section('title', 'Editar ubicacion')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Editar ubicacion</h1>
                <p class="text-sm text-slate-600 mt-1">Mantenimiento de datos y estado QR para {{ $location->room_code }}.</p>
            </div>
            <a href="{{ route('locations.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Volver</a>
        </header>

        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert-error">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="panel panel-pad space-y-4">
            <h2 class="text-lg font-semibold">Datos de la ubicacion</h2>
            <form method="POST" action="{{ route('locations.update', $location) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="block text-sm font-medium mb-1 text-slate-700">Nombre</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $location->name) }}" required class="field">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="building" class="block text-sm font-medium mb-1 text-slate-700">Edificio</label>
                        <input id="building" name="building" type="text" value="{{ old('building', $location->building) }}" required class="field">
                    </div>

                    <div>
                        <label for="floor" class="block text-sm font-medium mb-1 text-slate-700">Piso</label>
                        <input id="floor" name="floor" type="text" value="{{ old('floor', $location->floor) }}" class="field">
                    </div>
                </div>

                <div>
                    <label for="room_code" class="block text-sm font-medium mb-1 text-slate-700">Codigo de aula</label>
                    <input id="room_code" name="room_code" type="text" value="{{ old('room_code', $location->room_code) }}" required class="field">
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $location->is_active ? '1' : '0') === '1') class="rounded border-slate-300">
                    <label for="is_active" class="text-sm text-slate-700">Ubicacion activa</label>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Guardar cambios</button>
                </div>
            </form>
        </section>

        <section class="panel panel-pad space-y-4">
            <h2 class="text-lg font-semibold">Estado QR</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-slate-700">
                <p><strong>QR token:</strong> {{ $location->qr_token ?? 'N/A' }}</p>
                <p><strong>Estado:</strong> {{ $location->qr_generation_status ?? 'pending' }}</p>
                <p><strong>Ultimo error:</strong> {{ $location->qr_last_error ?? 'N/A' }}</p>
                <p><strong>Generado en:</strong> {{ $location->qr_generated_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                <p><strong>Tickets asociados:</strong> {{ $location->tickets_count }}</p>
                <p><strong>Historial incidencias:</strong> {{ $location->incident_history_count }}</p>
            </div>

            @if ($location->qr_image_url)
                <p class="text-sm">
                    <a href="{{ $location->qr_image_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Abrir imagen QR actual</a>
                </p>
            @endif

            <form method="POST" action="{{ route('locations.regenerate-qr', $location) }}">
                @csrf
                <button type="submit" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Regenerar QR</button>
            </form>
        </section>
    </div>
@endsection
