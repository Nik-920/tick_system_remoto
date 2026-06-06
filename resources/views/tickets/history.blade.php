@extends('layouts.app')

@section('title', 'Historial de tickets')

@section('content')
@php
    /** @var \App\ViewModels\Tickets\HistoryBoardViewModel $board */
    $f = $board->filters;
    $searchValue   = (string) ($f['search'] ?? '');
    $priorityValue = (string) ($f['priority'] ?? '');
    $locationValue = (string) ($f['location_id'] ?? '');
    $categoryValue = (string) ($f['category_id'] ?? '');
    $fromValue     = (string) ($f['from'] ?? '');
    $toValue       = (string) ($f['to'] ?? '');

    $statusTone = ['resolved' => 'success', 'rejected' => 'high'];

    // Querystring carried when switching the result chip (drop volatile keys).
    $preserved = collect(request()->query())->except(['result', 'page'])->all();
    $pageBase = collect(request()->query())->except(['page'])->all();

    // "Other" filters = everything except the result chip. Lets the empty state
    // distinguish "no matches for your filters" from "this result is just empty".
    $hasOtherFilters = $searchValue !== '' || $priorityValue !== '' || $locationValue !== ''
        || $categoryValue !== '' || $fromValue !== '' || $toValue !== '';
    $hasFilters = $hasOtherFilters || $board->activeResult !== 'all';

    $resultLabels = ['resolved' => 'resueltos', 'rejected' => 'rechazados'];

    $stateOptions = ['resolved' => 'Resuelto', 'rejected' => 'Rechazado'];
    $priorityOptions = ['high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja', 'critical' => 'Crítica'];
    $sortOptions = ['recent' => 'Más recientes', 'oldest' => 'Más antiguos', 'priority' => 'Prioridad'];

    $summary = $board->summary;
    $summaryCards = [
        ['value' => (string) $summary['total'],      'label' => 'Tickets en el periodo',          'note' => 'Resueltos + rechazados',                                                  'hint' => 'Total de tickets cerrados (resueltos o rechazados) en el periodo seleccionado.', 'icon' => 'history',      'tone' => 'primary'],
        ['value' => (string) $summary['resolved'],   'label' => 'Resueltos',                      'note' => $summary['resolved_pct'].'% del total',                                    'hint' => 'Tickets que terminaron en estado resuelto.',                                    'icon' => 'circle-check', 'tone' => 'success'],
        ['value' => (string) $summary['rejected'],   'label' => 'Rechazados',                     'note' => $summary['rejected_pct'].'% del total',                                    'hint' => 'Tickets que terminaron en estado rechazado.',                                   'icon' => 'x',            'tone' => 'high'],
        ['value' => $summary['avg_label'],           'label' => 'Tiempo promedio de resolución',  'note' => $summary['avg_has_data'] ? 'Solo tickets resueltos' : 'Sin tickets resueltos aún', 'hint' => 'Promedio de (fecha de resolución − fecha de creación), calculado solo con tickets resueltos.', 'icon' => 'clock', 'tone' => 'warning'],
        ['value' => $summary['resolution_rate'].'%', 'label' => 'Tasa de resolución',             'note' => 'Resueltos / total del periodo',                                           'hint' => 'Porcentaje de tickets del periodo que terminaron resueltos (vs rechazados).',    'icon' => 'shield-check', 'tone' => 'purple'],
    ];

    $periodLabel = ($fromValue !== '' || $toValue !== '')
        ? trim(($fromValue !== '' ? $fromValue : '…').' — '.($toValue !== '' ? $toValue : '…'))
        : 'Todo el historial';

    $donut = $board->donut;
    $donutStops = collect($donut['segments'])
        ->map(fn ($s) => "{$s['color']} {$s['start']}% {$s['end']}%")
        ->implode(', ');
@endphp

