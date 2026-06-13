@extends('layouts.app')

@section('title', 'Editar ticket')

@section('content')
@php
    /** @var \App\Models\Ticket $ticket */
    use App\Models\Ticket;

    $priorityMeta = [
        'low'      => ['label' => 'Baja',    'desc' => 'No impide trabajar',      'tone' => 'low',    'icon' => 'chevron-down'],
        'medium'   => ['label' => 'Media',   'desc' => 'Afecta parcialmente',     'tone' => 'medium', 'icon' => 'minus'],
        'high'     => ['label' => 'Alta',    'desc' => 'Bloquea actividades',     'tone' => 'high',   'icon' => 'alert-circle'],
        'critical' => ['label' => 'Crítica', 'desc' => 'Riesgo o servicio detenido', 'tone' => 'high', 'icon' => 'alert-triangle'],
    ];

    $currentPriority = old('priority', (string) $ticket->priority);

    // Reference number: #TIC-YYYY-XXXX from UUID prefix
    $uuidParts = explode('-', (string) $ticket->id);
    $ref       = '#TIC-' . strtoupper(($uuidParts[4] ?? substr((string) $ticket->id, -4)));
    $ref       = '#TIC-' . date('Y') . '-' . strtoupper(substr((string) $ticket->id, 0, 4));

    $existingMedia = $ticket->media ?? collect();

    $charCount = strlen(old('description', (string) $ticket->description));
@endphp

{{-- ============================================================
     MODULE: rep-edit — Editar Ticket (Reporter)
     Scoped under .rep-edit. Mirrors the design tokens and BEM
     conventions of reporter-tickets.css / reporter-ticket-show.css
     ============================================================ --}}

