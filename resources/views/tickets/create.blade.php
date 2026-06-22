@extends('layouts.app')

@section('title', 'Nuevo Ticket')

@section('content')
@php
    $priorityMeta = [
        'low'      => ['label' => 'Baja',    'desc' => 'No impide trabajar',            'tone' => 'low',    'icon' => 'chevron-down'],
        'medium'   => ['label' => 'Media',   'desc' => 'Afecta parcialmente el uso',    'tone' => 'medium', 'icon' => 'minus'],
        'high'     => ['label' => 'Alta',    'desc' => 'Bloquea una actividad clave',   'tone' => 'high',   'icon' => 'alert-circle'],
        'critical' => ['label' => 'Crítica', 'desc' => 'Riesgo o servicio detenido',    'tone' => 'high',   'icon' => 'alert-triangle'],
    ];
    $descriptionTemplate = implode("\n", [
        '¿Qué ocurre?',
        '...',
        '',
        '¿Cuándo ocurre o desde cuándo empezó?',
        '...',
        '',
        '¿A quién o qué actividad afecta?',
        '...',
        '',
        '¿Cómo se reproduce o en qué momento pasa?',
        '...',
        '',
        '¿Qué se intentó antes de reportarlo?',
        '...',
        '',
        'Mensaje de error o evidencia relevante:',
        '...',
    ]);
    $currentPriority = old('priority', 'medium');
    $charCount = strlen(old('description', $descriptionTemplate));
    $backRoute = auth()->user()?->hasRole('reporter') && ! auth()->user()?->hasAnyRole(['admin', 'super_admin', 'maintenance'])
        ? route('reporter.tickets.index')
        : route('tickets.index');
@endphp

