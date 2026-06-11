@extends('layouts.app')

@section('title', 'Nuevo Ticket')

@section('content')
    <div class="tickets-create-page">

        {{-- ===== HEADER ===== --}}
        <header class="tickets-create-header">
            <div>
                <h1 class="tickets-create-title">Crear Ticket</h1>
                <p class="tickets-create-subtitle">Registra una nueva incidencia para su seguimiento operativo.</p>
            </div>
            <a href="{{ route('tickets.index') }}" class="btn-secondary">Volver al listado</a>
        </header>

        {{-- ===== ALERTS ===== --}}
        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

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
                <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="tickets-create-form">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                    <section class="tickets-form-section" aria-labelledby="ticket-section-details">
                        <header class="tickets-form-section-header">
                            <h2 id="ticket-section-details" class="tickets-form-section-title">Describe la incidencia</h2>
                            <p class="tickets-form-section-subtitle">Ayuda al equipo a entender el problema de forma clara y rápida.</p>
                        </header>
                        <div class="tickets-form-section-body">
                            <div class="tickets-form-group">
                                <label for="title" class="tickets-field-label">Título *</label>
                                <p id="title-hint" class="tickets-field-hint">Resume el problema en una frase clara.</p>
                                <input id="title" type="text" name="title" value="{{ old('title') }}" required
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
                                          class="tickets-field" aria-describedby="description-hint">{{ old('description') }}</textarea>
                                @error('description')
                                    <p class="tickets-field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>

                    <section class="tickets-form-section" aria-labelledby="ticket-section-classify">
                        <header class="tickets-form-section-header">
                            <h2 id="ticket-section-classify" class="tickets-form-section-title">Clasifica el reporte</h2>
                            <p class="tickets-form-section-subtitle">Selecciona la ubicación, categoría y prioridad adecuada.</p>
                        </header>
                        <div class="tickets-form-section-body">
                            <div class="tickets-form-grid">
                                <div class="tickets-form-group">
                                    <label for="location_id" class="tickets-field-label">Ubicación *</label>
                                    <p id="location-hint" class="tickets-field-hint">Selecciona el espacio donde ocurre la incidencia.</p>
                                    <select id="location_id" name="location_id" required class="tickets-field" aria-describedby="location-hint">
                                        <option value="">Selecciona una ubicación</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}" @selected(old('location_id', $selectedLocationId) === $location->id)>
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
                                            <option value="{{ $category->id }}" @selected(old('category_id') === $category->id)>
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
                                <label class="tickets-field-label">Prioridad</label>
                                <p id="priority-hint" class="tickets-field-hint">Selecciona la urgencia con la que debe atenderse.</p>
                                <div class="tickets-priority-grid" role="radiogroup" aria-describedby="priority-hint">
                                    <label class="tickets-priority-option" for="priority_low">
                                        <input id="priority_low" class="tickets-priority-input" type="radio" name="priority" value="low"
                                               data-label="Baja" @checked(old('priority', 'medium') === 'low')>
                                        <span class="tickets-priority-card">
                                            <span class="tickets-priority-label">Baja</span>
                                            <span class="tickets-priority-desc">No impide trabajar.</span>
                                        </span>
                                    </label>
                                    <label class="tickets-priority-option" for="priority_medium">
                                        <input id="priority_medium" class="tickets-priority-input" type="radio" name="priority" value="medium"
                                               data-label="Media" @checked(old('priority', 'medium') === 'medium')>
                                        <span class="tickets-priority-card">
                                            <span class="tickets-priority-label">Media</span>
                                            <span class="tickets-priority-desc">Afecta parcialmente el uso.</span>
                                        </span>
                                    </label>
                                    <label class="tickets-priority-option" for="priority_high">
                                        <input id="priority_high" class="tickets-priority-input" type="radio" name="priority" value="high"
                                               data-label="Alta" @checked(old('priority', 'medium') === 'high')>
                                        <span class="tickets-priority-card">
                                            <span class="tickets-priority-label">Alta</span>
                                            <span class="tickets-priority-desc">Bloquea una actividad importante.</span>
                                        </span>
                                    </label>
                                    <label class="tickets-priority-option" for="priority_critical">
                                        <input id="priority_critical" class="tickets-priority-input" type="radio" name="priority" value="critical"
                                               data-label="Crítica" @checked(old('priority', 'medium') === 'critical')>
                                        <span class="tickets-priority-card">
                                            <span class="tickets-priority-label">Crítica</span>
                                            <span class="tickets-priority-desc">Riesgo o servicio detenido.</span>
                                        </span>
                                    </label>
                                </div>
                                @error('priority')
                                    <p class="tickets-field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>

                    <section class="tickets-form-section" aria-labelledby="ticket-section-attachments">
                        <header class="tickets-form-section-header">
                            <h2 id="ticket-section-attachments" class="tickets-form-section-title">Adjunta evidencia</h2>
                            <p class="tickets-form-section-subtitle">Agrega fotos o documentos que respalden el reporte.</p>
                        </header>
                        <div class="tickets-form-section-body">
                            <div class="tickets-form-group">
                                <label for="media_files" class="tickets-field-label">Adjuntos (opcional)</label>
                                <div class="tickets-upload">
                                    <input id="media_files" type="file" name="media_files[]" multiple
                                           accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.mp4,.avi,.mov"
                                           class="tickets-upload-input" aria-describedby="media-hint">
                                    <label for="media_files" class="tickets-upload-box">
                                        <span class="tickets-upload-title">Arrastra archivos aquí o haz clic para seleccionarlos</span>
                                        <span id="media-hint" class="tickets-upload-subtitle">Máximo 5 archivos de 10 MB cada uno</span>
                                        <span class="tickets-upload-subtitle">Formatos: imágenes, PDF, documentos, video</span>
                                    </label>
                                    <div class="tickets-upload-list" id="ticketsUploadList">
                                        <p class="tickets-upload-empty">No hay archivos seleccionados.</p>
                                    </div>
                                </div>
                                @error('media_files')
                                    <p class="tickets-field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </section>

                    <section class="tickets-form-section" aria-labelledby="ticket-section-actions">
                        <header class="tickets-form-section-header">
                            <h2 id="ticket-section-actions" class="tickets-form-section-title">Confirmación</h2>
                            <p class="tickets-form-section-subtitle">Revisa los datos antes de enviar el ticket.</p>
                        </header>
                        <div class="tickets-form-section-body">
                            <div class="tickets-form-actions">
                                <button type="submit" class="btn-primary">Guardar ticket</button>
                                <a href="{{ route('tickets.index') }}" class="btn-secondary">Cancelar</a>
                            </div>
                        </div>
                    </section>
                </form>
            </div>

            <aside class="tickets-create-aside">
                <section class="tickets-help-panel">
                    <h3 class="tickets-help-title">Cómo crear un buen ticket</h3>
                    <ul class="tickets-help-list">
                        <li>Describe el problema claramente.</li>
                        <li>Indica dónde ocurre.</li>
                        <li>Selecciona la prioridad correcta.</li>
                        <li>Adjunta evidencia si es posible.</li>
                    </ul>
                </section>

                <section class="tickets-help-panel">
                    <h3 class="tickets-help-title">Guía de prioridad</h3>
                    <ul class="tickets-help-list tickets-help-list--compact">
                        <li><strong>Baja:</strong> No impide trabajar.</li>
                        <li><strong>Media:</strong> Afecta parcialmente.</li>
                        <li><strong>Alta:</strong> Bloquea una actividad importante.</li>
                        <li><strong>Crítica:</strong> Riesgo o servicio detenido.</li>
                    </ul>
                </section>

                <section class="tickets-help-panel tickets-summary-panel">
                    <h3 class="tickets-help-title">Resumen del ticket</h3>
                    <dl class="tickets-summary-list">
                        <div class="tickets-summary-item">
                            <dt>Ubicación</dt>
                            <dd data-summary="location">Sin seleccionar</dd>
                        </div>
                        <div class="tickets-summary-item">
                            <dt>Categoría</dt>
                            <dd data-summary="category">Sin seleccionar</dd>
                        </div>
                        <div class="tickets-summary-item">
                            <dt>Prioridad</dt>
                            <dd data-summary="priority">Media</dd>
                        </div>
                        <div class="tickets-summary-item">
                            <dt>Adjuntos</dt>
                            <dd data-summary="attachments">Sin adjuntos</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>

    </div>
@endsection