<div class="rep-edit rep-tone-primary">

    {{-- ── Back link ── --}}
    <a href="{{ route('reporter.tickets.index') }}" class="rep-show__back">
        <x-lucide-arrow-left width="16" height="16" stroke-width="2.5" aria-hidden="true" />
        Volver a mis tickets
    </a>

    {{-- ── Page header ── --}}
    <div class="rep-edit__page-head">
        <div class="rep-edit__page-head-text">
            <h1 class="rep-edit__page-title">Editar ticket</h1>
            <p class="rep-edit__page-subtitle">Actualiza la información de tu incidencia antes de que mantenimiento la tome.</p>
        </div>
        <a href="{{ route('reporter.tickets.show', $ticket->id) }}" class="rep-btn-outline">
            Ver seguimiento
            <x-lucide-arrow-right width="15" height="15" stroke-width="2.5" aria-hidden="true" />
        </a>
    </div>

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

    {{-- ── Two-column layout ── --}}
    <div class="rep-edit__layout">

        {{-- ══════════════════════════════════════
             LEFT: Main form
             ══════════════════════════════════════ --}}
        <main class="rep-edit__main">
            <form method="POST"
                  action="{{ route('reporter.tickets.update', $ticket->id) }}"
                  enctype="multipart/form-data"
                  id="edit-ticket-form"
                  novalidate>
                @csrf
                @method('PATCH')
                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                {{-- ─────────────────────────────────────────────
                     SECTION 1 — Describe la incidencia
                     ───────────────────────────────────────────── --}}
                <section class="rep-edit__section" aria-labelledby="section-describe">
                    <div class="rep-edit__section-head">
                        <span class="rep-edit__step-badge" aria-hidden="true">1</span>
                        <div>
                            <h2 id="section-describe" class="rep-edit__section-title">Describe la incidencia</h2>
                            <p class="rep-edit__section-subtitle">Actualiza el título o la descripción si algo quedó incompleto.</p>
                        </div>
                    </div>

                    <div class="rep-edit__section-body rep-edit__two-col">
                        {{-- Title --}}
                        <div class="rep-edit__field">
                            <label for="edit-title" class="rep-edit__label">
                                Título del problema
                                <span class="rep-edit__required" aria-hidden="true">*</span>
                            </label>
                            <div class="rep-edit__input-wrap">
                                <input id="edit-title"
                                       type="text"
                                       name="title"
                                       value="{{ old('title', $ticket->title) }}"
                                       required
                                       minlength="5"
                                       maxlength="255"
                                       placeholder="Ej: Mesa rota en sala A-202"
                                       class="rep-edit__input @error('title') rep-edit__input--error @enderror"
                                       aria-describedby="edit-title-err"
                                       autocomplete="off">
                                <x-lucide-type width="16" height="16" stroke-width="2" class="rep-edit__input-icon" aria-hidden="true" />
                            </div>
                            @error('title')
                                <p id="edit-title-err" class="rep-edit__field-error" role="alert">
                                    <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div class="rep-edit__field rep-edit__field--wide">
                            <label for="edit-description" class="rep-edit__label">
                                Descripción detallada
                                <span class="rep-edit__required" aria-hidden="true">*</span>
                            </label>
                            <div class="rep-edit__textarea-wrap">
                                <textarea id="edit-description"
                                          name="description"
                                          rows="5"
                                          required
                                          minlength="20"
                                          maxlength="1000"
                                          placeholder="Describe qué ocurrió, dónde, cuándo y si afecta a más personas."
                                          class="rep-edit__textarea @error('description') rep-edit__input--error @enderror"
                                          aria-describedby="edit-description-err edit-description-count">{{ old('description', $ticket->description) }}</textarea>
                                <p id="edit-description-count" class="rep-edit__char-count" aria-live="polite">
                                    <span id="description-count-val">{{ $charCount }}</span>/1000
                                </p>
                            </div>
                            @error('description')
                                <p id="edit-description-err" class="rep-edit__field-error" role="alert">
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
                            <p class="rep-edit__section-subtitle">Ajusta la ubicación, categoría y prioridad si es necesario.</p>
                        </div>
                    </div>

                    <div class="rep-edit__section-body">
                        {{-- Location + Category row --}}
                        <div class="rep-edit__two-col">
                            <div class="rep-edit__field">
                                <label for="edit-location" class="rep-edit__label">
                                    Ubicación
                                    <span class="rep-edit__required" aria-hidden="true">*</span>
                                </label>
                                <div class="rep-edit__select-wrap">
                                    <x-lucide-map-pin width="16" height="16" stroke-width="2" class="rep-edit__select-icon" aria-hidden="true" />
                                    <select id="edit-location"
                                            name="location_id"
                                            required
                                            class="rep-edit__select @error('location_id') rep-edit__input--error @enderror"
                                            aria-describedby="edit-location-err">
                                        <option value="">Selecciona una ubicación</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}"
                                                @selected(old('location_id', (string) $ticket->location_id) === (string) $location->id)>
                                                {{ $location->name }} ({{ $location->room_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-lucide-chevron-down width="15" height="15" stroke-width="2" class="rep-edit__select-chevron" aria-hidden="true" />
                                </div>
                                @error('location_id')
                                    <p id="edit-location-err" class="rep-edit__field-error" role="alert">
                                        <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="rep-edit__field">
                                <label for="edit-category" class="rep-edit__label">
                                    Categoría
                                    <span class="rep-edit__required" aria-hidden="true">*</span>
                                </label>
                                <div class="rep-edit__select-wrap">
                                    <x-lucide-layers width="16" height="16" stroke-width="2" class="rep-edit__select-icon" aria-hidden="true" />
                                    <select id="edit-category"
                                            name="category_id"
                                            required
                                            class="rep-edit__select @error('category_id') rep-edit__input--error @enderror"
                                            aria-describedby="edit-category-err">
                                        <option value="">Selecciona una categoría</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                @selected(old('category_id', (string) $ticket->category_id) === (string) $category->id)>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-lucide-chevron-down width="15" height="15" stroke-width="2" class="rep-edit__select-chevron" aria-hidden="true" />
                                </div>
                                @error('category_id')
                                    <p id="edit-category-err" class="rep-edit__field-error" role="alert">
                                        <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>

                        {{-- Priority grid --}}
                        <div class="rep-edit__field">
                            <span class="rep-edit__label" id="priority-group-label">
                                Prioridad
                                <span class="rep-edit__required" aria-hidden="true">*</span>
                            </span>
                            <div class="rep-edit__priority-grid"
                                 role="radiogroup"
                                 aria-labelledby="priority-group-label"
                                 aria-describedby="edit-priority-err">
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
                                <p id="edit-priority-err" class="rep-edit__field-error" role="alert">
                                    <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </section>

                {{-- ─────────────────────────────────────────────
                     SECTION 3 — Evidencias
                     ───────────────────────────────────────────── --}}
                <section class="rep-edit__section" aria-labelledby="section-evidence">
                    <div class="rep-edit__section-head">
                        <span class="rep-edit__step-badge" aria-hidden="true">3</span>
                        <div>
                            <h2 id="section-evidence" class="rep-edit__section-title">Evidencias <span class="rep-edit__optional">(opcional)</span></h2>
                            <p class="rep-edit__section-subtitle">Agrega fotos o documentos que ayuden a entender mejor el problema.</p>
                        </div>
                    </div>

                    <div class="rep-edit__section-body">
                        {{-- Existing media thumbnails --}}
                        @if ($existingMedia->isNotEmpty())
                            <div class="rep-edit__evidence-existing">
                                <p class="rep-edit__evidence-existing-label">
                                    <x-lucide-image width="14" height="14" stroke-width="2" aria-hidden="true" />
                                    Evidencias actuales ({{ $existingMedia->count() }})
                                </p>
                                <div class="rep-edit__evidence-grid">
                                    @foreach ($existingMedia as $media)
                                        @php
                                            $isImage = str_starts_with((string) $media->file_type, 'image/');
                                            $mediaUrl = (string) $media->file_url;
                                        @endphp
                                        @if ($isImage && $mediaUrl)
                                            <a class="rep-edit__evidence-thumb rep-edit__evidence-thumb--img"
                                               href="{{ $mediaUrl }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               title="Ver imagen"
                                               style="background-image: url('{{ $mediaUrl }}');">
                                                <span class="sr-only">Ver imagen</span>
                                                <span class="rep-edit__evidence-overlay" aria-hidden="true">
                                                    <x-lucide-zoom-in width="16" height="16" stroke-width="2" />
                                                </span>
                                            </a>
                                        @elseif ($mediaUrl)
                                            <a class="rep-edit__evidence-thumb rep-edit__evidence-thumb--file"
                                               href="{{ $mediaUrl }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               title="Ver adjunto">
                                                <x-lucide-paperclip width="20" height="20" stroke-width="1.75" aria-hidden="true" />
                                                <span class="rep-edit__evidence-filename">Adjunto</span>
                                            </a>
                                        @endif
                                    @endforeach

                                    {{-- "Add more" placeholder --}}
                                    <label class="rep-edit__evidence-thumb rep-edit__evidence-thumb--add"
                                           for="new-images"
                                           title="Agregar más imágenes"
                                           aria-label="Agregar más imágenes">
                                        <x-lucide-plus width="22" height="22" stroke-width="2.5" aria-hidden="true" />
                                    </label>
                                </div>
                            </div>
                        @endif

                        {{-- Upload drop zone --}}
                        <div class="rep-edit__dropzone" id="edit-dropzone">
                            <label for="new-images" class="rep-edit__dropzone-label">
                                <div class="rep-edit__dropzone-icon" aria-hidden="true">
                                    <x-lucide-cloud-upload width="32" height="32" stroke-width="1.5" />
                                </div>
                                <p class="rep-edit__dropzone-text">
                                    <span class="rep-edit__dropzone-link">Arrastra archivos aquí o haz clic para seleccionar</span>
                                </p>
                                <p class="rep-edit__dropzone-hint">Formatos: JPG, PNG, GIF, WebP · Tamaño máximo: 10 MB por archivo · Hasta 10 imágenes</p>
                                <input id="new-images"
                                       type="file"
                                       name="new_images[]"
                                       multiple
                                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                       class="rep-edit__file-input"
                                       aria-describedby="new-images-err"
                                       aria-label="Subir nuevas imágenes de evidencia">
                            </label>

                            {{-- Preview of files selected by user (JS-driven) --}}
                            <div id="new-images-preview" class="rep-edit__preview-grid" aria-live="polite" aria-label="Vista previa de archivos seleccionados"></div>
                        </div>

                        @error('new_images')
                            <p id="new-images-err" class="rep-edit__field-error" role="alert">
                                <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                {{ $message }}
                            </p>
                        @enderror
                        @error('new_images.*')
                            <p class="rep-edit__field-error" role="alert">
                                <x-lucide-alert-circle width="13" height="13" stroke-width="2" aria-hidden="true" />
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </section>

                {{-- ─────────────────────────────────────────────
                     SECTION 4 — Confirmación (footer actions)
                     ───────────────────────────────────────────── --}}
                <section class="rep-edit__section rep-edit__section--actions" aria-labelledby="section-confirm">
                    <div class="rep-edit__section-head">
                        <span class="rep-edit__step-badge" aria-hidden="true">4</span>
                        <div>
                            <h2 id="section-confirm" class="rep-edit__section-title">Confirmación</h2>
                            <p class="rep-edit__section-subtitle">Revisa los cambios antes de guardarlos.</p>
                        </div>
                    </div>

                    <div class="rep-edit__section-body">
                        <div class="rep-edit__form-actions">
                            <a href="{{ route('reporter.tickets.show', $ticket->id) }}"
                               class="rep-edit__btn rep-edit__btn--ghost">
                                Cancelar y volver
                            </a>
                            <button type="submit"
                                    form="edit-ticket-form"
                                    class="rep-edit__btn rep-edit__btn--primary"
                                    id="edit-submit-btn">
                                <x-lucide-save width="16" height="16" stroke-width="2.5" aria-hidden="true" />
                                Guardar cambios
                            </button>
                        </div>
                    </div>
                </section>

            </form>
        </main>

        {{-- ══════════════════════════════════════
             RIGHT: Summary sidebar
             ══════════════════════════════════════ --}}
        <aside class="rep-edit__aside" aria-label="Información del ticket">

            {{-- Ticket summary panel --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-file-text width="16" height="16" stroke-width="2" aria-hidden="true" />
                    Resumen del ticket
                </h2>
                <dl class="rep-detail">
                    <div class="rep-detail__row">
                        <dt>
                            <x-lucide-hash width="14" height="14" stroke-width="2" aria-hidden="true" />
                            Referencia
                        </dt>
                        <dd><code>{{ $ref }}</code></dd>
                    </div>
                    <div class="rep-detail__row">
                        <dt>
                            <x-lucide-circle-dot width="14" height="14" stroke-width="2" aria-hidden="true" />
                            Estado actual
                        </dt>
                        <dd>
                            <span class="rep-badge rep-status--open">
                                <span class="rep-badge__dot" aria-hidden="true"></span>
                                Abierto
                            </span>
                        </dd>
                    </div>
                    <div class="rep-detail__row">
                        <dt>
                            <x-lucide-calendar width="14" height="14" stroke-width="2" aria-hidden="true" />
                            Creado el
                        </dt>
                        <dd>{{ $ticket->created_at?->translatedFormat('d M Y · H:i') ?? '—' }}</dd>
                    </div>
                    <div class="rep-detail__row">
                        <dt>
                            <x-lucide-map-pin width="14" height="14" stroke-width="2" aria-hidden="true" />
                            Ubicación
                        </dt>
                        <dd>{{ $ticket->location?->name ?? 'Sin ubicación' }}</dd>
                    </div>
                    <div class="rep-detail__row">
                        <dt>
                            <x-lucide-layers width="14" height="14" stroke-width="2" aria-hidden="true" />
                            Categoría
                        </dt>
                        <dd>{{ $ticket->category?->name ?? 'Sin categoría' }}</dd>
                    </div>
                    <div class="rep-detail__row">
                        <dt>
                            <x-lucide-alert-circle width="14" height="14" stroke-width="2" aria-hidden="true" />
                            Prioridad
                        </dt>
                        <dd>
                            @php $pMeta = $priorityMeta[(string)$ticket->priority] ?? null; @endphp
                            @if ($pMeta)
                                <span class="rep-badge rep-prio rep-tone-{{ $pMeta['tone'] }}">
                                    <span class="rep-badge__dot" aria-hidden="true"></span>
                                    {{ $pMeta['label'] }}
                                </span>
                            @else
                                {{ ucfirst((string)$ticket->priority) }}
                            @endif
                        </dd>
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
                    <li>Solo puedes editar mientras el ticket está abierto.</li>
                    <li>Si mantenimiento ya lo tomó, no podrás modificar la información.</li>
                    <li>Las imágenes que agregues se conservan; no se borran las existentes.</li>
                </ul>
            </section>

            {{-- Quick tips --}}
            <section class="rep-panel rep-edit__tips-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-lightbulb width="16" height="16" stroke-width="2" aria-hidden="true" />
                    Consejos rápidos
                </h2>
                <ul class="rep-edit__tips-list">
                    <li>
                        <x-lucide-check-circle width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                        Sé específico y claro en la descripción.
                    </li>
                    <li>
                        <x-lucide-check-circle width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                        Indica dónde ocurre el problema.
                    </li>
                    <li>
                        <x-lucide-check-circle width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                        Agrega fotos nítidas del problema.
                    </li>
                    <li>
                        <x-lucide-check-circle width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                        La información completa agiliza la atención.
                    </li>
                </ul>
            </section>

        </aside>
    </div>

</div>

{{-- ── JS: character counter + image preview ── --}}
<script>
(function () {
    // Description character counter
    var textarea = document.getElementById('edit-description');
    var counter  = document.getElementById('description-count-val');
    if (textarea && counter) {
        textarea.addEventListener('input', function () {
            counter.textContent = textarea.value.length;
        });
    }

    // Image preview
    var fileInput = document.getElementById('new-images');
    var preview   = document.getElementById('new-images-preview');
    var dropzone  = document.getElementById('edit-dropzone');

    if (fileInput && preview) {
        fileInput.addEventListener('change', renderPreviews);
    }

    // Drag-and-drop support
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
            if (fileInput && e.dataTransfer) {
                fileInput.files = e.dataTransfer.files;
                renderPreviews();
            }
        });
    }

    function renderPreviews() {
        if (!fileInput || !preview) return;
        preview.innerHTML = '';
        var files = Array.from(fileInput.files || []);
        files.forEach(function (file) {
            if (!file.type.startsWith('image/')) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                var wrap  = document.createElement('div');
                wrap.className = 'rep-edit__preview-item';

                var img = document.createElement('img');
                img.src = e.target.result;
                img.alt = file.name;
                img.className = 'rep-edit__preview-img';

                var name = document.createElement('span');
                name.className = 'rep-edit__preview-name';
                name.textContent = file.name.length > 18 ? file.name.slice(0,15)+'…' : file.name;

                wrap.appendChild(img);
                wrap.appendChild(name);
                preview.appendChild(wrap);
            };
            reader.readAsDataURL(file);
        });
    }

    // Priority card visual feedback
    var radios = document.querySelectorAll('.rep-edit__priority-radio');
    radios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.rep-edit__priority-card').forEach(function (card) {
                card.classList.remove('is-selected');
            });
            if (radio.checked && radio.parentElement) {
                radio.parentElement.classList.add('is-selected');
            }
        });
    });
})();
</script>
@endsection
