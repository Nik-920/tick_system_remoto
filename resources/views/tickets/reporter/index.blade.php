@extends('layouts.app')

@section('title', 'Mis tickets')

@section('content')
@php
    /** @var \App\ViewModels\Tickets\ReporterTicketsBoardViewModel $board */
    $f = $board->filters;
    $searchValue = (string) ($f['search'] ?? '');
    $priorityValue = (string) ($f['priority'] ?? '');
    $locationValue = (string) ($f['location_id'] ?? '');
    $categoryValue = (string) ($f['category_id'] ?? '');
    $fromValue = (string) ($f['from'] ?? '');
    $toValue = (string) ($f['to'] ?? '');

    // Querystring carried when switching the status chip (drop volatile keys).
    $chipBase = collect(request()->query())->except(['status', 'page'])->all();
    $pageBase = collect(request()->query())->except(['page'])->all();

    $sortOptions = ['recent' => 'Más recientes', 'oldest' => 'Más antiguos', 'priority' => 'Prioridad'];

    // Build the donut conic-gradient from cumulative ring percentages.
    $donutStops = collect($board->summary['donut'])
        ->map(fn ($s) => "{$s['color']} {$s['start']}% {$s['end']}%")
        ->implode(', ');
    $donutAria = collect($board->summary['donut'])
        ->map(fn ($s) => "{$s['label']} {$s['count']} ({$s['percent']}%)")
        ->implode(', ');
    $hasDonut = $board->summary['total'] > 0;
@endphp