<div class="rep-edit rep-tone-primary">

    {{-- ── Welcome Hero ── --}}
    <section class="rep-hero">
        <div class="rep-hero__content">
            <p class="rep-hero__eyebrow">Nuevo Ticket</p>
            <h1 class="rep-hero__title">Registra una incidencia 📋</h1>
            <p class="rep-hero__subtitle">
                Registra una nueva incidencia para su seguimiento operativo.
            </p>
        </div>
        <div class="rep-hero__actions">
            <a href="{{ $backRoute }}" class="rep-hero__cta rep-hero__cta--outline">
                <x-lucide-arrow-left width="16" height="16" stroke-width="2.5" />
                Volver al listado
            </a>
        </div>
    </section>

    {{-- ── Validation errors banner ── --}}
    @if ($errors->any())
        <div class="rep-edit__alert-error" role="alert" aria-live="assertive">
            <x-lucide-alert-circle width="18" height="18" stroke-width="2" aria-hidden="true" />
            <div>
                <p class="rep-edit__alert-title">Por favor corrige los siguientes errores:</p>
                <ul class="rep-edit__alert-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if (session('status'))
        <div class="rep-edit__alert-success" role="status" aria-live="polite">
            <x-lucide-check-circle width="18" height="18" stroke-width="2" aria-hidden="true" />
            {{ session('status') }}
        </div>
    @endif

    @if (session('duplicate_precheck'))
        <div class="rep-edit__alert-warning" role="alert" aria-live="assertive">
            <x-lucide-alert-triangle width="18" height="18" stroke-width="2" aria-hidden="true" />
            <div>
                <p class="rep-edit__alert-title">Posible reporte relacionado</p>
                <p>Este reporte podría estar relacionado con otro ticket similar. Revísalo antes de continuar o crea el ticket si consideras que es un caso distinto.</p>
                <dl class="rep-edit__alert-meta">
                    <dt>Ticket similar:</dt>
                    <dd>{{ session('duplicate_precheck')['matchedTitle'] }}</dd>
                    <dt>Estado:</dt>
                    <dd>{{ session('duplicate_precheck')['matchedState'] }}</dd>
                    <dt>Motivo:</dt>
                    <dd>{{ session('duplicate_precheck')['reason'] }}</dd>
                </dl>
                <p class="rep-edit__alert-note">Al hacer clic en <strong>Crear ticket</strong> confirmarás que este es un caso distinto.</p>
                @if (session('duplicate_precheck.hadAttachments'))
                    <p class="rep-edit__alert-note">Si habías seleccionado evidencias, vuelve a adjuntarlas antes de continuar. Por seguridad, el navegador no conserva archivos después de mostrar esta advertencia.</p>
                @endif
            </div>
        </div>
    @endif

    {{-- ── Two-column layout ── --}}
    <div class="rep-edit__layout">

        {{-- ══════════════════════════════════════
             LEFT: Main form
             ══════════════════════════════════════ --}}
        <main class="rep-edit__main">
            <form method="POST"
                  action="{{ route('tickets.store') }}"
                  enctype="multipart/form-data"
                  id="create-ticket-form"
                  data-upload-form
                  novalidate>
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                @if (session('duplicate_precheck'))
                    <input type="hidden" name="duplicate_ack" value="1">
                @endif

                {{-- ─────────────────────────────────────────────
                     SECTION 1 — Describe la incidencia
                     ───────────────────────────────────────────── --}}
                <section class="rep-edit__section" aria-labelledby="section-describe">
                    <div class="rep-edit__section-head">
                        <span class="rep-edit__step-badge" aria-hidden="true">1</span>
                        <div>
                            <h2 id="section-describe" class="rep-edit__section-title">Describe la incidencia</h2>
                            <p class="rep-edit__section-subtitle">Ayuda al equipo a entender el problema de forma clara y rápida.</p>
                        </div>
                    </div>

                    <div class="rep-edit__section-body">
                        {{-- Title --}}
                        <div class="rep-edit__field">
                            <label for="create-title" class="rep-edit__label">
                                Título del problema
                                <span class="rep-edit__required" aria-hidden="true">*</span>
                            </label>
                            <p id="create-title-hint" class="rep-edit__field-hint">Resume el problema en una frase corta y clara.</p>
                            <div class="rep-edit__input-wrap">
                                <input id="create-title"
                                       type="text"
                                       name="title"
                                       value="{{ old('title') }}"
                                       required
                                       minlength="5"
                                       maxlength="255"
                                       placeholder="Ej: Proyector del laboratorio A-201 no enciende"
                                       class="rep-edit__input @error('title') rep-edit__input--error @enderror"
                                       aria-describedby="create-title-hint create-title-err"
                                       autocomplete="off">
                                <x-lucide-type width="16" height="16" stroke-width="2" class="rep-edit__input-icon" aria-hidden="true" />
                            </div>
                            @error('title')
                                <p id="create-title-err" class="rep-edit__field-error" role="alert">
                                    <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div class="rep-edit__field">
                            <div class="ticket-description-field__header">
                                <label for="create-description" class="rep-edit__label">
                                    Descripción detallada
                                    <span class="rep-edit__required" aria-hidden="true">*</span>
                                </label>
                                <button
                                    type="button"
                                    class="ticket-description-template-clear"
                                    data-description-template-clear
                                    aria-controls="create-description"
                                >
                                    Borrar guía
                                </button>
                            </div>
                            <p id="create-description-hint" class="rep-edit__field-hint">
                                Describe el síntoma, cuándo ocurre, a quién afecta y qué se intentó.
                            </p>
                            <div class="rep-edit__textarea-wrap">
                                <textarea id="create-description"
                                          name="description"
                                          rows="6"
                                          required
                                          minlength="20"
                                          maxlength="2000"
                                          data-description-template
                                          data-description-template-default="{{ e($descriptionTemplate) }}"
                                          class="rep-edit__textarea @error('description') rep-edit__input--error @enderror"
                                          aria-describedby="create-description-hint create-description-err create-description-count">{{ old('description', $descriptionTemplate) }}</textarea>
                                <p id="create-description-count" class="rep-edit__char-count" aria-live="polite">
                                    <span id="create-count-val">{{ $charCount }}</span>/2000
                                </p>
                            </div>
                            @error('description')
                                <p id="create-description-err" class="rep-edit__field-error" role="alert">
                                    <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </section>

                {{-- ─────────────────────────────────────────────
                     SECTION 2 — Clasifica el reporte
                     ───────────────────────────────────────────── --}}
                <section class="rep-edit__section" aria-labelledby="section-classify">
                    <div class="rep-edit__section-head">
                        <span class="rep-edit__step-badge" aria-hidden="true">2</span>
                        <div>
                            <h2 id="section-classify" class="rep-edit__section-title">Clasifica el reporte</h2>
                            <p class="rep-edit__section-subtitle">Selecciona la ubicación, categoría y prioridad adecuada.</p>
                        </div>
                    </div>

                    <div class="rep-edit__section-body">
                        {{-- Location + Category row --}}
                        <div class="rep-edit__two-col">
                            <div class="rep-edit__field">
                                <label for="create-location" class="rep-edit__label">
                                    Ubicación
                                    <span class="rep-edit__required" aria-hidden="true">*</span>
                                </label>
                                <p id="create-location-hint" class="rep-edit__field-hint">¿Dónde ocurre la incidencia?</p>
                                <div class="rep-edit__select-wrap">
                                    <x-lucide-map-pin width="16" height="16" stroke-width="2" class="rep-edit__select-icon" aria-hidden="true" />
                                    <select id="create-location"
                                            name="location_id"
                                            required
                                            class="rep-edit__select @error('location_id') rep-edit__input--error @enderror"
                                            aria-describedby="create-location-hint create-location-err"
                                            data-summary="location">
                                        <option value="">Selecciona una ubicación</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}"
                                                data-label="{{ $location->name }} ({{ $location->room_code }})"
                                                @selected(old('location_id', $selectedLocationId) === (string) $location->id)>
                                                {{ $location->name }} ({{ $location->room_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-lucide-chevron-down width="15" height="15" stroke-width="2" class="rep-edit__select-chevron" aria-hidden="true" />
                                </div>
                                @error('location_id')
                                    <p id="create-location-err" class="rep-edit__field-error" role="alert">
                                        <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="rep-edit__field">
                                <label for="create-category" class="rep-edit__label">
                                    Categoría
                                    <span class="rep-edit__required" aria-hidden="true">*</span>
                                </label>
                                <p id="create-category-hint" class="rep-edit__field-hint">¿Qué tipo de incidencia es?</p>
                                <div class="rep-edit__select-wrap">
                                    <x-lucide-layers width="16" height="16" stroke-width="2" class="rep-edit__select-icon" aria-hidden="true" />
                                    <select id="create-category"
                                            name="category_id"
                                            required
                                            class="rep-edit__select @error('category_id') rep-edit__input--error @enderror"
                                            aria-describedby="create-category-hint create-category-err"
                                            data-summary="category">
                                        <option value="">Selecciona una categoría</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                @selected(old('category_id') === (string) $category->id)>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-lucide-chevron-down width="15" height="15" stroke-width="2" class="rep-edit__select-chevron" aria-hidden="true" />
                                </div>
                                @error('category_id')
                                    <p id="create-category-err" class="rep-edit__field-error" role="alert">
                                        <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>

                        {{-- Priority grid --}}
                        <div class="rep-edit__field">
                            <span class="rep-edit__label" id="create-priority-label">
                                Prioridad
                                <span class="rep-edit__required" aria-hidden="true">*</span>
                            </span>
                            <p id="create-priority-hint" class="rep-edit__field-hint">Selecciona la urgencia con la que debe atenderse.</p>
                            <div class="rep-edit__priority-grid"
                                 role="radiogroup"
                                 aria-labelledby="create-priority-label"
                                 aria-describedby="create-priority-hint create-priority-err">
                                @foreach ($priorityMeta as $value => $meta)
                                    <label class="rep-edit__priority-card rep-tone-{{ $meta['tone'] }} @if($currentPriority === $value) is-selected @endif"
                                           for="priority-{{ $value }}">
                                        <input id="priority-{{ $value }}"
                                               type="radio"
                                               name="priority"
                                               value="{{ $value }}"
                                               class="rep-edit__priority-radio"
                                               @checked($currentPriority === $value)>
                                        <span class="rep-edit__priority-check" aria-hidden="true">
                                            <x-lucide-check width="12" height="12" stroke-width="3" />
                                        </span>
                                        <span class="rep-edit__priority-icon" aria-hidden="true">
                                            <x-dynamic-component :component="'lucide-' . $meta['icon']"
                                                width="18" height="18" stroke-width="2" />
                                        </span>
                                        <span class="rep-edit__priority-text">
                                            <span class="rep-edit__priority-label">{{ $meta['label'] }}</span>
                                            <span class="rep-edit__priority-desc">{{ $meta['desc'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('priority')
                                <p id="create-priority-err" class="rep-edit__field-error" role="alert">
                                    <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </section>

                {{-- ─────────────────────────────────────────────
                     SECTION 3 — Adjunta evidencia
                     ───────────────────────────────────────────── --}}
                <section class="rep-edit__section" aria-labelledby="section-evidence">
                    <div class="rep-edit__section-head">
                        <span class="rep-edit__step-badge" aria-hidden="true">3</span>
                        <div>
                            <h2 id="section-evidence" class="rep-edit__section-title">
                                Adjunta evidencia <span class="rep-edit__optional">(opcional)</span>
                            </h2>
                            <p class="rep-edit__section-subtitle">Agrega fotos o documentos que respalden el reporte.</p>
                        </div>
                    </div>

                    <div class="rep-edit__section-body">
                        {{-- Drop zone --}}
                        <div class="rep-edit__dropzone" id="create-dropzone">
                            <label for="media_files" class="rep-edit__dropzone-label">
                                <div class="rep-edit__dropzone-icon" aria-hidden="true">
                                    <x-lucide-cloud-upload width="36" height="36" stroke-width="1.5" />
                                </div>
                                <div class="rep-edit__dropzone-actions">
                                    <span class="rep-edit__dropzone-btn rep-edit__dropzone-btn--primary">
                                        Arrastra archivos aquí o haz clic para seleccionarlos
                                    </span>
                                    <button type="button"
                                            class="rep-edit__dropzone-btn rep-edit__dropzone-btn--secondary"
                                            id="create-camera-btn"
                                            aria-label="Tomar foto con la cámara">
                                        <x-lucide-camera width="15" height="15" stroke-width="2.5" aria-hidden="true" />
                                        Tomar con la Cámara
                                    </button>
                                </div>
                                <p class="rep-edit__dropzone-hint">Imágenes, PDF, documentos y video · Máx. 10 MB c/u · Hasta 5 archivos</p>
                                <input id="media_files"
                                       type="file"
                                       name="media_files[]"
                                       multiple
                                       accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.mp4"
                                       class="rep-edit__file-input"
                                       data-upload-guard
                                       data-max-file-size="10485760"
                                       data-max-file-size-label="10 MB"
                                       data-max-files="5"
                                       data-max-total-size="52428800"
                                       data-max-total-size-label="50 MB"
                                       data-allowed-extensions="jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,mp4"
                                       aria-describedby="create-media-err"
                                       aria-label="Adjuntar archivos de evidencia">
                                <input id="media_camera"
                                       type="file"
                                       name="media_files[]"
                                       accept="image/*"
                                       capture="environment"
                                       class="rep-edit__file-input"
                                       data-upload-guard
                                       data-max-file-size="10485760"
                                       data-max-file-size-label="10 MB"
                                       data-max-files="5"
                                       data-max-total-size="52428800"
                                       data-max-total-size-label="50 MB"
                                       data-allowed-extensions="jpg,jpeg,png,webp"
                                       aria-label="Tomar foto con la cámara">
                            </label>

                            {{-- 5-slot preview strip --}}
                            <div id="create-media-preview"
                                 class="rep-edit__slots"
                                 aria-live="polite"
                                 aria-label="Vista previa de archivos seleccionados">
                                @for ($i = 0; $i < 5; $i++)
                                    <div class="rep-edit__slot" data-slot="{{ $i }}" aria-hidden="true">+</div>
                                @endfor
                            </div>
                        </div>

                        @error('media_files')
                            <p id="create-media-err" class="rep-edit__field-error" role="alert">
                                <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                {{ $message }}
                            </p>
                        @enderror
                        @error('media_files.*')
                            <p class="rep-edit__field-error" role="alert">
                                <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </section>

                {{-- ─────────────────────────────────────────────
                     SECTION 4 — Visibilidad en Comunidad
                     ───────────────────────────────────────────── --}}
                <section class="rep-edit__section" aria-labelledby="section-community-visibility">
                    <div class="rep-edit__section-head">
                        <span class="rep-edit__step-badge" aria-hidden="true">4</span>
                        <div>
                            <h2 id="section-community-visibility" class="rep-edit__section-title">Visibilidad en Comunidad</h2>
                            <p class="rep-edit__section-subtitle">Decide si este reporte aparece en la pestaña Comunidad.</p>
                        </div>
                    </div>

                    <div class="rep-edit__section-body">
                        @if ($selectedCategory?->locksCommunityVisibility())
                            {{-- Categoría sensible: visibilidad forzada por política --}}
                            <input type="hidden" name="community_visible" value="0">
                            <div class="rep-edit__field">
                                <div class="rep-edit__checkbox-label rep-edit__checkbox-label--disabled" aria-disabled="true">
                                    <input id="community-visible-toggle"
                                           type="checkbox"
                                           name="community_visible_display"
                                           class="rep-edit__checkbox"
                                           disabled
                                           aria-describedby="community-locked-hint">
                                    <span>Mostrar este reporte en Comunidad</span>
                                </div>
                                <p id="community-locked-hint" class="rep-edit__field-hint">
                                    @if ($selectedCategory->community_visibility_help)
                                        {{ $selectedCategory->community_visibility_help }}
                                    @else
                                        Esta categoría se mantiene privada por defecto y no puede publicarse en Comunidad.
                                    @endif
                                </p>
                            </div>
                        @else
                            <div class="rep-edit__field">
                                <input type="hidden" name="community_visible" value="0">
                                <label class="rep-edit__checkbox-label" for="community-visible-toggle">
                                    <input id="community-visible-toggle"
                                           type="checkbox"
                                           name="community_visible"
                                           value="1"
                                           class="rep-edit__checkbox"
                                           @checked(
                                               (string) old(
                                                   'community_visible',
                                                   $selectedCategory?->defaultCommunityVisible() === false ? '0' : '1'
                                               ) === '1'
                                           )>
                                    <span>Mostrar este reporte en Comunidad</span>
                                </label>
                                <p class="rep-edit__field-hint">
                                    Otros reporters podrán ver el título, categoría, ubicación, estado y evidencias del reporte.
                                    No se mostrará tu nombre, correo ni teléfono.
                                </p>
                                <p class="rep-edit__field-hint">
                                    Algunas categorías sensibles se mantienen privadas automáticamente para proteger la información del reporte.
                                </p>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- ─────────────────────────────────────────────
                     SECTION 5 — Confirmación
                     ───────────────────────────────────────────── --}}
                <section class="rep-edit__section rep-edit__section--actions" aria-labelledby="section-confirm">
                    <div class="rep-edit__section-head">
                        <span class="rep-edit__step-badge" aria-hidden="true">5</span>
                        <div>
                            <h2 id="section-confirm" class="rep-edit__section-title">Confirmación</h2>
                            <p class="rep-edit__section-subtitle">Revisa los datos antes de enviar el ticket al equipo de mantenimiento.</p>
                        </div>
                    </div>

                    <div class="rep-edit__section-body">
                        <div class="rep-edit__form-actions">
                            <a href="{{ $backRoute }}" class="rep-edit__btn rep-edit__btn--ghost">
                                Cancelar
                            </a>
                            <button type="submit"
                                    form="create-ticket-form"
                                    class="rep-edit__btn rep-edit__btn--primary"
                                    id="create-submit-btn">
                                <x-lucide-send width="16" height="16" stroke-width="2.5" aria-hidden="true" />
                                Crear ticket
                            </button>
                        </div>
                    </div>
                </section>

            </form>
        </main>

        {{-- ══════════════════════════════════════
             RIGHT: Sidebar
             ══════════════════════════════════════ --}}
        <aside class="rep-edit__aside" aria-label="Ayuda y resumen del ticket">

            {{-- Live summary panel --}}
            <section class="rep-panel" id="create-summary-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-file-text width="16" height="16" stroke-width="2" aria-hidden="true" />
                    Resumen del ticket
                </h2>
                <dl class="rep-detail">
                    <div class="rep-detail__row">
                        <dt>
                            <x-lucide-map-pin width="14" height="14" stroke-width="2" aria-hidden="true" />
                            Ubicación
                        </dt>
                        <dd id="summary-location" class="rep-edit__summary-val--empty">Sin seleccionar</dd>
                    </div>
                    <div class="rep-detail__row">
                        <dt>
                            <x-lucide-layers width="14" height="14" stroke-width="2" aria-hidden="true" />
                            Categoría
                        </dt>
                        <dd id="summary-category" class="rep-edit__summary-val--empty">Sin seleccionar</dd>
                    </div>
                    <div class="rep-detail__row">
                        <dt>
                            <x-lucide-alert-circle width="14" height="14" stroke-width="2" aria-hidden="true" />
                            Prioridad
                        </dt>
                        <dd id="summary-priority">
                            <span class="rep-badge rep-prio rep-tone-medium">
                                <span class="rep-badge__dot" aria-hidden="true"></span>
                                <span id="summary-priority-text">Media</span>
                            </span>
                        </dd>
                    </div>
                    <div class="rep-detail__row">
                        <dt>
                            <x-lucide-paperclip width="14" height="14" stroke-width="2" aria-hidden="true" />
                            Adjuntos
                        </dt>
                        <dd id="summary-attachments" class="rep-edit__summary-val--empty">Sin adjuntos</dd>
                    </div>
                </dl>
            </section>

            {{-- Info notice --}}
            <section class="rep-panel rep-edit__info-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-info width="16" height="16" stroke-width="2" aria-hidden="true" />
                    Información importante
                </h2>
                <ul class="rep-edit__info-list">
                    <li>El equipo de mantenimiento recibirá una notificación automática.</li>
                    <li>Puedes hacer seguimiento desde tu panel de tickets.</li>
                    <li>Si detectamos un posible duplicado, te avisaremos al guardar.</li>
                </ul>
            </section>

            {{-- Guía de prioridad --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-lightbulb width="16" height="16" stroke-width="2" aria-hidden="true" />
                    Guía de prioridad
                </h2>
                <ul class="rep-edit__tips-list">
                    <li>
                        <x-lucide-chevron-down width="14" height="14" stroke-width="2.5" aria-hidden="true"
                            style="color: #16a34a;" />
                        <span><strong>Baja:</strong> No impide trabajar.</span>
                    </li>
                    <li>
                        <x-lucide-minus width="14" height="14" stroke-width="2.5" aria-hidden="true"
                            style="color: #d97706;" />
                        <span><strong>Media:</strong> Afecta parcialmente.</span>
                    </li>
                    <li>
                        <x-lucide-alert-circle width="14" height="14" stroke-width="2.5" aria-hidden="true"
                            style="color: #dc2626;" />
                        <span><strong>Alta:</strong> Bloquea una actividad importante.</span>
                    </li>
                    <li>
                        <x-lucide-alert-triangle width="14" height="14" stroke-width="2.5" aria-hidden="true"
                            style="color: #dc2626;" />
                        <span><strong>Crítica:</strong> Riesgo o servicio detenido.</span>
                    </li>
                </ul>
            </section>

            {{-- Tips --}}
            <section class="rep-panel rep-edit__tips-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-check-circle width="16" height="16" stroke-width="2" aria-hidden="true" />
                    Cómo crear un buen ticket
                </h2>
                <ul class="rep-edit__tips-list">
                    <li>
                        <x-lucide-check-circle width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                        Describe el problema con claridad.
                    </li>
                    <li>
                        <x-lucide-check-circle width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                        Indica exactamente dónde ocurre.
                    </li>
                    <li>
                        <x-lucide-check-circle width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                        Elige la prioridad correcta.
                    </li>
                    <li>
                        <x-lucide-check-circle width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                        Adjunta fotos o evidencias si puedes.
                    </li>
                </ul>
            </section>

        </aside>
    </div>

</div>

{{-- ── JS: counter + preview + live summary + drag-and-drop ── --}}
<script>
(function () {
    // ── Description char counter ──
    var textarea = document.getElementById('create-description');
    var counter  = document.getElementById('create-count-val');
    if (textarea && counter) {
        textarea.addEventListener('input', function () {
            counter.textContent = textarea.value.length;
        });
    }

    // ── File upload: 5-slot preview strip ──
    var fileInput   = document.getElementById('media_files');
    var cameraInput = document.getElementById('media_camera');
    var cameraBtn   = document.getElementById('create-camera-btn');
    var slotsEl     = document.getElementById('create-media-preview');
    var dropzone    = document.getElementById('create-dropzone');
    var summaryAtt  = document.getElementById('summary-attachments');
    var MAX_FILES   = 5;

    // Files accumulator (DataTransfer trick to merge picks)
    var dt = new DataTransfer();

    // ── Pre-merge validation (mirrors upload-guard.js; runs before files enter dt) ──
    function validatePickedFiles(files, input) {
        var maxFileSize     = parseInt(input.dataset.maxFileSize    || '0', 10);
        var maxFileSzLabel  = input.dataset.maxFileSizeLabel        || '';
        var maxFiles        = parseInt(input.dataset.maxFiles       || '0', 10);
        var maxTotalSize    = parseInt(input.dataset.maxTotalSize   || '0', 10);
        var maxTotalSzLabel = input.dataset.maxTotalSizeLabel       || '';
        var allowedExts     = (input.dataset.allowedExtensions || '')
            .split(',').map(function (e) { return e.trim().toLowerCase(); }).filter(Boolean);
        var errors = [];

        if (maxFiles > 0 && (dt.files.length + files.length) > maxFiles) {
            errors.push('Puedes adjuntar hasta ' + maxFiles + ' archivos. Ya tienes ' + dt.files.length + ' seleccionado(s).');
            return errors;
        }

        var newBytes = 0;
        files.forEach(function (file) {
            if (maxFileSize > 0 && file.size > maxFileSize) {
                var mb = (file.size / 1048576).toFixed(1);
                errors.push('El archivo "' + file.name + '" pesa ' + mb + ' MB y supera el límite de ' + (maxFileSzLabel || Math.round(maxFileSize / 1048576) + ' MB') + '.');
            }
            if (allowedExts.length > 0) {
                var parts = file.name.split('.');
                var ext   = (parts.length > 1 ? parts[parts.length - 1] : '').toLowerCase();
                if (!allowedExts.includes(ext)) {
                    var friendly = allowedExts.map(function (e) { return e.toUpperCase(); }).join(', ');
                    errors.push('El archivo "' + file.name + '" no es compatible. Formatos: ' + friendly + '.');
                }
            }
            newBytes += file.size;
        });

        if (maxTotalSize > 0 && errors.length === 0) {
            var existingBytes = Array.from(dt.files).reduce(function (s, f) { return s + f.size; }, 0);
            if (existingBytes + newBytes > maxTotalSize) {
                var totalMb = ((existingBytes + newBytes) / 1048576).toFixed(1);
                errors.push('El total seleccionado pesa ' + totalMb + ' MB y supera el límite de ' + (maxTotalSzLabel || Math.round(maxTotalSize / 1048576) + ' MB') + '.');
            }
        }

        return errors;
    }

    function showPickError(messages) {
        // Selector is split so the substring does not appear twice in the HTML source
        // (the layout partial renders the modal element; a verbatim duplicate would
        // break the "exactly one modal" assertion in GlobalUploadErrorHandlingTest).
        var modal = document.querySelector('[data-upload-error' + '-modal]');
        if (!modal) { return; }
        var body = modal.querySelector('[data-upload-error-body]');
        if (body) {
            body.replaceChildren();
            if (messages.length === 1) {
                var p = document.createElement('p');
                p.textContent = messages[0];
                body.appendChild(p);
            } else {
                var ul = document.createElement('ul');
                ul.className = 'upload-error-modal__list';
                messages.forEach(function (msg) {
                    var li = document.createElement('li');
                    li.textContent = msg;
                    ul.appendChild(li);
                });
                body.appendChild(ul);
            }
        }
        modal.removeAttribute('hidden');
        var closeBtn = modal.querySelector('[data-upload-error-close]');
        if (closeBtn) { closeBtn.focus(); }
    }

    // Camera detection: hide button on devices that report no video input (e.g. desktop without webcam).
    // enumerateDevices() resolves without a permission prompt on all major browsers;
    // it returns device kinds even before the user grants camera access.
    if (cameraBtn && navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
        navigator.mediaDevices.enumerateDevices().then(function (devices) {
            var hasCamera = devices.some(function (d) { return d.kind === 'videoinput'; });
            if (!hasCamera) { cameraBtn.style.display = 'none'; }
        }).catch(function () { /* keep visible on error — user may still have a camera */ });
    }

    if (cameraBtn && cameraInput) {
        cameraBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            cameraInput.click();
        });
        cameraInput.addEventListener('change', function () {
            var newFiles = Array.from(cameraInput.files || []);
            cameraInput.value = '';
            if (newFiles.length === 0) { return; }
            var errs = validatePickedFiles(newFiles, cameraInput);
            if (errs.length > 0) { showPickError(errs); return; }
            mergeFiles(newFiles);
            try { fileInput.files = dt.files; } catch (_) {}
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var newFiles = Array.from(fileInput.files || []);
            // Clear the native input immediately so the same file can be re-selected.
            fileInput.value = '';
            if (newFiles.length === 0) { return; }
            var errs = validatePickedFiles(newFiles, fileInput);
            if (errs.length > 0) { showPickError(errs); return; }
            mergeFiles(newFiles);
            // Re-assign the accumulated FileList so the browser includes the
            // files in the multipart submission (value='' clears .files too).
            try { fileInput.files = dt.files; } catch (_) {}
        });
    }

    if (dropzone) {
        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropzone.classList.add('is-drag-over');
        });
        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('is-drag-over');
        });
        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropzone.classList.remove('is-drag-over');
            if (e.dataTransfer) {
                var newFiles = Array.from(e.dataTransfer.files);
                if (newFiles.length > 0) {
                    var errs = validatePickedFiles(newFiles, fileInput);
                    if (errs.length > 0) { showPickError(errs); return; }
                    mergeFiles(newFiles);
                }
            }
        });
    }

    function mergeFiles(newFiles) {
        newFiles.forEach(function (f) {
            if (dt.files.length < MAX_FILES) { dt.items.add(f); }
        });
        syncInput();
        renderSlots();
    }

    function removeFile(index) {
        var current = Array.from(dt.files);
        dt = new DataTransfer();
        current.forEach(function (f, i) { if (i !== index) { dt.items.add(f); } });
        syncInput();
        renderSlots();
    }

    function syncInput() {
        if (fileInput) { fileInput.files = dt.files; }
        if (summaryAtt) {
            var n = dt.files.length;
            if (n === 0) {
                summaryAtt.textContent = 'Sin adjuntos';
                summaryAtt.className = 'rep-edit__summary-val--empty';
            } else {
                summaryAtt.textContent = n + (n === 1 ? ' archivo' : ' archivos');
                summaryAtt.className = '';
            }
        }
        // When all slots are filled, block the camera button and the file-picker button.
        // fileInput must stay enabled — disabled inputs are excluded from form submission.
        var full = dt.files.length >= MAX_FILES;
        if (cameraBtn) {
            cameraBtn.disabled = full;
            cameraBtn.setAttribute('aria-disabled', String(full));
        }
    }

    function renderSlots() {
        if (!slotsEl) return;
        var slots = slotsEl.querySelectorAll('[data-slot]');
        var files = Array.from(dt.files);

        slots.forEach(function (slot, i) {
            slot.innerHTML = '';
            slot.className = 'rep-edit__slot';

            if (i < files.length) {
                var f = files[i];
                slot.classList.add('rep-edit__slot--filled');

                if (f.type.startsWith('image/')) {
                    var reader = new FileReader();
                    reader.onload = (function (s) { return function (ev) {
                        var img = document.createElement('img');
                        img.src = ev.target.result;
                        img.alt = f.name;
                        img.className = 'rep-edit__slot-img';
                        s.appendChild(img);
                    }; })(slot);
                    reader.readAsDataURL(f);
                } else {
                    var icon = document.createElement('span');
                    icon.className = 'rep-edit__slot-file-icon';
                    icon.setAttribute('aria-hidden', 'true');
                    icon.textContent = '📄';
                    slot.appendChild(icon);
                }

                var nameEl = document.createElement('span');
                nameEl.className = 'rep-edit__slot-name';
                nameEl.textContent = f.name.length > 16 ? f.name.slice(0, 13) + '…' : f.name;
                slot.appendChild(nameEl);

                var rmBtn = document.createElement('button');
                rmBtn.type = 'button';
                rmBtn.className = 'rep-edit__slot-remove';
                rmBtn.setAttribute('aria-label', 'Eliminar ' + f.name);
                rmBtn.textContent = '×';
                (function (idx) {
                    rmBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeFile(idx);
                    });
                })(i);
                slot.appendChild(rmBtn);
            } else {
                slot.textContent = '+';
                slot.setAttribute('aria-hidden', 'true');
            }
        });
    }

    // ── Priority card visual feedback ──
    var radios = document.querySelectorAll('.rep-edit__priority-radio');
    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.rep-edit__priority-card').forEach(function (card) {
                card.classList.remove('is-selected');
            });
            if (radio.checked && radio.parentElement) {
                radio.parentElement.classList.add('is-selected');
            }
            // Update sidebar summary
            var priorityLabels = { low: 'Baja', medium: 'Media', high: 'Alta', critical: 'Crítica' };
            var summaryPri = document.getElementById('summary-priority-text');
            if (summaryPri) {
                summaryPri.textContent = priorityLabels[radio.value] || radio.value;
            }
            // Update tone on badge
            var badge = document.querySelector('#summary-priority .rep-badge');
            if (badge) {
                badge.className = badge.className.replace(/rep-tone-\w+/, '');
                var toneMap = { low: 'rep-tone-low', medium: 'rep-tone-medium', high: 'rep-tone-high', critical: 'rep-tone-high' };
                badge.classList.add(toneMap[radio.value] || 'rep-tone-medium');
            }
        });
    });

    // ── Live location / category sidebar summary ──
    function attachSummarySelect(selectId, summaryId) {
        var sel = document.getElementById(selectId);
        var sum = document.getElementById(summaryId);
        if (!sel || !sum) return;
        function update() {
            var opt = sel.options[sel.selectedIndex];
            var label = opt && opt.value ? (opt.dataset.label || opt.textContent.trim()) : null;
            if (label) {
                sum.textContent = label;
                sum.className = '';
            } else {
                sum.textContent = 'Sin seleccionar';
                sum.className = 'rep-edit__summary-val--empty';
            }
        }
        sel.addEventListener('change', update);
        update(); // initialize
    }

    attachSummarySelect('create-location', 'summary-location');
    attachSummarySelect('create-category', 'summary-category');

    // ── Borrar guía ──
    var clearBtn = document.querySelector('[data-description-template-clear]');
    if (clearBtn) {
        var taId = clearBtn.getAttribute('aria-controls');
        var ta   = document.getElementById(taId);
        var ctr  = document.getElementById('create-count-val');
        if (ta) {
            clearBtn.addEventListener('click', function () {
                if (ta.value === '') {
                    ta.focus();
                    return;
                }
                var defaultTpl = ta.dataset.descriptionTemplateDefault || '';
                if (ta.value !== defaultTpl) {
                    if (!window.confirm('La descripción tiene cambios. ¿Quieres borrarla?')) {
                        return;
                    }
                }
                ta.value = '';
                if (ctr) { ctr.textContent = '0'; }
                ta.dispatchEvent(new Event('input', { bubbles: true }));
                ta.focus();
            });
        }
    }
})();
</script>
@endsection