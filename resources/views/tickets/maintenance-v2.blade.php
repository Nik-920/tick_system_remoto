@extends('layouts.app')

@section('title', 'Mis tickets y cola disponible')

@section('content')
@php
    /** @var \App\ViewModels\Tickets\MaintenanceBoardViewModel $board */
    $f = $board->filters;
    $searchValue   = (string) ($f['search'] ?? '');
    $stateValue    = (string) ($f['state'] ?? '');
    $priorityValue = (string) ($f['priority'] ?? '');
    $locationValue = (string) ($f['location_id'] ?? '');
    $categoryValue = (string) ($f['category_id'] ?? '');
    $fromValue     = (string) ($f['from'] ?? '');
    $toValue       = (string) ($f['to'] ?? '');
    $duplicatesOn  = ! empty($f['duplicates']);
    $view          = $board->view;

    $stateLabels = [
        'open' => 'Abierto', 'in_progress' => 'En progreso',
        'resolved' => 'Resuelto', 'rejected' => 'Rechazado',
    ];
    $priorityLabels = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Crítica'];
    $priorityTone   = ['low' => 'low', 'medium' => 'medium', 'high' => 'high', 'critical' => 'high'];

    $categoryIcons = [
        'hardware' => 'monitor', 'software' => 'cpu', 'seguridad' => 'shield-alert',
        'mobiliario' => 'armchair', 'equipos' => 'projector', 'conectividad' => 'cable',
        'redes' => 'cable', 'red' => 'cable', 'electricidad' => 'zap',
    ];
    $iconFor = fn (?string $name): string => $categoryIcons[strtolower(trim((string) $name))] ?? 'wrench';

    // Preserve the search term when switching tabs / chips.
    $keep = array_filter(['search' => $searchValue], fn ($v) => $v !== '');

    $tabs = [
        ['key' => 'mine',      'label' => 'Mis tickets'],
        ['key' => 'available', 'label' => 'Disponibles para tomar'],
        ['key' => 'all',       'label' => 'Todos los visibles'],
    ];

    $c = $board->chipCounts;
    $chips = [
        ['key' => 'all',         'label' => 'Todos',               'count' => $c['all'],         'tone' => 'neutral',   'params' => ['view' => 'all']],
        ['key' => 'in_progress', 'label' => 'En progreso',         'count' => $c['in_progress'], 'tone' => 'progress',  'params' => ['view' => 'all', 'state' => 'in_progress']],
        ['key' => 'high',        'label' => 'Alta prioridad',      'count' => $c['high'],        'tone' => 'high',      'params' => ['view' => 'all', 'priority' => 'high']],
        ['key' => 'available',   'label' => 'Disponibles',         'count' => $c['available'],   'tone' => 'available', 'params' => ['view' => 'available']],
        ['key' => 'duplicates',  'label' => 'Posibles duplicados', 'count' => $c['duplicates'],  'tone' => 'warning',   'params' => ['view' => 'all', 'duplicates' => 1]],
    ];

    $activeChip = '';
    if ($duplicatesOn) {
        $activeChip = 'duplicates';
    } elseif ($view === 'available') {
        $activeChip = 'available';
    } elseif ($stateValue === 'in_progress') {
        $activeChip = 'in_progress';
    } elseif (in_array($priorityValue, ['high', 'critical'], true)) {
        $activeChip = 'high';
    } elseif ($view === 'all' && $stateValue === '' && $priorityValue === '' && $locationValue === '' && $categoryValue === '' && $fromValue === '' && $toValue === '') {
        $activeChip = 'all';
    }

    $advancedOpen = $stateValue !== '' || $priorityValue !== '' || $locationValue !== '' || $categoryValue !== '' || $fromValue !== '' || $toValue !== '';

    $summary = $board->summary;
    $stats = [
        ['label' => 'Tickets visibles',         'value' => $summary['visible'],     'icon' => 'ticket',      'tone' => 'primary'],
        ['label' => 'En progreso',              'value' => $summary['in_progress'], 'icon' => 'clock',       'tone' => 'progress'],
        ['label' => 'Asignados a mí',           'value' => $summary['mine'],        'icon' => 'list-checks', 'tone' => 'info'],
        ['label' => 'Disponibles para tomar',   'value' => $summary['available'],   'icon' => 'inbox',       'tone' => 'available'],
    ];

    $insights = $board->insights;
    [$donutAlta, $donutMedia] = $board->donutStops();