<div class="rep-page">

    {{-- ── 1. HEADER ─────────────────────────────────────────────── --}}
    <header class="rep-header">
        <div>
            <h1 class="rep-header__title">Mis tickets</h1>
            <p class="rep-header__subtitle">
                Consulta y da seguimiento a todas las incidencias que has reportado.
            </p>
        </div>
        <div class="rep-header__actions">
            <a href="{{ request()->fullUrl() }}" class="rep-btn-outline">
                <x-lucide-refresh-cw width="16" height="16" stroke-width="2.5" />
                Actualizar
            </a>
            <a href="{{ route('tickets.create') }}" class="rep-btn-primary">
                <x-lucide-plus width="17" height="17" stroke-width="2.5" />
                Crear nuevo ticket
            </a>
        </div>
    </header>

    {{-- ── 2. SEARCH + FILTERS + QUICK CHIPS ─────────────────────── --}}
    {{-- Real GET form. Everything is applied server-side AFTER the reporter_id
         scope; the page works without JS. --}}
    <form method="GET" action="{{ route('reporter.tickets.index') }}" class="rep-toolbar" aria-label="Buscar y filtrar mis tickets">
        {{-- Preserve the active status chip when submitting search / filters. --}}
        @if ($board->activeStatus !== 'all')
            <input type="hidden" name="status" value="{{ $board->activeStatus }}">
        @endif

        <div class="rep-toolbar__row">
            <div class="rep-search">
                <span class="rep-search__icon" aria-hidden="true">
                    <x-lucide-search width="18" height="18" stroke-width="2" />
                </span>
                <label for="rep-search" class="sr-only">Buscar mis tickets</label>
                <input id="rep-search" type="search" name="search" value="{{ $searchValue }}" class="rep-search__input"
                       placeholder="Buscar por título, ID, laboratorio, categoría, estado..."
                       aria-label="Buscar por título, ID, descripción">
            </div>

            {{-- Native <details> = collapsible advanced filters that work without JS. --}}
            <details class="rep-advanced" @if ($board->hasOtherFilters()) open @endif>
                <summary class="rep-advanced__toggle">
                    <x-lucide-filter width="16" height="16" stroke-width="2" />
                    Filtros
                    <x-lucide-chevron-down class="rep-advanced__chevron" width="15" height="15" stroke-width="2.5" />
                </summary>
                <div class="rep-advanced__panel">
                    <div class="rep-field">
                        <label for="rep-f-priority" class="rep-field__label">Prioridad</label>
                        <select id="rep-f-priority" name="priority" class="rep-control">
                            <option value="">Todas</option>
                            <option value="high" @selected($priorityValue === 'high')>Alta</option>
                            <option value="medium" @selected($priorityValue === 'medium')>Media</option>
                            <option value="low" @selected($priorityValue === 'low')>Baja</option>
                            <option value="critical" @selected($priorityValue === 'critical')>Crítica</option>
                        </select>
                    </div>
                    <div class="rep-field">
                        <label for="rep-f-lab" class="rep-field__label">Laboratorio</label>
                        <select id="rep-f-lab" name="location_id" class="rep-control">
                            <option value="">Todos</option>
                            @foreach ($board->locations as $location)
                                <option value="{{ $location->id }}" @selected($locationValue === (string) $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rep-field">
                        <label for="rep-f-category" class="rep-field__label">Categoría</label>
                        <select id="rep-f-category" name="category_id" class="rep-control">
                            <option value="">Todas</option>
                            @foreach ($board->categories as $category)
                                <option value="{{ $category->id }}" @selected($categoryValue === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rep-field">
                        <label for="rep-f-sort" class="rep-field__label">Ordenar por</label>
                        <select id="rep-f-sort" name="sort" class="rep-control">
                            @foreach ($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected($board->sort === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rep-field">
                        <label for="rep-f-from" class="rep-field__label">Desde</label>
                        <input id="rep-f-from" type="date" name="from" value="{{ $fromValue }}" class="rep-control">
                    </div>
                    <div class="rep-field">
                        <label for="rep-f-to" class="rep-field__label">Hasta</label>
                        <input id="rep-f-to" type="date" name="to" value="{{ $toValue }}" class="rep-control">
                    </div>
                    <div class="rep-advanced__actions">
                        <a href="{{ route('reporter.tickets.index') }}" class="rep-advanced__clear">Limpiar</a>
                        <button type="submit" class="rep-btn-outline rep-advanced__apply">Aplicar filtros</button>
                    </div>
                </div>
            </details>
        </div>

        {{-- Quick chips: server-side links (preserve the rest of the query). --}}
        <div class="rep-chips" role="group" aria-label="Filtros rápidos por estado">
            @foreach ($board->chips as $chip)
                <a href="{{ route('reporter.tickets.index', $chip['key'] === 'all' ? $chipBase : array_merge($chipBase, ['status' => $chip['key']])) }}"
                   class="rep-chip rep-tone-{{ $chip['tone'] }}"
                   @if ($chip['active']) aria-current="true" @endif>
                    <span class="rep-chip__dot" aria-hidden="true"></span>
                    {{ $chip['label'] }}
                    <span class="rep-chip__count">{{ $chip['count'] }}</span>
                </a>
            @endforeach
        </div>
    </form>

    {{-- ── 3. CONTENT LAYOUT: ticket list + insights rail ────────── --}}
    <div class="rep-layout">

        {{-- LEFT: ticket inbox --}}
        <div class="rep-main">
            <div class="rep-list">
                @forelse ($board->tickets as $t)
                    <article class="rep-item rep-tone-{{ $t['status_tone'] }}" aria-labelledby="rep-row-title-{{ $loop->index }}">
                        <span class="rep-item__rail" aria-hidden="true"></span>

                        <div class="rep-item__icon">
                            <x-dynamic-component :component="'lucide-' . $t['icon']" width="20" height="20" stroke-width="2" />
                        </div>

                        <div class="rep-item__body">
                            <div class="rep-item__top">
                                <h3 class="rep-item__title" id="rep-row-title-{{ $loop->index }}">{{ $t['title'] }}</h3>
                                <span class="rep-item__id">{{ $t['ref'] }}</span>
                            </div>

                            <div class="rep-item__meta">
                                <x-lucide-map-pin width="13" height="13" stroke-width="2" />
                                <span>{{ $t['location'] }}</span>
                                <span class="rep-item__meta-sep" aria-hidden="true"></span>
                                <span>{{ $t['category'] }}</span>
                                @if ($t['type'] !== '')
                                    <span class="rep-item__meta-sep" aria-hidden="true"></span>
                                    <span>{{ $t['type'] }}</span>
                                @endif
                            </div>

                            <div class="rep-item__tags">
                                <span class="rep-badge rep-status--{{ $t['status'] }}">
                                    <span class="rep-badge__dot" aria-hidden="true"></span>
                                    {{ $t['status_label'] }}
                                </span>
                                <span class="rep-prio-wrap">
                                    <span class="rep-prio-label">Prioridad</span>
                                    <span class="rep-badge rep-prio rep-tone-{{ $t['priority'] }}">
                                        <span class="rep-badge__dot" aria-hidden="true"></span>
                                        {{ $t['priority_label'] }}
                                    </span>
                                </span>
                            </div>
                        </div>

                        <div class="rep-item__side">
                            <div class="rep-item__update">
                                <x-lucide-clock width="13" height="13" stroke-width="2" />
                                <span class="rep-item__update-label">Actualización</span>
                                <span class="rep-item__update-at">{{ $t['updated'] }}</span>
                            </div>

                            <div class="rep-item__actions">
                                {{-- Reporter tracking screen (resolves only own tickets). --}}
                                <a href="{{ route('reporter.tickets.show', $t['id']) }}"
                                   class="rep-btn {{ $t['action'] === 'follow' ? 'rep-btn--primary' : 'rep-btn--ghost' }}">
                                    {{ $t['action_label'] }}
                                </a>

                                <div class="rep-kebab" data-rep-kebab>
                                    <button type="button" class="rep-kebab__btn"
                                            aria-label="Más acciones para {{ $t['title'] }}"
                                            aria-haspopup="true" aria-expanded="false" data-rep-kebab-btn>
                                        <x-lucide-more-vertical width="18" height="18" stroke-width="2" />
                                    </button>
                                    <div class="rep-kebab__menu" hidden data-rep-kebab-menu>
                                        <a href="{{ route('reporter.tickets.show', $t['id']) }}" class="rep-kebab__item">
                                            <x-lucide-eye width="15" height="15" stroke-width="2" /> {{ $t['action_label'] }}
                                        </a>
                                        <a href="{{ route('reporter.tickets.show', $t['id']) }}#rep-timeline" class="rep-kebab__item">
                                            <x-lucide-clock width="15" height="15" stroke-width="2" /> Ver cronología
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rep-empty">
                        @if (! $board->hasAnyTickets())
                            <x-lucide-inbox width="34" height="34" stroke-width="1.5" />
                            <p class="rep-empty__title">Aún no has reportado incidencias</p>
                            <p class="rep-empty__note">Cuando crees un ticket aparecerá aquí para que le des seguimiento.</p>
                            <a href="{{ route('tickets.create') }}" class="rep-btn-primary">
                                <x-lucide-plus width="16" height="16" stroke-width="2.5" />
                                Crear ticket
                            </a>
                        @elseif ($board->hasOtherFilters())
                            <x-lucide-search-x width="34" height="34" stroke-width="1.5" />
                            <p class="rep-empty__title">Sin resultados</p>
                            <p class="rep-empty__note">No hay tickets que coincidan con los filtros actuales.</p>
                            <a href="{{ route('reporter.tickets.index') }}" class="rep-btn rep-btn--ghost">Limpiar filtros</a>
                        @else
                            <x-lucide-inbox width="34" height="34" stroke-width="1.5" />
                            <p class="rep-empty__title">Sin tickets en esta vista</p>
                            <p class="rep-empty__note">No tienes tickets en este estado por ahora.</p>
                            <a href="{{ route('reporter.tickets.index') }}" class="rep-btn rep-btn--ghost">Volver a Todos</a>
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
                        <nav class="rep-pagination" aria-label="Paginación de mis tickets">
                            @if ($board->pagination['current'] > 1)
                                <a href="{{ route('reporter.tickets.index', array_merge($pageBase, ['page' => $board->pagination['current'] - 1])) }}" class="rep-page-btn" rel="prev" aria-label="Página anterior">
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
                                <a href="{{ route('reporter.tickets.index', array_merge($pageBase, ['page' => $page])) }}" class="rep-page-btn"
                                   aria-label="Página {{ $page }}{{ $board->pagination['current'] === $page ? ', página actual' : '' }}"
                                   @if ($board->pagination['current'] === $page) aria-current="page" @endif>{{ $page }}</a>
                            @endforeach

                            @if ($board->pagination['current'] < $board->pagination['last'])
                                <a href="{{ route('reporter.tickets.index', array_merge($pageBase, ['page' => $board->pagination['current'] + 1])) }}" class="rep-page-btn" rel="next" aria-label="Página siguiente">
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
        <aside class="rep-rail" aria-label="Resumen de mis tickets">

            {{-- A. Status donut --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-pie-chart width="16" height="16" stroke-width="2" />
                    Resumen de mis tickets
                </h2>

                <div class="rep-donut-wrap">
                    <div class="rep-donut {{ $hasDonut ? '' : 'rep-donut--empty' }}" role="img"
                         @if ($hasDonut) style="background: conic-gradient({{ $donutStops }});" @endif
                         aria-label="Distribución por estado: {{ $hasDonut ? $donutAria : 'sin tickets' }}">
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

            {{-- B. Average first-response time --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-clock width="16" height="16" stroke-width="2" />
                    Tiempo promedio de respuesta
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

            {{-- C. Tickets per lab --}}
            <section class="rep-panel">
                <h2 class="rep-panel__title">
                    <x-lucide-bar-chart-3 width="16" height="16" stroke-width="2" />
                    Tickets por laboratorio
                </h2>
                @if (empty($board->labs['items']))
                    <p class="rep-empty__note">Aún no hay tickets por laboratorio.</p>
                @else
                    <div class="rep-bars">
                        @php $peak = max(1, (int) $board->labs['peak']); @endphp
                        @foreach ($board->labs['items'] as $lab)
                            <div>
                                <div class="rep-bar__head">
                                    <span class="rep-bar__label">{{ $lab['name'] }}</span>
                                    <span class="rep-bar__value">{{ $lab['count'] }}</span>
                                </div>
                                <div class="rep-bar__track">
                                    <div class="rep-bar__fill" style="width: {{ round($lab['count'] / $peak * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- D. Reporting tip (static) --}}
            <section class="rep-panel rep-advice">
                <div class="rep-advice__icon rep-tone-purple">
                    <x-lucide-lightbulb width="20" height="20" stroke-width="2" />
                </div>
                <div class="rep-advice__body">
                    <h2 class="rep-panel__title rep-advice__title">Consejo para reportar mejor</h2>
                    <p class="rep-advice__text">Agrega fotos, detalles del problema y el lugar exacto para acelerar la atención.</p>
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
   pagination all work server-side without JS (form + <details> + links).
   ============================================================ --}}
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
