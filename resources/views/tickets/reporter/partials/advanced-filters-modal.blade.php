{{-- ── FILTROS AVANZADOS — Modal (Mis tickets) ────────────────────────────────
     Mobile  (<640px): bottom-sheet que sube desde abajo.
     Desktop (≥640px): modal centrado con fade + escala suave.
     Mecánica: JS toggle añade/quita .rep-filter-modal--open.
     GET form → el "Aplicar filtros" navega con todos los params del panel.
──────────────────────────────────────────────────────────────────────────── --}}

<div
    class="rep-filter-modal"
    id="rep-filter-modal"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="rep-fm-title"
>
    <div class="rep-filter-modal__backdrop" id="rep-filter-backdrop" aria-hidden="true"></div>

    <div class="rep-filter-modal__drawer">

        {{-- Header --}}
        <div class="rep-filter-modal__header">
            <h2 class="rep-filter-modal__title" id="rep-fm-title">
                <x-lucide-sliders-horizontal width="17" height="17" stroke-width="2" aria-hidden="true" />
                Filtros avanzados
            </h2>
            <button
                type="button"
                class="rep-filter-modal__close"
                id="rep-filter-close"
                aria-label="Cerrar filtros"
            >
                <x-lucide-x width="18" height="18" stroke-width="2.5" />
            </button>
        </div>

        {{-- Form --}}
        <form
            method="GET"
            action="{{ route('reporter.tickets.index') }}"
            class="rep-filter-modal__form"
            id="rep-filter-form"
        >
            {{-- Preserve search when applying panel filters --}}
            @if (trim((string) ($board->filters['search'] ?? '')) !== '')
                <input type="hidden" name="search" value="{{ $board->filters['search'] }}">
            @endif

            {{-- ── Estado ── --}}
            <fieldset class="rep-fm-section">
                <legend class="rep-fm-section__label">
                    <x-lucide-activity width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Estado
                </legend>
                <div class="rep-fm-opts">
                    @foreach ($board->chips as $chip)
                        <label class="rep-fm-opt {{ $chip['active'] ? 'rep-fm-opt--on' : '' }} rep-tone-{{ $chip['tone'] }}">
                            <input
                                type="radio"
                                name="status"
                                value="{{ $chip['key'] === 'all' ? '' : $chip['key'] }}"
                                @checked($chip['active'])
                            >
                            {{ $chip['label'] }}
                            <span class="rep-fm-opt__count">{{ $chip['count'] }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- ── Prioridad ── --}}
            <fieldset class="rep-fm-section">
                <legend class="rep-fm-section__label">
                    <x-lucide-alert-triangle width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Prioridad
                </legend>
                <div class="rep-fm-opts">
                    @foreach (['' => 'Todas', 'low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Crítica'] as $val => $label)
                        <label class="rep-fm-opt {{ ($board->filters['priority'] ?? '') === $val ? 'rep-fm-opt--on' : '' }}">
                            <input
                                type="radio"
                                name="priority"
                                value="{{ $val }}"
                                @checked(($board->filters['priority'] ?? '') === $val)
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- ── Laboratorio ── --}}
            @if ($board->locations->isNotEmpty())
                <fieldset class="rep-fm-section">
                    <legend class="rep-fm-section__label">
                        <x-lucide-map-pin width="13" height="13" stroke-width="2" aria-hidden="true" />
                        Laboratorio
                    </legend>
                    <select
                        name="location_id"
                        class="rep-fm-select"
                        aria-label="Filtrar por laboratorio"
                    >
                        <option value="">Todos</option>
                        @foreach ($board->locations as $location)
                            <option
                                value="{{ $location->id }}"
                                @selected(($board->filters['location_id'] ?? '') === (string) $location->id)
                            >{{ $board->locationLabel($location) }}</option>
                        @endforeach
                    </select>
                </fieldset>
            @endif

            {{-- ── Categoría ── --}}
            @if ($board->categories->isNotEmpty())
                <fieldset class="rep-fm-section">
                    <legend class="rep-fm-section__label">
                        <x-lucide-tag width="13" height="13" stroke-width="2" aria-hidden="true" />
                        Categoría
                    </legend>
                    <select
                        name="category_id"
                        class="rep-fm-select"
                        aria-label="Filtrar por categoría"
                    >
                        <option value="">Todas</option>
                        @foreach ($board->categories as $category)
                            <option
                                value="{{ $category->id }}"
                                @selected(($board->filters['category_id'] ?? '') === (string) $category->id)
                            >{{ $category->name }}</option>
                        @endforeach
                    </select>
                </fieldset>
            @endif

            {{-- ── Ordenar por ── --}}
            <fieldset class="rep-fm-section">
                <legend class="rep-fm-section__label">
                    <x-lucide-arrow-up-down width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Ordenar por
                </legend>
                <div class="rep-fm-opts">
                    @foreach ($board->sortOptions() as $val => $label)
                        <label class="rep-fm-opt {{ $board->sort === $val ? 'rep-fm-opt--on' : '' }}">
                            <input
                                type="radio"
                                name="sort"
                                value="{{ $val }}"
                                @checked($board->sort === $val)
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- ── Período ── --}}
            <fieldset class="rep-fm-section rep-fm-section--last">
                <legend class="rep-fm-section__label">
                    <x-lucide-clock width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Período
                </legend>
                <div class="rep-fm-date-row">
                    <div class="rep-fm-date-field">
                        <label for="rep-fm-from" class="rep-fm-date-label">Desde</label>
                        <input
                            id="rep-fm-from"
                            type="date"
                            name="from"
                            value="{{ $board->filters['from'] ?? '' }}"
                            class="rep-fm-select"
                            aria-label="Fecha desde"
                        >
                    </div>
                    <div class="rep-fm-date-field">
                        <label for="rep-fm-to" class="rep-fm-date-label">Hasta</label>
                        <input
                            id="rep-fm-to"
                            type="date"
                            name="to"
                            value="{{ $board->filters['to'] ?? '' }}"
                            class="rep-fm-select"
                            aria-label="Fecha hasta"
                        >
                    </div>
                </div>
            </fieldset>

            {{-- Footer --}}
            <div class="rep-filter-modal__footer">
                <a
                    href="{{ route('reporter.tickets.index') }}"
                    class="rep-fm-clear"
                    aria-label="Limpiar todos los filtros"
                >
                    <x-lucide-x width="13" height="13" stroke-width="2.5" aria-hidden="true" />
                    Limpiar
                </a>
                <button type="submit" class="rep-fm-apply">
                    <x-lucide-check width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                    Aplicar filtros
                </button>
            </div>

        </form>
    </div>
</div>
