<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo Ticket</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
<div class="max-w-3xl mx-auto p-6 space-y-6">
    <header class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Crear Ticket</h1>
        <a href="{{ route('tickets.index') }}" class="text-blue-600 hover:underline">Volver al listado</a>
    </header>

    @if (session('status'))
        <div class="bg-green-100 border border-green-300 text-green-800 p-3 rounded-md">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="bg-red-100 border border-red-300 text-red-800 p-3 rounded-md">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tickets.store') }}" class="bg-white border rounded-lg p-5 space-y-4">
        @csrf

        <div>
            <label for="title" class="block text-sm font-medium mb-1">Título</label>
            <input id="title" type="text" name="title" value="{{ old('title') }}" required class="w-full border rounded-md p-2">
        </div>

        <div>
            <label for="description" class="block text-sm font-medium mb-1">Descripción</label>
            <textarea id="description" name="description" rows="5" required class="w-full border rounded-md p-2">{{ old('description') }}</textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="location_id" class="block text-sm font-medium mb-1">Ubicación</label>
                <select id="location_id" name="location_id" required class="w-full border rounded-md p-2">
                    <option value="">Selecciona ubicación</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $selectedLocationId) === $location->id)>
                            {{ $location->name }} ({{ $location->room_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="category_id" class="block text-sm font-medium mb-1">Categoría</label>
                <select id="category_id" name="category_id" required class="w-full border rounded-md p-2">
                    <option value="">Selecciona categoría</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') === $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label for="priority" class="block text-sm font-medium mb-1">Prioridad</label>
            <select id="priority" name="priority" class="w-full border rounded-md p-2">
                @foreach ($priorities as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>{{ ucfirst($priority) }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Guardar ticket</button>
        </div>
    </form>
</div>
</body>
</html>
