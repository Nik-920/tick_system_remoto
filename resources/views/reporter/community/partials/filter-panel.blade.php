{{-- ── FILTROS AVANZADOS — Panel/Drawer ────────────────────────────────────────
     Comportamiento por breakpoint:
       Móvil  (<640px): bottom-sheet que sube desde abajo (translateY + backdrop)
       Desktop(≥640px): modal centrado con fade + escala suave

     Mecánica: JS toggle añade/quita .comm-filter-panel--open en el contenedor.
     GET form → el "Aplicar" navega a la ruta con todos los params del panel.
     "Limpiar todo" preserva q y sort; sólo borra los filtros del panel.
──────────────────────────────────────────────────────────────────────────── --}}

@php
/* URL para "Limpiar todo" — preserva solo la búsqueda (q); sort se resetea al default */
$panelClearParams = array_filter(['q' => $feed->filters['q']]);
$panelClearUrl = route('reporter.community')
    . (! empty($panelClearParams) ? '?' . http_build_query($panelClearParams) : '');
@endphp

<div
    class="comm-filter-panel"
    id="comm-filter-panel"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="comm-fpanel-title"
>
    {{-- Backdrop: clic cierra el panel --}}
    <div class="comm-filter-panel__backdrop" id="comm-filter-backdrop" aria-hidden="true"></div>

    {{-- Drawer (bottom-sheet en móvil / modal centrado en desktop) --}}
    <div class="comm-filter-panel__drawer">

        {{-- Cabecera --}}
        <div class="comm-filter-panel__header">
            <h2 class="comm-filter-panel__title" id="comm-fpanel-title">
                <x-lucide-sliders-horizontal width="17" height="17" stroke-width="2" aria-hidden="true" />
                Filtros avanzados
            </h2>
            <button
                type="button"
                class="comm-filter-panel__close"
                id="comm-filter-close"
                aria-label="Cerrar filtros avanzados"
            >
                <x-lucide-x width="18" height="18" stroke-width="2.5" />
            </button>
        </div>

        {{-- Formulario de filtros --}}
        <form
            method="GET"
            action="{{ route('reporter.community') }}"
            class="comm-filter-panel__form"
            id="comm-filter-form"
        >
            {{-- Preservar búsqueda al aplicar --}}
            @if ($feed->filters['q'] !== '')
                <input type="hidden" name="q" value="{{ $feed->filters['q'] }}">
            @endif

            {{-- ── Sección: Ordenar por ── --}}
            <fieldset class="comm-fpanel-section">
                <legend class="comm-fpanel-section__label">
                    <x-lucide-arrow-up-down width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Ordenar por
                </legend>
                <div class="comm-fpanel-opts">
                    @foreach ($feed->sortOptions as $opt)
                        <label class="comm-fopt {{ $opt['active'] ? 'comm-fopt--on' : '' }}">
                            <input
                                type="radio"
                                name="sort"
                                value="{{ $opt['key'] }}"
                                {{ $opt['active'] ? 'checked' : '' }}
                            >
                            {{ $opt['label'] }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- ── Sección: Estado ── --}}
            <fieldset class="comm-fpanel-section">
                <legend class="comm-fpanel-section__label">
                    <x-lucide-activity width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Estado
                </legend>
                <div class="comm-fpanel-opts">
                    @foreach (['' => 'Todos', 'open' => 'Abierto', 'in_progress' => 'En progreso', 'resolved' => 'Resuelto'] as $val => $label)
                        <label class="comm-fopt {{ $feed->filters['state'] === $val ? 'comm-fopt--on' : '' }}">
                            <input
                                type="radio"
                                name="state"
                                value="{{ $val }}"
                                {{ $feed->filters['state'] === $val ? 'checked' : '' }}
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- ── Sección: Período ── --}}
            <fieldset class="comm-fpanel-section">
                <legend class="comm-fpanel-section__label">
                    <x-lucide-clock width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Período
                </legend>
                <div class="comm-fpanel-opts">
                    @foreach (['' => 'Cualquiera', '24h' => 'Últimas 24h', '7d' => 'Última semana', '30d' => 'Último mes'] as $val => $label)
                        <label class="comm-fopt {{ $feed->filters['period'] === $val ? 'comm-fopt--on' : '' }}">
                            <input
                                type="radio"
                                name="period"
                                value="{{ $val }}"
                                {{ $feed->filters['period'] === $val ? 'checked' : '' }}
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- ── Sección: Prioridad ── --}}
            <fieldset class="comm-fpanel-section">
                <legend class="comm-fpanel-section__label">
                    <x-lucide-alert-triangle width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Prioridad
                </legend>
                <div class="comm-fpanel-opts">
                    @foreach (['' => 'Cualquiera', 'critical' => 'Crítica', 'high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'] as $val => $label)
                        <label class="comm-fopt {{ ($feed->filters['priority'] ?? '') === $val ? 'comm-fopt--on' : '' }}">
                            <input
                                type="radio"
                                name="priority"
                                value="{{ $val }}"
                                {{ ($feed->filters['priority'] ?? '') === $val ? 'checked' : '' }}
                            >
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- ── Sección: Categoría ── --}}
            @if (count($feed->categories) > 0)
                <fieldset class="comm-fpanel-section">
                    <legend class="comm-fpanel-section__label">
                        <x-lucide-tag width="13" height="13" stroke-width="2" aria-hidden="true" />
                        Categoría
                    </legend>
                    <div class="comm-fpanel-opts comm-fpanel-opts--wrap">
                        <label class="comm-fopt {{ $feed->filters['category'] === '' ? 'comm-fopt--on' : '' }}">
                            <input
                                type="radio"
                                name="category"
                                value=""
                                {{ $feed->filters['category'] === '' ? 'checked' : '' }}
                            >
                            Todas
                        </label>
                        @foreach ($feed->categories as $cat)
                            <label class="comm-fopt {{ $feed->filters['category'] === $cat['id'] ? 'comm-fopt--on' : '' }}">
                                <input
                                    type="radio"
                                    name="category"
                                    value="{{ $cat['id'] }}"
                                    {{ $feed->filters['category'] === $cat['id'] ? 'checked' : '' }}
                                >
                                {{ $cat['name'] }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            {{-- ── Sección: Edificio ── --}}
            @if (count($feed->buildings) > 0)
                <fieldset class="comm-fpanel-section">
                    <legend class="comm-fpanel-section__label">
                        <x-lucide-building-2 width="13" height="13" stroke-width="2" aria-hidden="true" />
                        Edificio
                    </legend>
                    <select
                        name="building"
                        class="comm-fpanel-select"
                        aria-label="Filtrar por edificio"
                    >
                        <option value="">Todos los edificios</option>
                        @foreach ($feed->buildings as $building)
                            <option
                                value="{{ $building }}"
                                {{ $feed->filters['building'] === $building ? 'selected' : '' }}
                            >{{ $building }}</option>
                        @endforeach
                    </select>
                </fieldset>
            @endif

            {{-- ── Sección: Contenido / Evidencia ── --}}
            <fieldset class="comm-fpanel-section">
                <legend class="comm-fpanel-section__label">
                    <x-lucide-image width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Contenido
                </legend>
                <label class="comm-fopt-toggle">
                    <input
                        type="checkbox"
                        name="has_media"
                        value="1"
                        {{ $feed->filters['has_media'] === '1' ? 'checked' : '' }}
                    >
                    <span class="comm-fopt-toggle__track" aria-hidden="true">
                        <span class="comm-fopt-toggle__thumb"></span>
                    </span>
                    Solo con evidencia (fotos o archivos adjuntos)
                </label>
            </fieldset>

            {{-- ── Sección: Guardados ── --}}
            <fieldset class="comm-fpanel-section comm-fpanel-section--last">
                <legend class="comm-fpanel-section__label">
                    <x-lucide-bookmark width="13" height="13" stroke-width="2" aria-hidden="true" />
                    Guardados
                </legend>
                <label class="comm-fopt-toggle">
                    <input
                        type="checkbox"
                        name="saved"
                        value="1"
                        {{ ($feed->filters['saved'] ?? '') === '1' ? 'checked' : '' }}
                    >
                    <span class="comm-fopt-toggle__track" aria-hidden="true">
                        <span class="comm-fopt-toggle__thumb"></span>
                    </span>
                    Solo mis tickets guardados
                </label>
            </fieldset>

            {{-- Pie del panel: Limpiar + Aplicar --}}
            <div class="comm-filter-panel__footer">
                <a
                    href="{{ $panelClearUrl }}"
                    class="comm-fpanel-clear"
                    aria-label="Limpiar filtros del panel"
                >
                    <x-lucide-x width="13" height="13" stroke-width="2.5" aria-hidden="true" />
                    Limpiar todo
                </a>
                <button type="submit" class="comm-fpanel-apply">
                    <x-lucide-check width="14" height="14" stroke-width="2.5" aria-hidden="true" />
                    Aplicar filtros
                </button>
            </div>

        </form>
    </div>
</div>
