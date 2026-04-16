<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle Ticket</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
<div class="max-w-4xl mx-auto p-6 space-y-6">
    <header class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Detalle Ticket</h1>
        <a href="{{ route('tickets.index') }}" class="text-blue-600 hover:underline">Volver</a>
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

    <section class="bg-white border rounded-lg p-5 space-y-3">
        <h2 class="text-xl font-semibold">{{ $ticket->title }}</h2>
        <p class="text-slate-700">{{ $ticket->description }}</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <p><strong>Estado:</strong> {{ $ticket->state }}</p>
            <p><strong>Prioridad:</strong> {{ $ticket->priority }}</p>
            <p><strong>Ubicación:</strong> {{ $ticket->location?->name ?? 'N/A' }}</p>
            <p><strong>Categoría:</strong> {{ $ticket->category?->name ?? 'N/A' }}</p>
            <p><strong>Reportado por:</strong> {{ $ticket->reporter?->name ?? $ticket->reporter?->email ?? 'N/A' }}</p>
            <p><strong>Creado:</strong> {{ $ticket->created_at?->format('d/m/Y H:i') }}</p>
        </div>
    </section>

    <section class="bg-white border rounded-lg p-5 space-y-3">
        <h3 class="text-lg font-semibold">Actualizar estado</h3>
        <form method="POST" action="{{ route('tickets.update-state', $ticket) }}" class="space-y-3">
            @csrf
            @method('PATCH')

            <div>
                <label for="to_state" class="block text-sm font-medium mb-1">Nuevo estado</label>
                <select id="to_state" name="to_state" class="w-full border rounded-md p-2" required>
                    <option value="">Selecciona estado</option>
                    @foreach ($states as $state)
                        @if ($state !== $ticket->state)
                            <option value="{{ $state }}" @selected(old('to_state') === $state)>{{ $state }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div>
                <label for="comment" class="block text-sm font-medium mb-1">Comentario</label>
                <textarea id="comment" name="comment" rows="3" class="w-full border rounded-md p-2">{{ old('comment') }}</textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-slate-900 text-white px-4 py-2 rounded-md">Actualizar estado</button>
            </div>
        </form>
    </section>

    <section class="bg-white border rounded-lg p-5">
        <h3 class="text-lg font-semibold mb-3">Historial de estados</h3>
        <div class="space-y-3">
            @forelse ($ticket->stateHistory as $entry)
                <article class="border rounded-md p-3 text-sm">
                    <p><strong>{{ $entry->from_state ?? 'N/A' }}</strong> → <strong>{{ $entry->to_state }}</strong></p>
                    <p class="text-slate-600">{{ $entry->comment }}</p>
                    <p class="text-xs text-slate-500 mt-1">{{ $entry->created_at?->format('d/m/Y H:i') }}</p>
                </article>
            @empty
                <p class="text-slate-600">Aún no hay historial registrado.</p>
            @endforelse
        </div>
    </section>
</div>
</body>
</html>
