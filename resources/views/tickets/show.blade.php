@extends('layouts.app')

@section('title', 'Detalle Ticket')

@section('content')
    <div class="tickets-show-page">

        {{-- ===== HEADER ===== --}}
        <header class="tickets-show-header">
            <div>
                <h1 class="tickets-show-title">{{ $ticket->title }}</h1>
                <p class="tickets-show-subtitle">Revisión completa de la incidencia y su historial</p>
            </div>
            <div class="tickets-show-actions">
                <a href="{{ route('tickets.index') }}" class="btn-secondary">Volver</a>
                @can('delete', $ticket)
                    <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('¿Eliminar este ticket y sus adjuntos? Esta acción no se puede deshacer.');" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="tickets-btn-danger">Eliminar</button>
                    </form>
                @endcan
            </div>
        </header>

        {{-- ===== ALERTS ===== --}}
        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert-error">
                <p class="font-semibold mb-2">Errores en la actualización:</p>
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li class="text-sm">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ===== INFO PRINCIPAL ===== --}}
        <section class="tickets-show-section">
            <header class="tickets-show-section-header">
                <h2>Información del ticket</h2>
            </header>

            <div class="tickets-show-content">
                <div class="tickets-show-description">
                    <h3 class="tickets-show-description-title">Descripción</h3>
                    <p class="tickets-show-description-text">{{ $ticket->description }}</p>
                </div>

                <div class="tickets-show-grid">
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Estado</p>
                        <p class="tickets-show-meta-value">{{ $ticket->state }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Prioridad</p>
                        <p class="tickets-show-meta-value">{{ ucfirst($ticket->priority) }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Ubicación</p>
                        <p class="tickets-show-meta-value">{{ $ticket->location?->name ?? 'N/A' }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Categoría</p>
                        <p class="tickets-show-meta-value">{{ $ticket->category?->name ?? 'N/A' }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Reportado por</p>
                        <p class="tickets-show-meta-value">{{ $ticket->reporter?->name ?? $ticket->reporter?->email ?? 'N/A' }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Creado</p>
                        <p class="tickets-show-meta-value">{{ $ticket->created_at?->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== ADJUNTOS ===== --}}
        @if ($ticket->media->count() > 0)
            <section class="tickets-show-section">
                <header class="tickets-show-section-header">
                    <h2>Adjuntos ({{ $ticket->media->count() }})</h2>
                </header>

                <div class="tickets-media-grid">
                    @foreach ($ticket->media as $media)
                        <article class="tickets-media-item">
                            @if ($media->file_type === 'image')
                                <a href="{{ $media->file_url }}" target="_blank" rel="noopener noreferrer">
                                    <img src="{{ $media->file_url }}" alt="Adjunto" class="tickets-media-image">
                                </a>
                            @else
                                <div class="tickets-media-file">
                                    <p class="tickets-media-type">{{ strtoupper($media->file_type) }}</p>
                                </div>
                            @endif
                            <div class="tickets-media-info">
                                <p class="tickets-media-label">{{ $media->file_type }}</p>
                                <p class="tickets-media-date">{{ $media->created_at?->format('d/m/Y H:i') }}</p>
                                <a href="{{ $media->file_url }}" target="_blank" rel="noopener noreferrer" class="tickets-media-link">Ver archivo</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ===== ACTUALIZAR ESTADO ===== --}}
        <section class="tickets-show-section">
            <header class="tickets-show-section-header">
                <h2>Actualizar estado</h2>
            </header>

            <form method="POST" action="{{ route('tickets.update-state', $ticket) }}" class="tickets-update-form">
                @csrf
                @method('PATCH')

                <div class="tickets-form-group">
                    <label for="to_state" class="tickets-field-label">Nuevo estado *</label>
                    <select id="to_state" name="to_state" class="tickets-field" required>
                        <option value="">Selecciona estado</option>
                        @foreach ($states as $state)
                            @if ($state !== $ticket->state)
                                <option value="{{ $state }}" @selected(old('to_state') === $state)>{{ ucfirst(str_replace('_', ' ', $state)) }}</option>
                            @endif
                        @endforeach
                    </select>
                    @error('to_state')
                        <p class="tickets-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="tickets-form-group">
                    <label for="comment" class="tickets-field-label">Comentario</label>
                    <textarea id="comment" name="comment" rows="3"
                              placeholder="Explica brevemente por qué cambias el estado o qué acción se realizó"
                              class="tickets-field">{{ old('comment') }}</textarea>
                    @error('comment')
                        <p class="tickets-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="tickets-form-actions">
                    <button type="submit" class="btn-primary">Actualizar estado</button>
                </div>
            </form>
        </section>

        {{-- ===== HISTORIAL ===== --}}
        <section class="tickets-show-section">
            <header class="tickets-show-section-header">
                <h2>Historial de estados</h2>
            </header>

            <div class="tickets-history-list">
                @forelse ($ticket->stateHistory as $entry)
                    <article class="tickets-history-item">
                        <div class="tickets-history-transition">
                            <span class="tickets-history-badge">{{ ucfirst(str_replace('_', ' ', $entry->from_state ?? 'Inicio')) }}</span>
                            <x-lucide-arrow-right class="tickets-history-arrow" />
                            <span class="tickets-history-badge tickets-history-badge--target">{{ ucfirst(str_replace('_', ' ', $entry->to_state)) }}</span>
                        </div>
                        <p class="tickets-history-comment">{{ $entry->comment ?? '(sin comentario)' }}</p>
                        <p class="tickets-history-date">{{ $entry->created_at?->format('d/m/Y H:i') }}</p>
                    </article>
                @empty
                    <p class="tickets-history-empty">Aún no hay cambios de estado registrados</p>
                @endforelse
            </div>
        </section>

    </div>
@endsection
