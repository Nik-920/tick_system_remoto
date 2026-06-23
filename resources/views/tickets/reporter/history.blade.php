@extends('layouts.app')

@section('title', 'Historial de mis tickets')

@section('content')
@php
    /** @var \App\ViewModels\Tickets\ReporterTicketHistoryViewModel $board */
    $f = $board->filters;
    $searchValue = (string) ($f['search'] ?? '');
    $priorityValue = (string) ($f['priority'] ?? '');
    $locationValue = (string) ($f['location_id'] ?? '');
    $categoryValue = (string) ($f['category_id'] ?? '');
    $fromValue = (string) ($f['from'] ?? '');
    $toValue = (string) ($f['to'] ?? '');

    $chipBase = collect(request()->query())->except(['status', 'page'])->all();
    $pageBase = collect(request()->query())->except(['page'])->all();

    $sortOptions = ['recent' => 'Más recientes', 'oldest' => 'Más antiguos', 'priority' => 'Prioridad'];

    $hasDonut = $board->summary['total'] > 0;
    $donutStops = collect($board->summary['donut'])
        ->map(fn ($s) => "{$s['color']} {$s['start']}% {$s['end']}%")
        ->implode(', ');
    $donutAria = collect($board->summary['donut'])
        ->map(fn ($s) => "{$s['label']} {$s['count']} ({$s['percent']}%)")
        ->implode(', ');


    $resultLabels = ['resolved' => 'resueltos', 'rejected' => 'rechazados', 'cancelled' => 'cancelados'];
@endphp

