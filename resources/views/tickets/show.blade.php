@extends('layouts.app')

@section('title', 'Detalle Ticket')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-slate-900">Detalle Ticket</h1>
                <p class="text-sm text-slate-600 mt-1">Revision completa de la incidencia y su historial.</p>
            </div>
            <a href="{{ route('tickets.index') }}" class="btn-secondary border border-slate-300 px-3 py-2 rounded-md">Volver</a>
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

        <section class="panel panel-pad bg-white border rounded-lg p-5 space-y-3">
            <h2 class="text-xl font-semibold">{{ $ticket->title }}</h2>
            <p class="text-slate-700">{{ $ticket->description }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-slate-700">
                <p><strong>Estado:</strong> {{ $ticket->state }}</p>
                <p><strong>Prioridad:</strong> {{ $ticket->priority }}</p>
                <p><strong>Ubicacion:</strong> {{ $ticket->location?->name ?? 'N/A' }}</p>
                <p><strong>Categoria:</strong> {{ $ticket->category?->name ?? 'N/A' }}</p>
                <p><strong>Reportado por:</strong> {{ $ticket->reporter?->name ?? $ticket->reporter?->email ?? 'N/A' }}</p>
                <p><strong>Creado:</strong> {{ $ticket->created_at?->format('d/m/Y H:i') }}</p>
            </div>
        </section>

        <section class="panel panel-pad bg-white border rounded-lg p-5 space-y-3">
            <h3 class="text-lg font-semibold">Actualizar estado</h3>
            <form method="POST" action="{{ route('tickets.update-state', $ticket) }}" class="space-y-3">
                @csrf
                @method('PATCH')

                <div>
                    <label for="to_state" class="block text-sm font-medium mb-1 text-slate-700">Nuevo estado</label>
                    <select id="to_state" name="to_state" class="field w-full border rounded-md p-2" required>
                        <option value="">Selecciona estado</option>
                        @foreach ($states as $state)
                            @if ($state !== $ticket->state)
                                <option value="{{ $state }}" @selected(old('to_state') === $state)>{{ $state }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="comment" class="block text-sm font-medium mb-1 text-slate-700">Comentario</label>
                    <textarea id="comment" name="comment" rows="3" class="field w-full border rounded-md p-2">{{ old('comment') }}</textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary bg-slate-900 text-white px-4 py-2 rounded-md">Actualizar estado</button>
                </div>
            </form>
        </section>

        <section class="panel panel-pad bg-white border rounded-lg p-5">
            <h3 class="text-lg font-semibold mb-3">Historial de estados</h3>
            <div class="space-y-3">
                @forelse ($ticket->stateHistory as $entry)
                    <article class="border border-slate-200 rounded-md p-3 text-sm">
                        <p><strong>{{ $entry->from_state ?? 'N/A' }}</strong> -> <strong>{{ $entry->to_state }}</strong></p>
                        <p class="text-slate-600">{{ $entry->comment }}</p>
                        <p class="text-xs text-slate-500 mt-1">{{ $entry->created_at?->format('d/m/Y H:i') }}</p>
                    </article>
                @empty
                    <p class="text-slate-600">Aun no hay historial registrado.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
