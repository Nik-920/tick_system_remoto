@extends('layouts.app')

@section('title', 'Editar categoria')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Editar categoria</h1>
                <p class="text-sm text-slate-600 mt-1">Mantenimiento de categoria {{ $category->name }}.</p>
            </div>
            <a href="{{ route('categories.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Volver</a>
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
            <form method="POST" action="{{ route('categories.update', $category) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="block text-sm font-medium mb-1 text-slate-700">Nombre</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $category->name) }}" required class="field">
                </div>

                <div>
                    <label for="icon" class="block text-sm font-medium mb-1 text-slate-700">Icono (opcional)</label>
                    <input id="icon" name="icon" type="text" value="{{ old('icon', $category->icon) }}" class="field">
                    <p class="text-xs text-slate-500 mt-2">Compatibilidad legado: texto libre o URL manual.</p>
                </div>

                <div>
                    <label for="icon_file" class="block text-sm font-medium mb-1 text-slate-700">Reemplazar con archivo (opcional)</label>
                    <input id="icon_file" name="icon_file" type="file" accept="image/*" class="field">
                    <p class="text-xs text-slate-500 mt-2">Si subes un archivo, se usara como icono principal.</p>
                </div>

                @php
                    $resolvedIcon = old('icon', $category->icon);
                @endphp
                @if (is_string($resolvedIcon) && filter_var($resolvedIcon, FILTER_VALIDATE_URL))
                    <div>
                        <p class="text-sm font-medium text-slate-700 mb-2">Vista previa actual</p>
                        <img src="{{ $resolvedIcon }}" alt="Icono de categoria" class="h-12 w-12 rounded-md border border-slate-200 object-cover">
                    </div>
                @endif

                <div>
                    <label for="description" class="block text-sm font-medium mb-1 text-slate-700">Descripcion</label>
                    <textarea id="description" name="description" class="field" rows="4">{{ old('description', $category->description) }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-slate-700">
                    <p><strong>Tickets asociados:</strong> {{ $category->tickets_count }}</p>
                    <p><strong>Historial incidencias:</strong> {{ $category->incident_history_count }}</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Guardar cambios</button>
                </div>
            </form>
        </section>
    </div>
@endsection