<div class="hist-page">

    {{-- ── 1. HEADER ─────────────────────────────────────────────── --}}
    <header class="hist-header">
        <div>
            <h1 class="hist-header__title">Historial de tickets</h1>
            <p class="hist-header__subtitle">
                Consulta y revisa el historial completo de tickets gestionados.
            </p>
            <p class="hist-header__hint">
                <x-lucide-info width="13" height="13" stroke-width="2" />
                Este historial muestra tickets resueltos o rechazados que estuvieron asignados a ti.
            </p>
        </div>
        <div class="hist-header__actions">
            {{-- Visual placeholder: real export is not implemented in this phase. --}}
            <button type="button" class="hist-btn-outline" data-hist-export
                    aria-describedby="hist-export-soon"
                    title="Exportación disponible próximamente">
                <x-lucide-download width="16" height="16" stroke-width="2.5" />
                Exportar
                <span class="hist-soon" id="hist-export-soon">Próximamente</span>
            </button>
        </div>
    </header>

    {{-- ── 2. FILTER PANEL (real GET form) ───────────────────────── --}}
    <form method="GET" action="{{ route('tickets.history') }}" class="hist-filters" aria-label="Filtros del historial">
        <div class="hist-filters__row">
            <div class="hist-field">
                <label for="hist-from" class="hist-field__label">Desde</label>
                <input id="hist-from" type="date" name="from" value="{{ $fromValue }}" class="hist-control">
            </div>
            <div class="hist-field">
                <label for="hist-to" class="hist-field__label">Hasta</label>
                <input id="hist-to" type="date" name="to" value="{{ $toValue }}" class="hist-control">
            </div>
            <div class="hist-field">
                <label for="hist-f-state" class="hist-field__label">Estado</label>
                <select id="hist-f-state" name="result" class="hist-control" data-hist-autosubmit>
                    <option value="all" @selected($board->activeResult === 'all')>Todos</option>
                    @foreach ($stateOptions as $value => $label)
                        <option value="{{ $value }}" @selected($board->activeResult === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hist-field">
                <label for="hist-f-priority" class="hist-field__label">Prioridad</label>
                <select id="hist-f-priority" name="priority" class="hist-control" data-hist-autosubmit>
                    <option value="">Todas</option>
                    @foreach ($priorityOptions as $value => $label)
                        <option value="{{ $value }}" @selected($priorityValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hist-field">
                <label for="hist-f-location" class="hist-field__label">Laboratorio</label>
                <select id="hist-f-location" name="location_id" class="hist-control" data-hist-autosubmit>
                    <option value="">Todos</option>
                    @foreach ($board->locations as $location)
                        <option value="{{ $location->id }}" @selected($locationValue === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hist-field">
                <label for="hist-f-category" class="hist-field__label">Categoría</label>
                <select id="hist-f-category" name="category_id" class="hist-control" data-hist-autosubmit>
                    <option value="">Todas</option>
                    @foreach ($board->categories as $category)
                        <option value="{{ $category->id }}" @selected($categoryValue === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="hist-filters__row">
            <div class="hist-search">
                <span class="hist-search__icon" aria-hidden="true">
                    <x-lucide-search width="18" height="18" stroke-width="2" />
                </span>
                <label for="hist-search" class="sr-only">Buscar en el historial</label>
                <input id="hist-search" type="search" name="search" value="{{ $searchValue }}" class="hist-search__input"
                       placeholder="Buscar por título, ID, descripción, laboratorio...">
            </div>
            <div class="hist-sort">
                <label for="hist-sort" class="hist-sort__label">Ordenar por:</label>
                <select id="hist-sort" name="sort" class="hist-sort__select" data-hist-autosubmit>
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($board->sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="hist-btn-outline hist-filters__submit">Buscar</button>
        </div>

        {{-- Result chips (quick links; preserve the rest of the query) --}}
        <div class="hist-chips" role="group" aria-label="Filtrar por resultado">
            @foreach ($board->chips as $chip)
                <a href="{{ route('tickets.history', array_merge($preserved, ['result' => $chip['key']])) }}"
                   class="hist-chip hist-tone-{{ $chip['tone'] }}"
                   @if ($board->activeResult === $chip['key']) aria-current="true" @endif>
                    {{ $chip['label'] }}
                    <span class="hist-chip__count">{{ $chip['count'] }}</span>
                </a>
            @endforeach
            <a href="{{ route('tickets.history') }}" class="hist-chips__clear">
                <x-lucide-x width="14" height="14" stroke-width="2.5" />
                Limpiar filtros
            </a>
        </div>
    </form>

    {{-- ── 3. SUMMARY KPI CARDS ──────────────────────────────────── --}}
    <div class="hist-summary">
        @foreach ($summaryCards as $card)
            <div class="hist-card hist-tone-{{ $card['tone'] }}" @isset($card['hint']) title="{{ $card['hint'] }}" @endisset>
                <div class="hist-card__icon">
                    <x-dynamic-component :component="'lucide-' . $card['icon']" width="22" height="22" stroke-width="2" />
                </div>
                <div class="hist-card__body">
                    <div class="hist-card__value">{{ $card['value'] }}</div>
                    <div class="hist-card__label">{{ $card['label'] }}</div>
                    @if (! empty($card['note']))
                        <span class="hist-card__note">{{ $card['note'] }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── 4. CONTENT LAYOUT: list + analytics rail ──────────────── --}}
    <div class="hist-layout">

        {{-- LEFT: history list --}}
        <div class="hist-main">
            <div class="hist-list">
                @forelse ($board->tickets as $t)
                    @php $tone = $statusTone[$t['status']] ?? 'neutral'; @endphp
                    <article class="hist-item hist-tone-{{ $tone }}" aria-labelledby="hist-row-title-{{ $t['id'] }}">
                        <span class="hist-item__rail" aria-hidden="true"></span>

                        <div class="hist-item__icon">
                            <x-dynamic-component :component="'lucide-' . $t['icon']" width="20" height="20" stroke-width="2" />
                        </div>

                        <div class="hist-item__body">
                            <div class="hist-item__top">
                                <h3 class="hist-item__title" id="hist-row-title-{{ $t['id'] }}">{{ $t['title'] }}</h3>
                                <span class="hist-item__id">{{ $t['ref'] }}</span>
                            </div>

                            <div class="hist-item__meta">
                                <x-lucide-map-pin width="13" height="13" stroke-width="2" />
                                <span>{{ $t['location'] }}</span>
                                <span class="hist-item__meta-sep" aria-hidden="true"></span>
                                <span>{{ $t['category'] }}</span>
                                @if ($t['type'] !== '')
                                    <span class="hist-item__meta-sep" aria-hidden="true"></span>
                                    <span>{{ $t['type'] }}</span>
                                @endif
                            </div>

                            <div class="hist-item__tags">
                                <span class="hist-badge hist-status--{{ $t['status'] }}">
                                    <span class="hist-badge__dot" aria-hidden="true"></span>
                                    {{ $t['status_label'] }}
                                </span>
                                <span class="hist-badge hist-prio hist-tone-{{ $t['priority'] }}">
                                    <span class="hist-badge__dot" aria-hidden="true"></span>
                                    {{ $t['priority_label'] }}
                                </span>
                            </div>
                        </div>

                        <div class="hist-item__side">
                            <div class="hist-item__stamps">
                                <div class="hist-stamp">
                                    <span class="hist-stamp__date">{{ $t['created'] }}</span>
                                    <span class="hist-stamp__label">Creado</span>
                                </div>
                                <div class="hist-stamp">
                                    <span class="hist-stamp__date">{{ $t['result'] }}</span>
                                    <span class="hist-stamp__label hist-stamp__label--result">{{ $t['status_label'] }}</span>
                                </div>
                            </div>

                            <div class="hist-item__actions">
                                <a href="{{ route('tickets.show', $t['id']) }}" class="hist-btn hist-btn--ghost">Ver detalle</a>

                                <div class="hist-kebab" data-hist-kebab>
                                    <button type="button" class="hist-kebab__btn"
                                            aria-label="Más acciones para {{ $t['title'] }}"
                                            aria-haspopup="true" aria-expanded="false" data-hist-kebab-btn>
                                        <x-lucide-more-vertical width="18" height="18" stroke-width="2" />
                                    </button>
                                    <div class="hist-kebab__menu" hidden data-hist-kebab-menu>
                                        <a href="{{ route('tickets.show', $t['id']) }}" class="hist-kebab__item">
                                            <x-lucide-eye width="15" height="15" stroke-width="2" /> Ver detalle
                                        </a>
                                        <button type="button" class="hist-kebab__item" title="Exportación disponible próximamente">
                                            <x-lucide-download width="15" height="15" stroke-width="2" /> Exportar ticket
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="hist-empty">
                        <x-lucide-history width="34" height="34" stroke-width="1.5" />
                        @if ($hasOtherFilters)
                            {{-- Active filters (search/priority/lab/category/dates) yielded nothing. --}}
                            <p class="hist-empty__title">Sin resultados</p>
                            <p class="hist-empty__note">Ningún ticket del historial coincide con los filtros actuales. Prueba a limpiarlos.</p>
                            <a href="{{ route('tickets.history') }}" class="hist-btn hist-btn--ghost">Limpiar filtros</a>
                        @elseif ($board->activeResult !== 'all')
                            {{-- A specific result chip is empty, but other results may exist. --}}
                            <p class="hist-empty__title">Sin tickets {{ $resultLabels[$board->activeResult] ?? '' }}</p>
                            <p class="hist-empty__note">No hay tickets {{ $resultLabels[$board->activeResult] ?? '' }} en tu historial para este periodo.</p>
                            <a href="{{ route('tickets.history') }}" class="hist-btn hist-btn--ghost">Volver a Todos</a>
                        @else
                            {{-- No history at all. --}}
                            <p class="hist-empty__title">Sin tickets en el historial</p>
                            <p class="hist-empty__note">Cuando resuelvas o se rechacen tus tickets, aparecerán aquí como historial.</p>
                        @endif
                    </div>
                @endforelse
            </div>

            @if ($board->pagination['total'] > 0)
                <div class="hist-list-foot">
                    <span class="hist-list-foot__count">
                        Mostrando {{ $board->pagination['from'] }} a {{ $board->pagination['to'] }} de {{ $board->pagination['total'] }} tickets
                    </span>
                    @if ($board->pagination['last'] > 1)
                        <nav class="hist-pagination" aria-label="Paginación del historial">
                            @if ($board->pagination['current'] > 1)
                                <a href="{{ route('tickets.history', array_merge($pageBase, ['page' => $board->pagination['current'] - 1])) }}" class="hist-page-btn" rel="prev" aria-label="Página anterior">
                                    <x-lucide-chevron-left width="15" height="15" stroke-width="2.5" />
                                    Anterior
                                </a>
                            @else
                                <span class="hist-page-btn" aria-disabled="true">
                                    <x-lucide-chevron-left width="15" height="15" stroke-width="2.5" />
                                    Anterior
                                </span>
                            @endif

                            @foreach ($board->pagination['pages'] as $page)
                                <a href="{{ route('tickets.history', array_merge($pageBase, ['page' => $page])) }}" class="hist-page-btn"
                                   aria-label="Página {{ $page }}{{ $board->pagination['current'] === $page ? ', página actual' : '' }}"
                                   @if ($board->pagination['current'] === $page) aria-current="page" @endif>{{ $page }}</a>
                            @endforeach

                            @if ($board->pagination['current'] < $board->pagination['last'])
                                <a href="{{ route('tickets.history', array_merge($pageBase, ['page' => $board->pagination['current'] + 1])) }}" class="hist-page-btn" rel="next" aria-label="Página siguiente">
                                    Siguiente
                                    <x-lucide-chevron-right width="15" height="15" stroke-width="2.5" />
                                </a>
                            @else
                                <span class="hist-page-btn" aria-disabled="true">
                                    Siguiente
                                    <x-lucide-chevron-right width="15" height="15" stroke-width="2.5" />
                                </span>
                            @endif
                        </nav>
                    @endif
                </div>
            @endif
        </div>

        {{-- RIGHT: analytics rail --}}
        <aside class="hist-rail" aria-label="Resumen analítico del historial">

            {{-- A. History summary (donut) --}}
            <section class="hist-panel">
                <div class="hist-panel__head">
                    <h2 class="hist-panel__title">
                        <x-lucide-pie-chart width="16" height="16" stroke-width="2" />
                        Resumen del historial
                    </h2>
                    <span class="hist-card__note" title="Periodo seleccionado">{{ $periodLabel }}</span>
                </div>

                <div class="hist-donut-wrap">
                    <div class="hist-donut {{ $donut['total'] === 0 ? 'hist-donut--empty' : '' }}" role="img"
                         @if ($donut['total'] > 0) style="background: conic-gradient({{ $donutStops }});" @endif
                         aria-label="Distribución: {{ collect($donut['segments'])->map(fn ($s) => "{$s['label']} {$s['count']} ({$s['percent']}%)")->implode(', ') ?: 'sin datos' }}">
                        <div class="hist-donut__center">
                            <span class="hist-donut__total">{{ $donut['total'] }}</span>
                            <span class="hist-donut__caption">tickets</span>
                        </div>
                    </div>
                    <div class="hist-legend">
                        @foreach ($donut['segments'] as $seg)
                            <div class="hist-legend__row hist-tone-{{ $seg['tone'] }}">
                                <span class="hist-legend__dot" aria-hidden="true"></span>
                                <span class="hist-legend__label">{{ $seg['label'] }}</span>
                                <span class="hist-legend__value">{{ $seg['count'] }} · {{ $seg['percent'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                @if ($donut['total'] === 0)
                    <p class="hist-empty__note hist-panel__empty-note">Sin tickets cerrados en el periodo seleccionado.</p>
                @endif
                <div class="hist-panel__total">
                    <span>Total</span><strong>{{ $donut['total'] }} tickets</strong>
                </div>
            </section>

            {{-- B. Average resolution time --}}
            <section class="hist-panel">
                <h2 class="hist-panel__title">
                    <x-lucide-clock width="16" height="16" stroke-width="2" />
                    Tiempo promedio de resolución
                </h2>
                <div class="hist-avg">
                    <div>
                        <div class="hist-avg__value">{{ $summary['avg_label'] }}</div>
                        <span class="hist-trend__note">
                            {{ $summary['avg_has_data'] ? 'Sobre tus tickets resueltos en el periodo' : 'Aún no hay tickets resueltos en el periodo' }}
                        </span>
                    </div>
                </div>
            </section>

            {{-- C. Top labs --}}
            <section class="hist-panel">
                <h2 class="hist-panel__title">
                    <x-lucide-bar-chart-3 width="16" height="16" stroke-width="2" />
                    Laboratorios con más tickets resueltos
                </h2>
                @if (empty($board->topLabs['items']))
                    <p class="hist-empty__note">Aún no hay tickets resueltos en el periodo.</p>
                @else
                    <div class="hist-bars">
                        @php $peak = max(1, $board->topLabs['peak']); @endphp
                        @foreach ($board->topLabs['items'] as $lab)
                            <div>
                                <div class="hist-bar__head">
                                    <span class="hist-bar__label">{{ $lab['name'] }}</span>
                                    <span class="hist-bar__value">{{ $lab['count'] }}</span>
                                </div>
                                <div class="hist-bar__track">
                                    <div class="hist-bar__fill" style="width: {{ round($lab['count'] / $peak * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- D. History activity --}}
            <section class="hist-panel">
                <h2 class="hist-panel__title">
                    <x-lucide-history width="16" height="16" stroke-width="2" />
                    Actividad del historial
                </h2>
                @if (empty($board->activity))
                    <p class="hist-empty__note">Sin actividad de cierre registrada en el periodo.</p>
                @else
                    <div class="hist-timeline">
                        @foreach ($board->activity as $event)
                            <div class="hist-event hist-tone-{{ $event['tone'] }}">
                                <div class="hist-event__rail">
                                    <span class="hist-event__dot">
                                        <x-dynamic-component :component="'lucide-' . $event['icon']" width="15" height="15" stroke-width="2" />
                                    </span>
                                    <span class="hist-event__line" aria-hidden="true"></span>
                                </div>
                                <div class="hist-event__body">
                                    <div class="hist-event__text">Ticket <strong>{{ $event['ref'] }}</strong> {{ $event['text'] }}</div>
                                    <span class="hist-event__at">{{ $event['at'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="hist-panel__foot">
                    <a href="{{ route('tickets.history', ['result' => 'all']) }}" class="hist-link">
                        Ver toda la actividad
                        <x-lucide-chevron-right width="14" height="14" stroke-width="2.5" />
                    </a>
                </div>
            </section>

        </aside>
    </div>
</div>

{{-- ============================================================
   Progressive JS — select auto-submit + kebab menus.
   Filtering, chips and pagination all work server-side without JS.
   ============================================================ --}}
<script>
(function () {
    'use strict';

    // ── Auto-submit filter selects (state, priority, location, category, sort) ─
    document.querySelectorAll('[data-hist-autosubmit]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (sel.form) { sel.form.submit(); }
        });
    });

    // ── Kebab menus ──────────────────────────────────────────────
    var kebabs = Array.prototype.slice.call(document.querySelectorAll('[data-hist-kebab]'));
    function closeAll(except) {
        kebabs.forEach(function (k) {
            if (k === except) { return; }
            var menu = k.querySelector('[data-hist-kebab-menu]');
            var btn = k.querySelector('[data-hist-kebab-btn]');
            if (menu) { menu.hidden = true; }
            if (btn) { btn.setAttribute('aria-expanded', 'false'); }
        });
    }
    kebabs.forEach(function (kebab) {
        var btn = kebab.querySelector('[data-hist-kebab-btn]');
        var menu = kebab.querySelector('[data-hist-kebab-menu]');
        if (!btn || !menu) { return; }
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = !menu.hidden;
            closeAll(kebab);
            menu.hidden = open;
            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
    });
    document.addEventListener('click', function () { closeAll(null); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(null); } });
})();
</script>
@endsection