@endphp

<div class="tkv2-page">

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    {{-- HEADER --}}
    <header class="tkv2-header">
        <div>
            <h1 class="tkv2-header__title">Mis tickets y cola disponible</h1>
            <p class="tkv2-header__subtitle">
                Gestiona tus tickets asignados y toma incidencias disponibles para iniciar atención.
            </p>
        </div>
        <div class="tkv2-header__actions">
            <a href="{{ route('tickets.create') }}" class="btn-primary">
                <x-lucide-plus width="16" height="16" stroke-width="2.5" />
                Nuevo ticket
            </a>
        </div>
    </header>

    {{-- QUICK-VIEW TABS --}}
    <div class="tkv2-tabs" role="tablist" aria-label="Vista rápida de tickets">
        @foreach ($tabs as $tab)
            <a href="{{ route('tickets.index', array_merge($keep, ['view' => $tab['key']])) }}"
               class="tkv2-tab"
               role="tab"
               aria-selected="{{ $view === $tab['key'] ? 'true' : 'false' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>

    {{-- SEARCH + ADVANCED FILTERS (real GET form) --}}
    <form method="GET" action="{{ route('tickets.index') }}" class="tkv2-toolbar-form">
        <input type="hidden" name="view" value="{{ $view }}">
        @if ($duplicatesOn)
            <input type="hidden" name="duplicates" value="1">
        @endif

        <div class="tkv2-toolbar">
            <div class="tkv2-search">
                <span class="tkv2-search__icon" aria-hidden="true">
                    <x-lucide-search width="18" height="18" stroke-width="2" />
                </span>
                <label for="tkv2-search" class="sr-only">Buscar tickets</label>
                <input id="tkv2-search" type="search" name="search" value="{{ $searchValue }}"
                       class="tkv2-search__input"
                       placeholder="Buscar por título, descripción, laboratorio...">
            </div>
            <button type="button"
                    class="tkv2-filters-toggle"
                    aria-expanded="{{ $advancedOpen ? 'true' : 'false' }}"
                    aria-controls="tkv2-advanced"
                    data-tkv2-filters-toggle>
                <x-lucide-sliders-horizontal width="16" height="16" stroke-width="2" />
                Filtros avanzados
                <x-lucide-chevron-down class="tkv2-filters-toggle__chevron" width="15" height="15" stroke-width="2.5" />
            </button>
            <button type="submit" class="btn-primary tkv2-toolbar-submit">Buscar</button>
        </div>

        <section id="tkv2-advanced" class="tkv2-advanced" @unless ($advancedOpen) hidden @endunless aria-label="Filtros avanzados">
            <div class="tkv2-advanced__grid">
                <div>
                    <label class="tkv2-field-label" for="tkv2-f-state">Estado</label>
                    <select id="tkv2-f-state" name="state" class="tkv2-field">
                        <option value="">Todos</option>
                        @foreach ($stateLabels as $value => $label)
                            <option value="{{ $value }}" @selected($stateValue === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="tkv2-field-label" for="tkv2-f-priority">Prioridad</label>
                    <select id="tkv2-f-priority" name="priority" class="tkv2-field">
                        <option value="">Todas</option>
                        @foreach ($priorityLabels as $value => $label)
                            <option value="{{ $value }}" @selected($priorityValue === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="tkv2-field-label" for="tkv2-f-location">Laboratorio / ubicación</label>
                    <select id="tkv2-f-location" name="location_id" class="tkv2-field">
                        <option value="">Todas</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected($locationValue === (string) $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="tkv2-field-label" for="tkv2-f-category">Categoría</label>
                    <select id="tkv2-f-category" name="category_id" class="tkv2-field">
                        <option value="">Todas</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($categoryValue === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="tkv2-field-label" for="tkv2-f-from">Desde</label>
                    <input id="tkv2-f-from" type="date" name="from" value="{{ $fromValue }}" class="tkv2-field">
                </div>
                <div>
                    <label class="tkv2-field-label" for="tkv2-f-to">Hasta</label>
                    <input id="tkv2-f-to" type="date" name="to" value="{{ $toValue }}" class="tkv2-field">
                </div>
            </div>
        </section>
    </form>

    {{-- QUICK CHIPS --}}
    <div class="tkv2-chips" role="group" aria-label="Filtros rápidos">
        @foreach ($chips as $chip)
            <a href="{{ route('tickets.index', array_merge($keep, $chip['params'])) }}"
               class="tkv2-chip tkv2-tone-{{ $chip['tone'] }}"
               aria-pressed="{{ $activeChip === $chip['key'] ? 'true' : 'false' }}">
                {{ $chip['label'] }}
                <span class="tkv2-chip__count">{{ $chip['count'] }}</span>
            </a>
        @endforeach
        <a href="{{ route('tickets.index') }}" class="tkv2-chips__clear">
            <x-lucide-x width="14" height="14" stroke-width="2.5" />
            Limpiar filtros
        </a>
    </div>

    {{-- SUMMARY STAT CARDS --}}
    <div class="tkv2-summary">
        @foreach ($stats as $stat)
            <div class="tkv2-stat">
                <div class="tkv2-stat__icon tkv2-tone-{{ $stat['tone'] }}">
                    <x-dynamic-component :component="'lucide-' . $stat['icon']" width="22" height="22" stroke-width="2" />
                </div>
                <div>
                    <div class="tkv2-stat__value">{{ $stat['value'] }}</div>
                    <div class="tkv2-stat__label">{{ $stat['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- LAYOUT: queue + insights --}}
    <div class="tkv2-layout">

        {{-- LEFT: prioritised queue --}}
        <div class="tkv2-main">
            <div class="tkv2-section-head">
                <h2 class="tkv2-section-title">Cola priorizada</h2>
                <span class="tkv2-section-hint">Mostrando {{ $board->queue->count() }} de {{ $board->queueTotal }}</span>
            </div>

            <div class="tkv2-queue">
                @forelse ($board->queue as $ticket)
                    @php
                        $tone      = $priorityTone[$ticket->priority] ?? 'medium';
                        $isMine    = $ticket->assigned_to === $board->technicianId;
                        $isDup     = $ticket->embedding && $ticket->embedding->effective_duplicate;
                        // Softer signal: reporter confirmed a precheck candidate was
                        // distinct. Only surfaced when the AI hasn't independently
                        // confirmed a duplicate — see 2026_07_01_000100 migration.
                        $isRelated = $ticket->embedding && $ticket->embedding->hasPrecheckCandidate() && ! $isDup;
                        $catName   = $ticket->category?->name;
                        $roomCode  = $ticket->location?->room_code;
                    @endphp
                    <article class="tkv2-ticket tkv2-tone-{{ $tone }}">
                        <span class="tkv2-ticket__rail" aria-hidden="true"></span>

                        <div class="tkv2-ticket__icon">
                            <x-dynamic-component :component="'lucide-' . $iconFor($catName)" width="20" height="20" stroke-width="2" />
                        </div>

                        <div class="tkv2-ticket__body">
                            <div class="tkv2-ticket__top">
                                <h3 class="tkv2-ticket__title">{{ $ticket->title }}</h3>
                                <span class="tkv2-prio">
                                    <span class="tkv2-prio__dot" aria-hidden="true"></span>
                                    {{ $priorityLabels[$ticket->priority] ?? ucfirst((string) $ticket->priority) }}
                                </span>
                                @if ($isDup)
                                    <span class="tkv2-dup">
                                        <x-lucide-copy width="12" height="12" stroke-width="2" />
                                        Posible duplicado
                                    </span>
                                @elseif ($isRelated)
                                    <span class="tkv2-dup tkv2-dup--info" title="{{ $ticket->embedding->precheck_reason ?? '' }}">
                                        <x-lucide-link width="12" height="12" stroke-width="2" />
                                        Relacionado
                                    </span>
                                @endif
                            </div>

                            <div class="tkv2-ticket__meta">
                                <x-lucide-map-pin width="13" height="13" stroke-width="2" />
                                <span>{{ $ticket->location?->name ?? 'Sin ubicación' }}</span>
                                @if ($catName)
                                    <span class="tkv2-ticket__meta-sep" aria-hidden="true"></span>
                                    <span>{{ $catName }}</span>
                                @endif
                                @if ($roomCode)
                                    <span class="tkv2-ticket__meta-sep" aria-hidden="true"></span>
                                    <span>{{ $roomCode }}</span>
                                @endif
                            </div>

                            @if ($ticket->description)
                                <p class="tkv2-ticket__desc">{{ \Illuminate\Support\Str::limit($ticket->description, 120) }}</p>
                            @endif

                            <div class="tkv2-ticket__tags">
                                <span class="ticket-badge ticket-badge--{{ $ticket->state }}">
                                    {{ $stateLabels[$ticket->state] ?? ucfirst((string) $ticket->state) }}
                                </span>
                                @if ($isMine)
                                    <span class="tkv2-assign tkv2-assign--mine">
                                        <x-lucide-user-check width="12" height="12" stroke-width="2" />
                                        Asignado a mí
                                    </span>
                                    @if ($ticket->assignment_source === \App\Models\Ticket::ASSIGNMENT_SOURCE_SELF)
                                        <span class="tkv2-assign__note">Tomado</span>
                                    @endif
                                @elseif ($ticket->assigned_to === null)
                                    <span class="tkv2-assign tkv2-assign--available">
                                        <x-lucide-inbox width="12" height="12" stroke-width="2" />
                                        Disponible
                                    </span>
                                    <span class="tkv2-assign__note">Para tomar</span>
                                @else
                                    <span class="tkv2-assign__note">{{ $ticket->assignee?->name ?? 'Asignado' }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="tkv2-ticket__side">
                            <span class="tkv2-ticket__date">Creado {{ $ticket->created_at?->format('d/m/Y') }}</span>
                            <div class="tkv2-ticket__actions">
                                @if ($isMine)
                                    <a href="{{ route('tickets.show', $ticket) }}" class="tkv2-btn tkv2-btn--primary">
                                        <x-lucide-wrench width="14" height="14" stroke-width="2" />
                                        Gestionar
                                    </a>
                                @else
                                    <a href="{{ route('tickets.show', $ticket) }}" class="tkv2-btn tkv2-btn--ghost">Ver</a>
                                    @can('claim', $ticket)
                                        <form method="POST" action="{{ route('tickets.claim', $ticket) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                            <button type="submit" class="tkv2-btn tkv2-btn--success">
                                                <x-lucide-hand width="14" height="14" stroke-width="2" />
                                                Tomar
                                            </button>
                                        </form>
                                    @endcan
                                @endif

                                <div class="tkv2-kebab" data-tkv2-kebab>
                                    <button type="button" class="tkv2-kebab__btn"
                                            aria-label="Más acciones para {{ $ticket->title }}"
                                            aria-haspopup="true" aria-expanded="false" data-tkv2-kebab-btn>
                                        <x-lucide-more-vertical width="18" height="18" stroke-width="2" />
                                    </button>
                                    <div class="tkv2-kebab__menu" hidden data-tkv2-kebab-menu>
                                        <a href="{{ route('tickets.show', $ticket) }}" class="tkv2-kebab__item">
                                            <x-lucide-eye width="15" height="15" stroke-width="2" /> Ver detalle
                                        </a>
                                        @if ($isMine)
                                            <a href="{{ route('tickets.show', $ticket) }}" class="tkv2-kebab__item">
                                                <x-lucide-refresh-cw width="15" height="15" stroke-width="2" /> Cambiar estado
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="tkv2-empty">
                        <x-lucide-inbox width="34" height="34" stroke-width="1.5" />
                        <p class="tkv2-empty__title">No hay tickets en esta vista</p>
                        <p class="tkv2-empty__note">Ajusta los filtros o revisa otra pestaña para ver más incidencias.</p>
                        <a href="{{ route('tickets.index') }}" class="tkv2-btn tkv2-btn--ghost">Limpiar filtros</a>
                    </div>
                @endforelse
            </div>

            @if ($view === 'available' && $board->queueTotal > $board->queue->count())
                <div class="tkv2-queue-foot">
                    <a href="{{ route('tickets.available') }}" class="tkv2-link">Ver cola completa de disponibles ({{ $board->queueTotal }})</a>
                </div>
            @endif
        </div>

        {{-- RIGHT: insights --}}
        <aside class="tkv2-insights" aria-label="Insights operativos">

            <div class="tkv2-insights__head">
                <x-lucide-sparkles width="16" height="16" stroke-width="2" />
                <h2 class="tkv2-insights__title">Insights operativos</h2>
            </div>

            {{-- A. Priority donut --}}
            <section class="tkv2-insight">
                <h3 class="tkv2-insight__title">
                    <x-lucide-pie-chart width="16" height="16" stroke-width="2" />
                    Prioridad de la cola
                </h3>
                <div class="tkv2-donut-wrap">
                    @php
                        $donutLabel = collect($insights['priority']['bands'])
                            ->map(fn ($b) => "{$b['label']} {$b['count']} ({$b['percent']}%)")
                            ->implode(', ');
                    @endphp
                    <div class="tkv2-donut {{ $insights['priority']['total'] === 0 ? 'tkv2-donut--empty' : '' }}"
                         role="img" aria-label="Distribución por prioridad: {{ $donutLabel ?: 'sin datos' }}"
                         style="--alta-end: {{ $donutAlta }}%; --media-end: {{ $donutMedia }}%;">
                        <div class="tkv2-donut__center">
                            <span class="tkv2-donut__total">{{ $insights['priority']['total'] }}</span>
                            <span class="tkv2-donut__caption">tickets</span>
                        </div>
                    </div>
                    <div class="tkv2-legend">
                        @foreach ($insights['priority']['bands'] as $band)
                            <div class="tkv2-legend__row tkv2-tone-{{ $band['key'] }}">
                                <span class="tkv2-legend__dot" aria-hidden="true"></span>
                                <span class="tkv2-legend__label">{{ $band['label'] }}</span>
                                <span class="tkv2-legend__value">{{ $band['count'] }} · {{ $band['percent'] }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="tkv2-insight__total">
                    <span>Total</span><strong>{{ $insights['priority']['total'] }} tickets</strong>
                </div>
            </section>

            {{-- B. Labs activity --}}
            <section class="tkv2-insight">
                <h3 class="tkv2-insight__title">
                    <x-lucide-building-2 width="16" height="16" stroke-width="2" />
                    Laboratorios con más actividad
                </h3>
                @if (empty($insights['labs']['items']))
                    <p class="tkv2-insight__empty">Aún no hay actividad registrada.</p>
                @else
                    @php $labPeak = max(1, $insights['labs']['peak']); @endphp
                    <div class="tkv2-bars">
                        @foreach ($insights['labs']['items'] as $lab)
                            <div>
                                <div class="tkv2-bar__head">
                                    <span class="tkv2-bar__label">{{ $lab['name'] }}</span>
                                    <span class="tkv2-bar__value">{{ $lab['count'] }}</span>
                                </div>
                                <div class="tkv2-bar__track">
                                    <div class="tkv2-bar__fill" style="width: {{ round($lab['count'] / $labPeak * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- C. Current state --}}
            <section class="tkv2-insight">
                <h3 class="tkv2-insight__title">
                    <x-lucide-activity width="16" height="16" stroke-width="2" />
                    Estado actual
                </h3>
                <div class="tkv2-states">
                    @php
                        $stateCards = [
                            ['label' => 'Abiertos',      'count' => $insights['states']['open'],           'tone' => 'open'],
                            ['label' => 'En progreso',   'count' => $insights['states']['in_progress'],    'tone' => 'progress'],
                            ['label' => 'Resueltos hoy', 'count' => $insights['states']['resolved_today'], 'tone' => 'resolved'],
                            ['label' => 'Rechazados',    'count' => $insights['states']['rejected'],       'tone' => 'rejected'],
                        ];
                    @endphp
                    @foreach ($stateCards as $card)
                        <div class="tkv2-state">
                            <span class="tkv2-state__dot tkv2-state__dot--{{ $card['tone'] }}" aria-hidden="true"></span>
                            <div>
                                <div class="tkv2-state__value">{{ $card['count'] }}</div>
                                <div class="tkv2-state__label">{{ $card['label'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- D. Duplicates --}}
            <section class="tkv2-insight">
                <h3 class="tkv2-insight__title">
                    <x-lucide-copy width="16" height="16" stroke-width="2" />
                    Duplicados detectados
                </h3>
                <div class="tkv2-dupcard">
                    <div class="tkv2-dupcard__icon">
                        <x-lucide-alert-triangle width="22" height="22" stroke-width="2" />
                    </div>
                    <div>
                        <div class="tkv2-dupcard__count">{{ $insights['duplicates'] }}</div>
                        <div class="tkv2-dupcard__note">{{ $insights['duplicates'] > 0 ? 'Requieren revisión' : 'Sin duplicados activos' }}</div>
                    </div>
                </div>
                @if ($insights['duplicates'] > 0)
                    <a href="{{ route('tickets.index', ['view' => 'all', 'duplicates' => 1]) }}" class="tkv2-btn tkv2-btn--ghost tkv2-dupcard__action">Ver duplicados</a>
                @endif
            </section>

            {{-- E. Average attention time --}}
            <section class="tkv2-insight">
                <h3 class="tkv2-insight__title">
                    <x-lucide-clock width="16" height="16" stroke-width="2" />
                    Tiempo promedio de atención
                </h3>
                <div class="tkv2-avg">
                    <div class="tkv2-avg__icon">
                        <x-lucide-timer width="22" height="22" stroke-width="2" />
                    </div>
                    <div>
                        <div class="tkv2-avg__value">{{ $insights['avg_time']['label'] }}</div>
                        <span class="tkv2-avg__status">{{ $insights['avg_time']['has_data'] ? 'Tickets resueltos' : 'Aún sin cierres' }}</span>
                    </div>
                </div>
            </section>

        </aside>
    </div>
</div>

{{-- Progressive JS: advanced-filters toggle + kebab menus. Page works without it. --}}
<script>
(function () {
    'use strict';

    var filtersBtn = document.querySelector('[data-tkv2-filters-toggle]');
    var advanced = document.getElementById('tkv2-advanced');
    if (filtersBtn && advanced) {
        filtersBtn.addEventListener('click', function () {
            var open = filtersBtn.getAttribute('aria-expanded') === 'true';
            filtersBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
            advanced.hidden = open;
        });
    }

    var kebabs = Array.prototype.slice.call(document.querySelectorAll('[data-tkv2-kebab]'));
    function closeAll(except) {
        kebabs.forEach(function (k) {
            if (k === except) { return; }
            var menu = k.querySelector('[data-tkv2-kebab-menu]');
            var btn = k.querySelector('[data-tkv2-kebab-btn]');
            if (menu) { menu.hidden = true; }
            if (btn) { btn.setAttribute('aria-expanded', 'false'); }
        });
    }
    kebabs.forEach(function (kebab) {
        var btn = kebab.querySelector('[data-tkv2-kebab-btn]');
        var menu = kebab.querySelector('[data-tkv2-kebab-menu]');
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
