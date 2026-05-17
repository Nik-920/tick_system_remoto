@extends('layouts.app')

@section('title', 'Detalle Ticket')

@section('content')
    <div class="tickets-show-page">

        {{-- ===== HEADER ===== --}}
        <header class="tickets-show-header">
            <div>
                <h1 class="tickets-show-title">{{ $ticket?->title ?? 'Sin título' }}</h1>
                <p class="tickets-show-subtitle">Revisión completa de la incidencia y su historial</p>
            </div>
            <div class="tickets-show-actions">
                <a href="{{ route('tickets.index') }}" class="btn-secondary">Volver</a>
                @if($ticket && Auth::user()?->can('delete', $ticket))
                    <form id="delete-ticket-form" method="POST" action="{{ route('tickets.destroy', $ticket) }}" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="tickets-btn-danger" onclick="openDeleteTicketModal()">Eliminar</button>
                    </form>
                @endif
            </div>
        </header>

        {{-- ===== ALERTS ===== --}}
        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        @if (isset($errors) && $errors->any())
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
                    <p class="tickets-show-description-text">{{ $ticket?->description ?? 'Sin descripción' }}</p>
                </div>

                <div class="tickets-show-grid">
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Estado</p>
                        <p class="tickets-show-meta-value">{{ $ticket?->state ?? 'N/A' }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Prioridad</p>
                        <p class="tickets-show-meta-value">{{ ucfirst($ticket?->priority ?? 'N/A') }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Ubicación</p>
                        <p class="tickets-show-meta-value">{{ $ticket?->location?->name ?? 'N/A' }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Categoría</p>
                        <p class="tickets-show-meta-value">{{ $ticket?->category?->name ?? 'N/A' }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Reportado por</p>
                        <p class="tickets-show-meta-value">{{ $ticket?->reporter?->name ?? $ticket?->reporter?->email ?? 'N/A' }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Creado</p>
                        <p class="tickets-show-meta-value">{{ $ticket?->created_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ===== ADJUNTOS ===== --}}
        @if (optional($ticket?->media)->isNotEmpty())
            <section class="tickets-show-section">
                <header class="tickets-show-section-header">
                    <h2>Adjuntos ({{ $ticket->media->count() }})</h2>
                </header>

                <div class="tickets-media-grid">
                    @foreach ($ticket->media as $media)
                        <article class="tickets-media-item">
                            @if ($media?->file_type === 'image')
                                <a href="{{ $media?->file_url }}" target="_blank" rel="noopener noreferrer">
                                    <img src="{{ $media?->file_url }}" alt="Adjunto" class="tickets-media-image">
                                </a>
                            @else
                                <div class="tickets-media-file">
                                    <p class="tickets-media-type">{{ strtoupper($media?->file_type ?? 'FILE') }}</p>
                                </div>
                            @endif
                            <div class="tickets-media-info">
                                <p class="tickets-media-label">{{ $media?->file_type ?? 'Desconocido' }}</p>
                                <p class="tickets-media-date">{{ $media?->created_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                                <a href="{{ $media?->file_url }}" target="_blank" rel="noopener noreferrer" class="tickets-media-link">Ver archivo</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ===== ACTUALIZAR ESTADO ===== --}}
        @if ($ticket)
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
                        @foreach ($states ?? [] as $state)
                            @if ($state !== $ticket?->state)
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
        @endif

        {{-- ===== HISTORIAL ===== --}}
        <section class="tickets-show-section">
            <header class="tickets-show-section-header">
                <h2>Historial de estados</h2>
            </header>

            <div class="tickets-history-list">
                @forelse (optional($ticket?->stateHistory) as $entry)
                    <article class="tickets-history-item">
                        <div class="tickets-history-transition">
                            <span class="tickets-history-badge">{{ ucfirst(str_replace('_', ' ', $entry?->from_state ?? 'Inicio')) }}</span>
                            <x-lucide-arrow-right class="tickets-history-arrow" />
                            <span class="tickets-history-badge tickets-history-badge--target">{{ ucfirst(str_replace('_', ' ', $entry?->to_state ?? '')) }}</span>
                        </div>
                        <p class="tickets-history-comment">{{ $entry?->comment ?? '(sin comentario)' }}</p>
                        <p class="tickets-history-date">{{ $entry?->created_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
                    </article>
                @empty
                    <p class="tickets-history-empty">Aún no hay cambios de estado registrados</p>
                @endforelse
            </div>
        </section>

    </div>

<script>
// Lógica para el modal premium
function openDeleteTicketModal() {
    const modal = document.getElementById('deleteTicketModal');
    const modalContent = modal.querySelector('.relative');
    
    // Mostrar contenedor
    modal.classList.remove('hidden');
    
    // Forzar reflow para aplicar la transición
    void modal.offsetWidth;
    
    // Animar entrada
    modal.classList.remove('opacity-0');
    modal.classList.add('opacity-100');
    modalContent.classList.remove('scale-95', 'translate-y-4');
    modalContent.classList.add('scale-100', 'translate-y-0');
}

function closeDeleteTicketModal() {
    const modal = document.getElementById('deleteTicketModal');
    const modalContent = modal.querySelector('.relative');
    
    // Animar salida
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    modalContent.classList.remove('scale-100', 'translate-y-0');
    modalContent.classList.add('scale-95', 'translate-y-4');
    
    // Ocultar completamente después de la transición
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}
</script>

{{-- Custom Delete Modal Overlay --}}
<div id="deleteTicketModal" class="fixed inset-0 z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300" style="backdrop-filter: blur(5px);">
    <!-- Backdrop oscuro -->
    <button type="button" class="absolute inset-0 w-full h-full border-0 p-0 m-0 cursor-default" style="background-color: rgba(15, 23, 42, 0.55);" onclick="closeDeleteTicketModal()" aria-label="Cerrar modal" tabindex="-1"></button>

    <!-- Contenido del Modal -->
    <div class="relative w-full max-w-sm rounded-2xl p-6 transform scale-95 translate-y-4 transition-all duration-300 shadow-2xl" style="background-color: var(--bg-surface); border: 1px solid var(--border-default);">
        
        <!-- Icono centrado -->
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full mb-4" style="background-color: rgba(225, 29, 72, 0.12);">
            <x-lucide-alert-triangle width="28" height="28" style="color: #e11d48;" stroke-width="2.5" />
        </div>
        
        <!-- Título y descripción -->
        <div class="text-center mb-6">
            <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">¿Eliminar este ticket?</h3>
            <p class="text-sm" style="color: var(--text-muted); line-height: 1.5;">Esta acción no se puede deshacer. Se eliminará el ticket junto con todos sus adjuntos.</p>
        </div>
        
        <!-- Botones de Acción -->
        <div class="flex gap-3 justify-center mt-2">
            <button type="button" class="btn-secondary flex-1 text-center justify-center" onclick="closeDeleteTicketModal()">
                Cancelar
            </button>
            <button type="button" class="btn-danger flex-1 text-center justify-center" onclick="document.getElementById('delete-ticket-form').submit();">
                Sí, eliminar
            </button>
        </div>
    </div>
</div>
@endsection
