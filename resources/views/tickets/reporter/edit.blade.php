@extends('layouts.app')

@section('title', 'Editar ticket')

@section('content')
@php
    /** @var \App\Models\Ticket $ticket */
    $priorityLabels = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Crítica'];
    $currentPriority = old('priority', (string) $ticket->priority);
    $ref = '#'.strtoupper(substr((string) $ticket->id, 0, 8));
@endphp

    <div class="tickets-create-page">

        {{-- ===== HEADER ===== --}}
        <header class="tickets-create-header">
            <div>
                <h1 class="tickets-create-title">Editar ticket</h1>
                <p class="tickets-create-subtitle">Actualiza los datos de tu solicitud antes de que mantenimiento la tome.</p>
            </div>
            <a href="{{ route('reporter.tickets.show', $ticket->id) }}" class="btn-secondary">Volver al seguimiento</a>
        </header>

        {{-- ===== ALERTS ===== --}}
        @if ($errors->any())
            <div class="alert-error">
                <p class="font-semibold mb-2">Por favor corrige los siguientes errores:</p>
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li class="text-sm">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ===== FORM ===== --}}
        <div class="tickets-create-layout">
            <div class="tickets-create-main">
                <form method="POST" action="{{ route('reporter.tickets.update', $ticket->id) }}" class="tickets-create-form">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                    <section class="tickets-form-section" aria-labelledby="ticket-section-details">
                        <header class="tickets-form-section-header">
                            <h2 id="ticket-section-details" class="tickets-form-section-title">Describe la incidencia</h2>
                            <p class="tickets-form-section-subtitle">Corrige el título o la descripción si algo quedó incompleto.</p>
                        </header>
                        <div class="tickets-form-section-body">
                            <div class="tickets-form-group">
                                <label for="title" class="tickets-field-label">Título *</label>
                                <p id="title-hint" class="tickets-field-hint">Resume el problema en una frase clara.</p>
                                <input id="title" type="text" name="title" value="{{ old('title', $ticket->title) }}" required
                                       placeholder="Ej: Proyector del laboratorio A-201 no enciende"
                                       class="tickets-field" aria-describedby="title-hint">
                                @error('title')
                                    <p class="tickets-field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="tickets-form-group">
                                <label for="description" class="tickets-field-label">Descripción *</label>
                                <p id="description-hint" class="tickets-field-hint">
                                    Incluye qué ocurrió, cuándo ocurrió, dónde ocurrió y si afecta a más personas.
                                </p>
                                <textarea id="description" name="description" rows="6" required
                                          placeholder="Describe la incidencia con detalle."
                                          class="tickets-field" aria-describedby="description-hint">{{ old('description', $ticket->description) }}</textarea>
                                @error('description')
                                    <p class="tickets-field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>

                    <section class="tickets-form-section" aria-labelledby="ticket-section-classify">
                        <header class="tickets-form-section-header">
                            <h2 id="ticket-section-classify" class="tickets-form-section-title">Clasifica el reporte</h2>
                            <p class="tickets-form-section-subtitle">Ajusta la ubicación, categoría y prioridad si es necesario.</p>
                        </header>
                        <div class="tickets-form-section-body">
                            <div class="tickets-form-grid">
                                <div class="tickets-form-group">
                                    <label for="location_id" class="tickets-field-label">Ubicación *</label>
                                    <p id="location-hint" class="tickets-field-hint">Selecciona el espacio donde ocurre la incidencia.</p>
                                    <select id="location_id" name="location_id" required class="tickets-field" aria-describedby="location-hint">
                                        <option value="">Selecciona una ubicación</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}" @selected(old('location_id', (string) $ticket->location_id) === (string) $location->id)>
                                                {{ $location->name }} ({{ $location->room_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('location_id')
                                        <p class="tickets-field-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="tickets-form-group">
                                    <label for="category_id" class="tickets-field-label">Categoría *</label>
                                    <p id="category-hint" class="tickets-field-hint">Elige el tipo de incidencia que mejor describe el caso.</p>
                                    <select id="category_id" name="category_id" required class="tickets-field" aria-describedby="category-hint">
                                        <option value="">Selecciona una categoría</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected(old('category_id', (string) $ticket->category_id) === (string) $category->id)>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <p class="tickets-field-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="tickets-form-group">
                                <label class="tickets-field-label">Prioridad *</label>
                                <p id="priority-hint" class="tickets-field-hint">Selecciona la urgencia con la que debe atenderse.</p>
                                <div class="tickets-priority-grid" role="radiogroup" aria-describedby="priority-hint">
                                    @foreach (['low' => 'No impide trabajar.', 'medium' => 'Afecta parcialmente el uso.', 'high' => 'Bloquea una actividad importante.', 'critical' => 'Riesgo o servicio detenido.'] as $value => $desc)
                                        <label class="tickets-priority-option" for="priority_{{ $value }}">
                                            <input id="priority_{{ $value }}" class="tickets-priority-input" type="radio" name="priority" value="{{ $value }}"
                                                   data-label="{{ $priorityLabels[$value] }}" @checked($currentPriority === $value)>
                                            <span class="tickets-priority-card">
                                                <span class="tickets-priority-label">{{ $priorityLabels[$value] }}</span>
                                                <span class="tickets-priority-desc">{{ $desc }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('priority')
                                    <p class="tickets-field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>

                    <section class="tickets-form-section" aria-labelledby="ticket-section-actions">
                        <header class="tickets-form-section-header">
                            <h2 id="ticket-section-actions" class="tickets-form-section-title">Confirmación</h2>
                            <p class="tickets-form-section-subtitle">Revisa los cambios antes de guardarlos.</p>
                        </header>
                        <div class="tickets-form-section-body">
                            <div class="tickets-form-actions">
                                <button type="submit" class="btn-primary">Guardar cambios</button>
                                <a href="{{ route('reporter.tickets.show', $ticket->id) }}" class="btn-secondary">Cancelar y volver</a>
                            </div>
                        </div>
                    </section>
                </form>
            </div>

            <aside class="tickets-create-aside">
                <section class="tickets-help-panel tickets-summary-panel">
                    <h3 class="tickets-help-title">Resumen del ticket</h3>
                    <dl class="tickets-summary-list">
                        <div class="tickets-summary-item">
                            <dt>Referencia</dt>
                            <dd>{{ $ref }}</dd>
                        </div>
                        <div class="tickets-summary-item">
                            <dt>Estado</dt>
                            <dd>Abierto</dd>
                        </div>
                        <div class="tickets-summary-item">
                            <dt>Ubicación</dt>
                            <dd>{{ $ticket->location?->name ?? 'Sin ubicación' }}</dd>
                        </div>
                        <div class="tickets-summary-item">
                            <dt>Categoría</dt>
                            <dd>{{ $ticket->category?->name ?? 'Sin categoría' }}</dd>
                        </div>
                        <div class="tickets-summary-item">
                            <dt>Prioridad</dt>
                            <dd>{{ $priorityLabels[(string) $ticket->priority] ?? ucfirst((string) $ticket->priority) }}</dd>
                        </div>
                        <div class="tickets-summary-item">
                            <dt>Creado</dt>
                            <dd>{{ $ticket->created_at?->translatedFormat('d M Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="tickets-help-panel">
                    <h3 class="tickets-help-title">Antes de guardar</h3>
                    <ul class="tickets-help-list">
                        <li>Solo puedes editar mientras el ticket está abierto.</li>
                        <li>Si mantenimiento ya lo tomó, no podrás modificarlo.</li>
                        <li>El estado y la asignación los gestiona mantenimiento.</li>
                    </ul>
                </section>
            </aside>
        </div>

    </div>
@endsection
