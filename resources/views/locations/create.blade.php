@extends('layouts.app')

@section('title', 'Nueva ubicacion')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Nueva ubicacion</h1>
                <p class="text-sm text-slate-600 mt-1">Registra un espacio para operacion y trazabilidad QR.</p>
            </div>
            <a href="{{ route('locations.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Volver</a>
        </header>

        @if ($errors->any())
            <div class="alert-error">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('locations.store') }}" class="panel panel-pad space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium mb-1 text-slate-700">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="field">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="building" class="block text-sm font-medium mb-1 text-slate-700">Edificio</label>
                    <input id="building" name="building" type="text" value="{{ old('building') }}" required class="field">
                </div>

                <div>
                    <label for="floor" class="block text-sm font-medium mb-1 text-slate-700">Piso</label>
                    <input id="floor" name="floor" type="text" value="{{ old('floor') }}" class="field">
                </div>
            </div>

            <div>
                <label for="room_code" class="block text-sm font-medium mb-1 text-slate-700">Codigo de aula</label>
                <input id="room_code" name="room_code" type="text" value="{{ old('room_code') }}" required class="field">
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', '1') === '1') class="rounded border-slate-300">
                <label for="is_active" class="text-sm text-slate-700">Ubicacion activa</label>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Guardar ubicacion</button>
            </div>
        </form>
    </div>
@endsection
