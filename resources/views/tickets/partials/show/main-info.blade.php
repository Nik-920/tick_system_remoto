{{-- ① Información principal del ticket (con edición limitada para Maintenance) --}}
<section class="ticket-show__card" aria-labelledby="info-heading">
    <h2 id="info-heading" class="ticket-show__title">
        <span class="ticket-show__section-number" aria-hidden="true">1</span>
        Información principal del ticket
    </h2>

    @if ($vm->canEditOperational)
        {{-- Formulario de edición limitada: los controles distribuidos en esta
             tarjeta y en la sección de evidencias se vinculan vía form="..." --}}
        <form id="maintenance-edit-form"
              method="POST"
              action="{{ route('tickets.maintenance.update', $ticket) }}"
              enctype="multipart/form-data"
              class="tickets-once-form hidden" aria-hidden="true">
            @csrf
            @method('PATCH')
            <input type="hidden" name="idempotency_key" value="{{ $vm->generateIdempotencyKey() }}">
        </form>
    @endif

    <div class="mb-5">
        <p class="ticket-show__label mb-1.5">Descripción del problema</p>
        <div class="ticket-show__box min-h-[60px]">
            {{ $ticket->description ?? 'Sin descripción registrada.' }}
        </div>
    </div>

    {{-- Ubicación --}}
    <div class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-5 text-sm mb-5">
        <div>
            <p class="ticket-show__label">Ubicación</p>
            <div class="flex items-center gap-1 ticket-show__value">
                <svg class="w-3.5 h-3.5 ticket-show__faint flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <span class="truncate">{{ $ticket->location?->name ?? '—' }}</span>
            </div>
        </div>
        <div>
            <p class="ticket-show__label">Edificio</p>
            <p class="ticket-show__value">{{ $ticket->location?->building ?? '—' }}</p>
        </div>
        <div>
            <p class="ticket-show__label">Piso</p>
            <p class="ticket-show__value">{{ $ticket->location?->floor ?? '—' }}</p>
        </div>
        <div>
            <p class="ticket-show__label">Aula / Lab / Sala</p>
            <p class="ticket-show__value">{{ $ticket->location?->room_code ?? '—' }}</p>
        </div>
        <div>
            <p class="ticket-show__label">Código de ubicación</p>
            <p class="ticket-show__value font-mono text-xs">{{ $ticket->location?->room_code ?? '—' }}</p>
        </div>
    </div>

    {{-- Datos del ticket --}}
    <div class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-5 text-sm mb-5">
        <div>
            <p class="ticket-show__label">Categoría</p>
            @if ($vm->canEditOperational)
                <select id="edit-category" name="category_id" form="maintenance-edit-form"
                        class="ticket-show__control" aria-label="Corregir categoría">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}"
                            @selected((string) old('category_id', $ticket->category_id) === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')
                    <p class="ticket-show__error">{{ $message }}</p>
                @enderror
            @else
                <p class="ticket-show__value truncate">{{ $ticket->category?->name ?? '—' }}</p>
            @endif
        </div>
        <div>
            <p class="ticket-show__label">Prioridad</p>
            @if ($vm->canEditOperational)
                <select id="edit-priority" name="priority" form="maintenance-edit-form"
                        class="ticket-show__control" aria-label="Corregir prioridad">
                    @foreach ($priorities as $priorityOption)
                        <option value="{{ $priorityOption }}"
                            @selected(old('priority', $ticket->priority) === $priorityOption)>
                            {{ $vm->priorityLabels()[$priorityOption] ?? ucfirst($priorityOption) }}
                        </option>
                    @endforeach
                </select>
                @error('priority')
                    <p class="ticket-show__error">{{ $message }}</p>
                @enderror
            @else
                <span class="{{ $vm->priorityBadge() }}">
                    <span class="ts-badge__dot" aria-hidden="true"></span>
                    {{ $vm->priorityLabel() }}
                </span>
            @endif
        </div>
        <div>
            <p class="ticket-show__label">Estado</p>
            <span class="{{ $vm->stateBadge() }}">
                <span class="ts-badge__dot" aria-hidden="true"></span>
                {{ $vm->stateLabel() }}
            </span>
        </div>
        <div>
            <p class="ticket-show__label">Reportado por</p>
            <div class="flex items-center gap-1.5">
                <x-avatar :initials="$vm->initials($ticket->reporter?->name, 1, 'R')" tone="rose" class="w-5 h-5 text-[10px]" aria-hidden="true" />
                <span class="ticket-show__value truncate text-xs">{{ $ticket->reporter?->name ?? $ticket->reporter?->email ?? '—' }}</span>
            </div>
        </div>
        <div>
            <p class="ticket-show__label">Correo</p>
            <p class="ticket-show__value--soft ticket-show__value truncate text-xs">{{ $ticket->reporter?->email ?? '—' }}</p>
        </div>
    </div>

    <div class="text-sm">
        <p class="ticket-show__label">Fecha de creación</p>
        <p class="ticket-show__value">{{ $vm->fmtDate($ticket->created_at) ?? '—' }}</p>
    </div>

    @if ($vm->canEditOperational)
        <div class="mt-5 pt-4 ticket-show__divider space-y-3">
            <div class="ticket-show__edit-note" role="note">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                <span>Los datos originales del reporte no se modifican. Solo puedes corregir campos operativos como categoría y prioridad.</span>
            </div>

            <div>
                <label for="edit-comment" class="ticket-show__field-label">Comentario del cambio (opcional)</label>
                <textarea id="edit-comment" name="comment" rows="2" maxlength="2000" form="maintenance-edit-form"
                          placeholder="Explica brevemente la corrección realizada"
                          class="ticket-show__control">{{ old('comment') }}</textarea>
                @error('comment')
                    <p class="ticket-show__error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" form="maintenance-edit-form"
                        class="ticket-show__btn ticket-show__btn--primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                    Guardar cambios
                </button>
                <span class="ticket-show__hint">También guarda las evidencias seleccionadas abajo.</span>
            </div>
        </div>
    @endif
</section>
