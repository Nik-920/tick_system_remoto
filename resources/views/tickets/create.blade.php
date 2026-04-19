@extends('layouts.app')

@section('title', 'Nuevo Ticket')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Crear Ticket</h1>
                <p class="text-sm text-slate-600 mt-1">Registra una nueva incidencia para su seguimiento.</p>
            </div>
            <a href="{{ route('tickets.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Volver al listado</a>
        </header>

        @if (session('status'))
            <div class="alert-success bg-green-100 border border-green-300 text-green-800 p-3 rounded-md">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert-error bg-red-100 border border-red-300 text-red-800 p-3 rounded-md">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="panel panel-pad bg-white border rounded-lg p-5 space-y-4">
            @csrf

            <div>
                <label for="title" class="block text-sm font-medium mb-1 text-slate-700">Titulo</label>
                <input id="title" type="text" name="title" value="{{ old('title') }}" required class="field w-full border rounded-md p-2">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium mb-1 text-slate-700">Descripcion</label>
                <textarea id="description" name="description" rows="5" required class="field w-full border rounded-md p-2">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="location_id" class="block text-sm font-medium mb-1 text-slate-700">Ubicacion</label>
                    <select id="location_id" name="location_id" required class="field w-full border rounded-md p-2">
                        <option value="">Selecciona ubicacion</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location_id', $selectedLocationId) === $location->id)>
                                {{ $location->name }} ({{ $location->room_code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="category_id" class="block text-sm font-medium mb-1 text-slate-700">Categoria</label>
                    <select id="category_id" name="category_id" required class="field w-full border rounded-md p-2">
                        <option value="">Selecciona categoria</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') === $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="priority" class="block text-sm font-medium mb-1 text-slate-700">Prioridad</label>
                <select id="priority" name="priority" class="field w-full border rounded-md p-2">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>{{ ucfirst($priority) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="media_files" class="block text-sm font-medium mb-1 text-slate-700">Adjuntos (opcional)</label>
                <input id="media_files" type="file" name="media_files[]" multiple class="field w-full border rounded-md p-2">
                <p class="text-xs text-slate-500 mt-2">Hasta 5 archivos de maximo 10 MB cada uno (imagenes, PDF, documentos y video).</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Guardar ticket</button>
            </div>
        </form>
    </div>
@endsection
