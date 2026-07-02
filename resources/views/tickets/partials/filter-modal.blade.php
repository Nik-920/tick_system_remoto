{{-- ── FILTROS AVANZADOS — Modal (Tickets) ─────────────────────────────────────
     Mobile  (<640px): bottom-sheet que sube desde abajo.
     Desktop (≥640px): modal centrado con fade + escala suave.
     Mecánica: JS toggle añade/quita .tickets-filter-modal--open (ver script
     en tickets/index.blade.php). GET form → "Aplicar filtros" navega con
     todos los params del panel.
──────────────────────────────────────────────────────────────────────────── --}}

<div
    class="tickets-filter-modal"
    id="tickets-filter-modal"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="tickets-fm-title"
>
    <div class="tickets-filter-modal__backdrop" id="tickets-filter-backdrop" aria-hidden="true"></div>

    <div class="tickets-filter-modal__drawer">

        {{-- Header --}}
        <div class="tickets-filter-modal__header">
            <h2 class="tickets-filter-modal__title" id="tickets-fm-title">
                <x-lucide-sliders-horizontal width="17" height="17" stroke-width="2" aria-hidden="true" />
                Filtros avanzados
            </h2>
            <button
                type="button"
                class="tickets-filter-modal__close"
                id="tickets-filter-close"
                aria-label="Cerrar filtros"
            >
                <x-lucide-x width="18" height="18" stroke-width="2.5" />
            </button>
        </div>

        {{-- Form --}}
        <form
            method="GET"
            action="{{ route('tickets.index') }}"
            class="tickets-filter-modal__form"
            id="tickets-filter-form"
        >
            {{-- Preservar búsqueda al aplicar --}}
            @if ($searchValue !== '')
                <input type="hidden" name="search" value="{{ $searchValue }}">
            @endif

            {{-- ── Estado ── --}}
            <fieldset class="tickets-fm-section">
                <legend class="tickets-fm-section__label">
                    <x-lucide-activity width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Estado
                </legend>
                <div class="tickets-fm-opts">
                    <label class="tickets-fopt {{ $stateValue === '' ? 'tickets-fopt--on' : '' }}">
                        <input type="radio" name="state" value="" @checked($stateValue === '')>
                        Todos
                    </label>
                    @foreach ($stateLabels as $val => $label)
                        <label class="tickets-fopt {{ $stateValue === $val ? 'tickets-fopt--on' : '' }}">
                            <input type="radio" name="state" value="{{ $val }}" @checked($stateValue === $val)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- ── Prioridad ── --}}
            <fieldset class="tickets-fm-section">
                <legend class="tickets-fm-section__label">
                    <x-lucide-alert-triangle width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Prioridad
                </legend>
                <div class="tickets-fm-opts">
                    <label class="tickets-fopt {{ $priorityValue === '' ? 'tickets-fopt--on' : '' }}">
                        <input type="radio" name="priority" value="" @checked($priorityValue === '')>
                        Todas
                    </label>
                    @foreach ($priorityLabels as $val => $label)
                        <label class="tickets-fopt {{ $priorityValue === $val ? 'tickets-fopt--on' : '' }}">
                            <input type="radio" name="priority" value="{{ $val }}" @checked($priorityValue === $val)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- ── Ubicación ── --}}
            <fieldset class="tickets-fm-section">
                <legend class="tickets-fm-section__label">
                    <x-lucide-map-pin width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Ubicación
                </legend>
                <select name="location_id" class="tickets-fm-select" aria-label="Filtrar por ubicación">
                    <option value="">Todas</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected($locationValue === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </fieldset>

            {{-- ── Categoría ── --}}
            <fieldset class="tickets-fm-section">
                <legend class="tickets-fm-section__label">
                    <x-lucide-tag width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Categoría
                </legend>
                <select name="category_id" class="tickets-fm-select" aria-label="Filtrar por categoría">
                    <option value="">Todas</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($categoryValue === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </fieldset>

            {{-- ── Asignación ── --}}
            @unless ($isReporterOnly)
                <fieldset class="tickets-fm-section">
                    <legend class="tickets-fm-section__label">
                        <x-lucide-user-check width="13" height="13" stroke-width="2" aria-hidden="true" />
                        Asignación
                    </legend>
                    <div class="tickets-fm-opts">
                        @foreach (['all' => 'Todos', 'unassigned' => 'Sin asignar', 'mine' => 'Mis tickets', 'assigned' => 'Asignados'] as $val => $label)
                            @php
                                $isActiveAssignment = $val === 'all'
                                    ? ($assignmentValue === '' || $assignmentValue === 'all')
                                    : $assignmentValue === $val;
                            @endphp
                            <label class="tickets-fopt {{ $isActiveAssignment ? 'tickets-fopt--on' : '' }}">
                                <input type="radio" name="assignment" value="{{ $val }}" @checked($isActiveAssignment)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endunless

            {{-- ── Por página ── --}}
            <fieldset class="tickets-fm-section">
                <legend class="tickets-fm-section__label">
                    <x-lucide-layout-list width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Por página
                </legend>
                <select name="per_page" class="tickets-fm-select" aria-label="Tickets por página">
                    <option value="">15</option>
                    @foreach ([10, 15, 25, 50] as $option)
                        <option value="{{ $option }}" @selected($perPageValue === (string) $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </fieldset>

            {{-- ── Período ── --}}
            <fieldset class="tickets-fm-section {{ $isReporterOnly ? 'tickets-fm-section--last' : '' }}">
                <legend class="tickets-fm-section__label">
                    <x-lucide-clock width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Período
                </legend>
                <div class="tickets-fm-date-row">
                    <div class="tickets-fm-date-field">
                        <label for="tickets-fm-from" class="tickets-fm-date-label">Desde</label>
                        <input
                            id="tickets-fm-from"
                            type="date"
                            name="from"
                            value="{{ $fromValue }}"
                            class="tickets-fm-select"
                            aria-label="Fecha desde"
                        >
                    </div>
                    <div class="tickets-fm-date-field">
                        <label for="tickets-fm-to" class="tickets-fm-date-label">Hasta</label>
                        <input
                            id="tickets-fm-to"
                            type="date"
                            name="to"
                            value="{{ $toValue }}"
                            class="tickets-fm-select"
                            aria-label="Fecha hasta"
                        >
                    </div>
                </div>
            </fieldset>

            {{-- ── Posibles duplicados ── --}}
            @unless ($isReporterOnly)
                <fieldset class="tickets-fm-section tickets-fm-section--last">
                    <legend class="tickets-fm-section__label">
                        <x-lucide-copy width="13" height="13" stroke-width="2" aria-hidden="true" />
                        Duplicados
                    </legend>
                    <label class="tickets-fopt-toggle">
                        <input type="checkbox" name="duplicates" value="1" @checked($duplicatesOn)>
                        <span class="tickets-fopt-toggle__track" aria-hidden="true">
                            <span class="tickets-fopt-toggle__thumb"></span>
                        </span>
                        Posibles duplicados
                    </label>
                </fieldset>
            @endunless

            {{-- Footer --}}
            <div class="tickets-filter-modal__footer">
                <a
                    href="{{ route('tickets.index') }}"
                    class="tickets-fm-clear"
                    aria-label="Limpiar todos los filtros"
                >
                    <x-lucide-x width="13" height="13" stroke-width="2.5" aria-hidden="true" />
                    Limpiar
                </a>
                <button type="submit" class="tickets-fm-apply">
                    <x-lucide-check width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                    Aplicar filtros
                </button>
            </div>

        </form>
    </div>
</div>