<div class="rep-page">

    {{-- ── 1. HEADER ─────────────────────────────────────────────── --}}
    <header class="rep-header">
        <div>
            <h1 class="rep-header__title">Historial de mis tickets</h1>
            <p class="rep-header__subtitle">
                Consulta el historial de todos los tickets que reportaste y que ya fueron cerrados.
            </p>
        </div>
        <div class="rep-header__actions">
            {{-- Visual placeholder: real export lands in a later phase. --}}
            <button type="button" class="rep-btn-outline" title="Exportación disponible próximamente">
                <x-lucide-download width="16" height="16" stroke-width="2.5" />
                Exportar
                <span class="rep-soon">Próximamente</span>
            </button>
        </div>
    </header>

    {{-- ── 2. SEARCH + FILTERS + QUICK CHIPS ─────────────────────── --}}
    <form method="GET" action="{{ route('reporter.tickets.history') }}" class="rep-toolbar" aria-label="Buscar y filtrar mi historial">
        <div class="rep-toolbar__row">
            <div class="rep-search">
                <span class="rep-search__icon" aria-hidden="true">
                    <x-lucide-search width="18" height="18" stroke-width="2" />
                </span>
                <label for="rep-hist-search" class="sr-only">Buscar en mi historial</label>
                <input id="rep-hist-search" type="search" name="search" value="{{ $searchValue }}" class="rep-search__input"
                       placeholder="Buscar por título, ID, descripción..."
                       aria-label="Buscar por título, ID o descripción">
            </div>

            <details class="rep-advanced" @if ($board->hasOtherFilters()) open @endif>
                <summary class="rep-advanced__toggle">
                    <x-lucide-filter width="16" height="16" stroke-width="2" />
                    Filtros
                    <x-lucide-chevron-down class="rep-advanced__chevron" width="15" height="15" stroke-width="2.5" />
                </summary>
                <div class="rep-advanced__panel">
                    <div class="rep-field">
                        <label for="rep-hist-result" class="rep-field__label">Resultado</label>
                        <select id="rep-hist-result" name="status" class="rep-control">
                            <option value="all" @selected($board->activeResult === 'all')>Todos</option>
                            <option value="resolved" @selected($board->activeResult === 'resolved')>Resuelto</option>
                            <option value="rejected" @selected($board->activeResult === 'rejected')>Rechazado</option>
                            <option value="cancelled" @selected($board->activeResult === 'cancelled')>Cancelado</option>
                        </select>
                    </div>
                    <div class="rep-field">
                        <label for="rep-hist-priority" class="rep-field__label">Prioridad</label>
                        <select id="rep-hist-priority" name="priority" class="rep-control">
                            <option value="">Todas</option>
                            <option value="high" @selected($priorityValue === 'high')>Alta</option>
                            <option value="medium" @selected($priorityValue === 'medium')>Media</option>
                            <option value="low" @selected($priorityValue === 'low')>Baja</option>
                            <option value="critical" @selected($priorityValue === 'critical')>Crítica</option>
                        </select>
                    </div>
                    <div class="rep-field">
                        <label for="rep-hist-lab" class="rep-field__label">Laboratorio</label>
                        <select id="rep-hist-lab" name="location_id" class="rep-control">
                            <option value="">Todos</option>
                            @foreach ($board->locations as $location)
                                <option value="{{ $location->id }}" @selected($locationValue === (string) $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rep-field">
                        <label for="rep-hist-category" class="rep-field__label">Categoría</label>
                        <select id="rep-hist-category" name="category_id" class="rep-control">
                            <option value="">Todas</option>
                            @foreach ($board->categories as $category)
                                <option value="{{ $category->id }}" @selected($categoryValue === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rep-field">
                        <label for="rep-hist-sort" class="rep-field__label">Ordenar por</label>
                        <select id="rep-hist-sort" name="sort" class="rep-control">
                            @foreach ($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected($board->sort === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rep-field">
                        <label for="rep-hist-from" class="rep-field__label">Desde</label>
                        <input id="rep-hist-from" type="date" name="from" value="{{ $fromValue }}" class="rep-control">
                    </div>
                    <div class="rep-field">
                        <label for="rep-hist-to" class="rep-field__label">Hasta</label>
                        <input id="rep-hist-to" type="date" name="to" value="{{ $toValue }}" class="rep-control">
                    </div>
                    <div class="rep-advanced__actions">
                        <a href="{{ route('reporter.tickets.history') }}" class="rep-advanced__clear">Limpiar</a>
                        <button type="submit" class="rep-btn-outline rep-advanced__apply">Aplicar filtros</button>
                    </div>
                </div>
            </details>
        </div>

        <div class="rep-chips" role="group" aria-label="Filtros rápidos por resultado">
            @foreach ($board->chips as $chip)
                <a href="{{ route('reporter.tickets.history', $chip['key'] === 'all' ? $chipBase : array_merge($chipBase, ['status' => $chip['key']])) }}"
                   class="rep-chip rep-tone-{{ $chip['tone'] }}"
                   @if ($chip['active']) aria-current="true" @endif>
                    <span class="rep-chip__dot" aria-hidden="true"></span>
                    {{ $chip['label'] }}
                    <span class="rep-chip__count">{{ $chip['count'] }}</span>
                </a>
            @endforeach
            <a href="{{ route('reporter.tickets.history') }}" class="rep-chips__clear">
                <x-lucide-x width="14" height="14" stroke-width="2.5" />
                Limpiar filtros
            </a>
        </div>
    </form>

    {{-- ── 3. CONTENT LAYOUT: history list + insights rail ───────── --}}
    <div class="rep-layout">

        {{-- LEFT: history list --}}
        <div class="rep-main">
            <div class="rep-list">
                @forelse ($board->tickets as $t)
                    @include('tickets.reporter.partials.history-card', ['t' => $t, 'loopIndex' => $loop->index])
                @empty
                    <div class="rep-empty">
                        @if (! $board->hasAnyHistory())
                            <x-lucide-history width="34" height="34" stroke-width="1.5" />
                            <p class="rep-empty__title">Aún no tienes historial</p>
                            <p class="rep-empty__note">Cuando tus tickets se resuelvan, se rechacen o los canceles aparecerán aquí.</p>
                            <a href="{{ route('reporter.tickets.index') }}" class="rep-btn rep-btn--ghost">Ver mis tickets</a>
                        @elseif ($board->hasOtherFilters())
                            <x-lucide-search-x width="34" height="34" stroke-width="1.5" />
                            <p class="rep-empty__title">Sin resultados</p>
                            <p class="rep-empty__note">Ningún ticket del historial coincide con los filtros actuales.</p>
                            <a href="{{ route('reporter.tickets.history') }}" class="rep-btn rep-btn--ghost">Limpiar filtros</a>
                        @else
                            <x-lucide-history width="34" height="34" stroke-width="1.5" />
                            <p class="rep-empty__title">Sin tickets {{ $resultLabels[$board->activeResult] ?? '' }}</p>
                            <p class="rep-empty__note">No tienes tickets {{ $resultLabels[$board->activeResult] ?? '' }} en tu historial.</p>
                            <a href="{{ route('reporter.tickets.history') }}" class="rep-btn rep-btn--ghost">Volver a Todos</a>
                        @endif
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            @if ($board->pagination['total'] > 0)
                <div class="rep-list-foot">
                    <span class="rep-list-foot__count">
                        Mostrando {{ $board->pagination['from'] }} a {{ $board->pagination['to'] }} de {{ $board->pagination['total'] }} tickets
                    </span>
                    @if ($board->pagination['last'] > 1)
                        <nav class="rep-pagination" aria-label="Paginación de mi historial">
                            @if ($board->pagination['current'] > 1)
                                <a href="{{ route('reporter.tickets.history', array_merge($pageBase, ['page' => $board->pagination['current'] - 1])) }}" class="rep-page-btn" rel="prev" aria-label="Página anterior">
                                    <x-lucide-chevron-left width="15" height="15" stroke-width="2.5" />
                                    Anterior
                                </a>
                            @else
                                <span class="rep-page-btn" aria-disabled="true" aria-label="Página anterior">
                                    <x-lucide-chevron-left width="15" height="15" stroke-width="2.5" />
                                    Anterior
                                </span>
                            @endif

                            @foreach ($board->pagination['pages'] as $page)
                                <a href="{{ route('reporter.tickets.history', array_merge($pageBase, ['page' => $page])) }}" class="rep-page-btn"
                                   aria-label="Página {{ $page }}{{ $board->pagination['current'] === $page ? ', página actual' : '' }}"
                                   @if ($board->pagination['current'] === $page) aria-current="page" @endif>{{ $page }}</a>
                            @endforeach

                            @if ($board->pagination['current'] < $board->pagination['last'])
                                <a href="{{ route('reporter.tickets.history', array_merge($pageBase, ['page' => $board->pagination['current'] + 1])) }}" class="rep-page-btn" rel="next" aria-label="Página siguiente">
                                    Siguiente
                                    <x-lucide-chevron-right width="15" height="15" stroke-width="2.5" />
                                </a>
                            @else
                                <span class="rep-page-btn" aria-disabled="true" aria-label="Página siguiente">
                                    Siguiente
                                    <x-lucide-chevron-right width="15" height="15" stroke-width="2.5" />
                                </span>
                            @endif
                        </nav>
                    @endif
                </div>
            @endif
        </div>

        {{-- RIGHT: insights rail --}}
        <aside class="rep-rail" aria-label="Resumen de mi historial">

            {{-- A. Closure donut --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-pie-chart width="16" height="16" stroke-width="2" />
                    Resumen de mi historial
                </h2>

                <div class="rep-donut-wrap">
                    <div class="rep-donut {{ $hasDonut ? '' : 'rep-donut--empty' }}" role="img"
                         @if ($hasDonut) style="background: conic-gradient({{ $donutStops }});" @endif
                         aria-label="Distribución por resultado: {{ $hasDonut ? $donutAria : 'sin tickets' }}">
                        <div class="rep-donut__center">
                            <span class="rep-donut__total">{{ $board->summary['total'] }}</span>
                            <span class="rep-donut__caption">tickets</span>
                        </div>
                    </div>
                    <div class="rep-legend">
                        @foreach ($board->summary['donut'] as $seg)
                            <div class="rep-legend__row rep-tone-{{ $seg['tone'] }}">
                                <span class="rep-legend__dot" aria-hidden="true"></span>
                                <span class="rep-legend__label">{{ $seg['label'] }}</span>
                                <span class="rep-legend__value">{{ $seg['count'] }} · {{ $seg['percent'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rep-panel__total">
                    <span>Total</span><strong>{{ $board->summary['total'] }} tickets</strong>
                </div>
            </section>

            {{-- B. Average resolution time --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-clock width="16" height="16" stroke-width="2" />
                    Tiempo promedio de resolución
                </h2>
                <div class="rep-avg">
                    <div class="rep-avg__icon rep-tone-purple">
                        <x-lucide-clock width="20" height="20" stroke-width="2" />
                    </div>
                    <div>
                        <div class="rep-avg__value">{{ $board->summary['avg_value'] }}</div>
                        <span class="rep-avg__note">{{ $board->summary['avg_note'] }}</span>
                    </div>
                </div>
            </section>


            {{-- D. Tip (static) --}}
            <section class="rep-panel rep-advice">
                <div class="rep-advice__icon rep-tone-purple">
                    <x-lucide-lightbulb width="20" height="20" stroke-width="2" />
                </div>
                <div class="rep-advice__body">
                    <h2 class="rep-panel__title rep-advice__title">Consejo</h2>
                    <p class="rep-advice__text">Revisa tus tickets pasados para describir mejor los nuevos reportes y evitar duplicados.</p>
                    <a href="{{ route('reporter.guide') }}" class="rep-link">
                        Ver guía de reporte
                        <x-lucide-chevron-right width="14" height="14" stroke-width="2.5" />
                    </a>
                </div>
            </section>

        </aside>
    </div>
</div>

{{-- ============================================================
   Progressive JS — kebab menus only. Search, chips, filters and
   pagination all work server-side without JS.
   ============================================================ --}}
{{-- Comments modal — single instance; JS populates it dynamically. --}}
@include('tickets.reporter.partials.comments-modal')

<script>
(function () {
    'use strict';

    var kebabs = Array.prototype.slice.call(document.querySelectorAll('[data-rep-kebab]'));
    function closeAll(except) {
        kebabs.forEach(function (k) {
            if (k === except) { return; }
            var menu = k.querySelector('[data-rep-kebab-menu]');
            var btn = k.querySelector('[data-rep-kebab-btn]');
            if (menu) { menu.hidden = true; }
            if (btn) { btn.setAttribute('aria-expanded', 'false'); }
        });
    }
    kebabs.forEach(function (kebab) {
        var btn = kebab.querySelector('[data-rep-kebab-btn]');
        var menu = kebab.querySelector('[data-rep-kebab-menu]');
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
