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
                <a href="{{ route('tickets.index') }}" class="btn-secondary tickets-btn-back">Volver</a>
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

        @php
            $embedding      = $ticket?->embedding;
            $matchedTicket  = $embedding?->matchedTicket;
            $reviewer       = $embedding?->reviewer;

            // Use effective_duplicate (respects human review_status override)
            $showDuplicateWarning = $embedding
                && $embedding->effective_duplicate
                && $matchedTicket
                && in_array($matchedTicket->state, ['open', 'in_progress'], true);

            $reviewStatus   = $embedding?->review_status;
            $canReview      = $ticket && Auth::user()?->can('reviewDuplicate', $ticket);
        @endphp

        {{-- ===== DUPLICATE WARNING BANNER ===== --}}
        @if ($showDuplicateWarning)
            <div class="tickets-dup-alert">
                <div class="tickets-dup-banner">
                    <div class="tickets-dup-title">
                        @if ($reviewStatus === 'confirmed')
                            ✅ Duplicado confirmado manualmente.
                        @else
                            ⚠️ Posible duplicado detectado por IA.
                        @endif
                    </div>
                    <div class="tickets-dup-meta">
                        <span class="tickets-dup-meta-item">Ticket similar: {{ $matchedTicket?->title ?? 'N/A' }}</span>
                        <span class="tickets-dup-meta-item">Estado: {{ $matchedTicket?->state ?? 'N/A' }}</span>
                        @can('reviewDuplicate', $ticket)
                            <span class="tickets-dup-meta-item">Similitud: {{ $embedding?->similarity_score !== null ? number_format($embedding->similarity_score, 2) : 'N/A' }}</span>
                        @endcan
                        @if ($matchedTicket)
                            <a href="{{ route('tickets.show', $matchedTicket) }}" class="btn-secondary tickets-dup-link">Ver ticket</a>
                        @endif
                    </div>

                    {{-- Manual review info --}}
                    @if ($reviewer && $reviewStatus)
                        <div class="tickets-dup-reviewer">
                            Revisado por <strong>{{ $reviewer->name ?? $reviewer->email }}</strong>
                            el {{ $embedding->reviewed_at?->format('d/m/Y H:i') ?? 'N/A' }}.
                            @if ($embedding->review_note)
                                Nota: <em>{{ $embedding->review_note }}</em>
                            @endif
                        </div>
                    @endif

                    {{-- Review actions --}}
                    @can('reviewDuplicate', $ticket)
                        <form method="POST" action="{{ route('tickets.duplicate-review.update', $ticket) }}"
                              class="tickets-review-actions">
                            @csrf
                            @method('PATCH')
                                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <div class="tickets-review-note-wrap">
                                <label class="tickets-review-label" for="review_note">Nota de revisión</label>
                                <textarea id="review_note" name="review_note" rows="2" maxlength="1000"
                                          placeholder="Escribe una nota breve (opcional)"
                                          class="tickets-review-note">{{ $embedding?->review_note }}</textarea>
                            </div>
                            <div class="tickets-review-buttons">
                                <button type="submit" name="review_status" value="dismissed" class="btn-secondary tickets-review-btn tickets-review-btn--dismiss">
                                    🚫 Marcar como no duplicado
                                </button>
                                <button type="submit" name="review_status" value="confirmed" class="btn-primary tickets-review-btn tickets-review-btn--confirm">
                                    ✅ Confirmar duplicado
                                </button>
                            </div>
                        </form>
                        @error('review')
                            <p style="color:var(--color-danger); font-size:0.85rem;">{{ $message }}</p>
                        @enderror
                    @endcan
                </div>
            </div>
        @elseif ($embedding && $embedding->isDismissedDuplicate())
            {{-- Show dismissed badge for authorized users only --}}
            @can('reviewDuplicate', $ticket)
                <div class="alert-success tickets-dup-dismissed">
                    <span>🚫 Duplicado descartado manualmente.</span>
                    @if ($reviewer)
                        <span style="font-size:0.85rem; opacity:0.8;">
                            Por {{ $reviewer->name ?? $reviewer->email }}
                            el {{ $embedding->reviewed_at?->format('d/m/Y H:i') ?? 'N/A' }}.
                            @if ($embedding->review_note) — <em>{{ $embedding->review_note }}</em> @endif
                        </span>
                    @endif
                    {{-- Allow re-review --}}
                    <form method="POST" action="{{ route('tickets.duplicate-review.update', $ticket) }}"
                          class="tickets-review-actions">
                        @csrf
                        @method('PATCH')
                                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="review_note" value="{{ $embedding->review_note ?? '' }}">
                        <button type="submit" name="review_status" value="confirmed" class="c-btn c-btn--ghost c-btn--sm tickets-review-btn">
                            ↩ Reabrir como duplicado
                        </button>
                    </form>
                </div>
            @endcan
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

        @php
            $assignee = $ticket?->assignee;
            $assignedBy = $ticket?->assignedBy;
            $assignmentSource = $ticket?->assignment_source;
            $assignmentLocked = (bool) ($ticket?->assignment_locked ?? false);
            $assignedAt = $ticket?->assigned_at;

            $assigneeLabel = $assignee?->name ?? $assignee?->email ?? 'Sin asignar';
            $assignedByLabel = $assignedBy?->name ?? $assignedBy?->email ?? '—';

            if ($assignmentSource === \App\Models\Ticket::ASSIGNMENT_SOURCE_SELF) {
                $assignmentTypeLabel = 'Tomado por mantenimiento';
            } elseif ($assignmentSource === \App\Models\Ticket::ASSIGNMENT_SOURCE_ADMIN) {
                $assignmentTypeLabel = 'Asignación fija por administración';
            } else {
                $assignmentTypeLabel = 'Sin asignación';
            }

            $user = Auth::user();
            $isMaintenance = $user?->hasRole('maintenance') ?? false;
            $isAdmin = $user?->hasAnyRole(['admin', 'super_admin']) ?? false;

            $canClaim = $isMaintenance
                && $ticket?->state === \App\Models\Ticket::STATE_OPEN
                && $ticket?->assigned_to === null;
            $canRelease = $isMaintenance
                && $ticket?->state === \App\Models\Ticket::STATE_OPEN
                && $ticket?->assigned_to === $user?->id
                && ! $assignmentLocked
                && $assignmentSource === \App\Models\Ticket::ASSIGNMENT_SOURCE_SELF;
        @endphp

        {{-- ===== ASIGNACIÓN ===== --}}
        <section class="tickets-show-section ticket-assignment-panel">
            <header class="tickets-show-section-header">
                <h2>Asignación</h2>
            </header>

            <div class="tickets-show-content">
                <div class="tickets-show-grid">
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Asignado a</p>
                        <p class="tickets-show-meta-value">{{ $assigneeLabel }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Asignado por</p>
                        <p class="tickets-show-meta-value">{{ $assignedByLabel }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Fecha de asignación</p>
                        <p class="tickets-show-meta-value">{{ $assignedAt?->format('d/m/Y H:i') ?? '—' }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Tipo de asignación</p>
                        <p class="tickets-show-meta-value">{{ $assignmentTypeLabel }}</p>
                    </div>
                    <div class="tickets-show-meta">
                        <p class="tickets-show-meta-label">Estado de bloqueo</p>
                        <p class="tickets-show-meta-value">
                            @if ($assignmentLocked)
                                <span class="assignment-badge assignment-badge--locked">Fija</span>
                            @elseif ($ticket?->assigned_to)
                                <span class="assignment-badge assignment-badge--flexible">Flexible</span>
                            @else
                                <span class="assignment-badge assignment-badge--none">Sin asignación</span>
                            @endif
                        </p>
                    </div>
                </div>

                @if ($isMaintenance)
                    <div class="assignment-actions">
                        @if ($canClaim)
                            <form method="POST" action="{{ route('tickets.claim', $ticket) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <button type="submit" class="btn-primary">Tomar este ticket</button>
                            </form>
                        @endif

                        @if ($canRelease)
                            <form method="POST" action="{{ route('tickets.release', $ticket) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <button type="submit" class="btn-secondary">Liberar ticket</button>
                            </form>
                        @endif

                        @if ($assignmentLocked && $ticket?->assigned_to === $user?->id)
                            <p class="tickets-show-meta-value" style="font-size:0.85rem; opacity:0.8;">
                                Asignación fija por administración. Solo Admin o SuperAdmin puede cambiarla.
                            </p>
                        @endif
                    </div>
                @endif

                @if ($isAdmin)
                    <div class="assignment-actions">
                        <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="assignment-actions">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <div class="tickets-form-group" style="min-width:220px;">
                                <label for="assigned_to" class="tickets-field-label">Asignar a</label>
                                <select id="assigned_to" name="assigned_to" class="tickets-field" required>
                                    <option value="">Selecciona maintenance</option>
                                    @foreach (($maintenanceUsers ?? collect()) as $maintenanceUser)
                                        <option value="{{ $maintenanceUser->id }}" @selected($ticket?->assigned_to === $maintenanceUser->id)>
                                            {{ $maintenanceUser->name ?? $maintenanceUser->email ?? $maintenanceUser->id }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('assigned_to')
                                    <p class="tickets-field-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit" class="btn-primary">
                                {{ $ticket?->assigned_to ? 'Reasignar' : 'Asignar' }}
                            </button>
                        </form>

                        @if ($ticket?->assigned_to)
                            <form method="POST" action="{{ route('tickets.unassign', $ticket) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <button type="submit" class="btn-secondary">Desasignar</button>
                            </form>
                        @endif
                    </div>
                @endif

                @error('assignment')
                    <p class="tickets-field-error">{{ $message }}</p>
                @enderror
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
        @can('updateState', $ticket)
        <section class="tickets-show-section">
            <header class="tickets-show-section-header">
                <h2>Actualizar estado</h2>
            </header>

            <form method="POST" action="{{ route('tickets.update-state', $ticket) }}" class="tickets-update-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

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
        @endcan

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
                            <span class="tickets-history-badge tickets-history-badge--target">{{ ucfirst(str_replace('_', ' ', $entry->to_state ?? '')) }}</span>
                        </div>
                        @if ($entry->changedBy)
                            <p class="tickets-history-actor">Cambiado por: <strong>{{ $entry->changedBy->name ?? $entry->changedBy->email }}</strong></p>
                        @endif
                        <p class="tickets-history-comment">{{ $entry->comment ?? '(sin comentario)' }}</p>
                        <p class="tickets-history-date">{{ $entry->created_at?->format('d/m/Y H:i') ?? 'N/A' }}</p>
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

document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('.tickets-update-form, .tickets-review-actions');
    forms.forEach(function (form) {
        form.addEventListener('submit', function () {
            const buttons = form.querySelectorAll('button[type="submit"]');
            buttons.forEach(function (button) {
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            });
        }, { once: true });
    });
});
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
