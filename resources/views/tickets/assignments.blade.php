@extends('layouts.app')

@section('title', 'Mis asignaciones')

@section('content')
@php
    /** @var \App\ViewModels\Tickets\AssignmentsBoardViewModel $board */
    $f = $board->filters;
    $searchValue   = (string) ($f['search'] ?? '');
    $stateValue    = (string) ($f['state'] ?? '');
    $priorityValue = (string) ($f['priority'] ?? '');
    $locationValue = (string) ($f['location_id'] ?? '');
    $categoryValue = (string) ($f['category_id'] ?? '');

    // Querystring carried when switching tab / focusing a row (drop volatile keys).
    $preserved = collect(request()->query())->except(['tab', 'focus', 'all', 'page'])->all();
    $focusBase = request()->query();

    $stateOptions = ['open' => 'Abierto', 'in_progress' => 'En progreso', 'resolved' => 'Resuelto', 'rejected' => 'Rechazado'];
    $priorityOptions = ['high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja', 'critical' => 'Crítica'];
    $sortOptions = ['recent' => 'Más recientes', 'oldest' => 'Más antiguos', 'priority' => 'Prioridad', 'elapsed' => 'Tiempo transcurrido'];

    $hasActiveFilters = $searchValue !== '' || $stateValue !== '' || $priorityValue !== '' || $locationValue !== '' || $categoryValue !== '';

    $summary = $board->summary;
    $summaryCards = [
        ['key' => 'active',   'value' => (string) $summary['active'],         'label' => 'Asignaciones activas', 'icon' => 'list-checks',    'tone' => 'primary',   'tab' => 'active',  'note' => null],
        ['key' => 'progress', 'value' => (string) $summary['in_progress'],    'label' => 'En progreso',          'icon' => 'loader',         'tone' => 'progress',  'tab' => 'active',  'note' => null],
        ['key' => 'waiting',  'value' => (string) $summary['waiting'],        'label' => 'En espera',            'icon' => 'pause-circle',   'tone' => 'info',      'tab' => 'waiting', 'note' => null],
        ['key' => 'overdue',  'value' => (string) $summary['overdue'],        'label' => 'Vencidas',             'icon' => 'alert-triangle', 'tone' => 'high',      'tab' => 'active',  'note' => null],
        ['key' => 'sla',      'value' => $summary['compliance'] . '%',        'label' => 'Cumplimiento',         'icon' => 'circle-check',   'tone' => 'available', 'tab' => null,     'note' => 'En plazo'],
    ];

    $stepStatusLabel = ['done' => 'Completado', 'current' => 'En curso', 'pending' => 'Pendiente'];
    $detail = $board->focused;

    // Tab-aware empty-state copy (when no filters are applied).
    $emptyByTab = [
        'active'    => ['title' => 'Sin asignaciones activas',     'note' => 'Cuando tomes o te asignen un ticket, aparecerá aquí para su atención y seguimiento.'],
        'waiting'   => ['title' => 'Nada en espera',               'note' => 'Los tickets que tienes asignados pero aún no has iniciado aparecerán aquí.'],
        'completed' => ['title' => 'Sin asignaciones completadas',  'note' => 'Los tickets que resuelvas o que se rechacen se listarán aquí como historial.'],
    ];
    $emptyCurrent = $emptyByTab[$board->activeTab] ?? $emptyByTab['active'];
@endphp

<div class="asg-page">

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    {{-- ── 1. HEADER ─────────────────────────────────────────────── --}}
    <header class="asg-header">
        <div>
            <h1 class="asg-header__title">Mis asignaciones</h1>
            <p class="asg-header__subtitle">
                Tickets asignados actualmente para su atención y seguimiento.
            </p>
        </div>
        <div class="asg-header__actions">
            <a href="{{ route('tickets.create') }}" class="btn-primary">
                <x-lucide-plus width="16" height="16" stroke-width="2.5" />
                Nuevo ticket
            </a>
        </div>
    </header>

    {{-- ── 2. STATUS TABS (real navigation) ──────────────────────── --}}
    <nav class="asg-tabs" aria-label="Estado de las asignaciones">
        @foreach ($board->tabs as $tab)
            <a href="{{ route('tickets.assignments', array_merge($preserved, ['tab' => $tab['key']])) }}"
               class="asg-tab"
               @if ($board->activeTab === $tab['key']) aria-current="page" @endif>
                {{ $tab['label'] }}
                <span class="asg-tab__count">{{ $tab['count'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- ── 3. SUMMARY KPI CARDS ──────────────────────────────────── --}}
    <div class="asg-summary">
        @foreach ($summaryCards as $card)
            <div class="asg-card asg-tone-{{ $card['tone'] }}">
                <div class="asg-card__icon">
                    <x-dynamic-component :component="'lucide-' . $card['icon']" width="22" height="22" stroke-width="2" />
                </div>
                <div class="asg-card__body">
                    <div class="asg-card__value">{{ $card['value'] }}</div>
                    <div class="asg-card__label">{{ $card['label'] }}</div>
                    @if (! empty($card['tab']))
                        <a href="{{ route('tickets.assignments', ['tab' => $card['tab']]) }}" class="asg-card__link">Ver todas</a>
                    @elseif (! empty($card['note']))
                        <span class="asg-card__note">{{ $card['note'] }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── 4. SEARCH + FILTERS (real GET form) ───────────────────── --}}
    <form method="GET" action="{{ route('tickets.assignments') }}" class="asg-toolbar-form">
        <input type="hidden" name="tab" value="{{ $board->activeTab }}">

        <div class="asg-toolbar">
            <div class="asg-search">
                <span class="asg-search__icon" aria-hidden="true">
                    <x-lucide-search width="18" height="18" stroke-width="2" />
                </span>
                <label for="asg-search" class="sr-only">Buscar asignaciones</label>
                <input id="asg-search" type="search" name="search" value="{{ $searchValue }}"
                       class="asg-search__input"
                       placeholder="Buscar por título, ID, laboratorio...">
            </div>

            <button type="button"
                    class="asg-filters-toggle"
                    aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}"
                    aria-controls="asg-filters"
                    data-asg-filters-toggle>
                <x-lucide-sliders-horizontal width="16" height="16" stroke-width="2" />
                Filtros
                <x-lucide-chevron-down class="asg-filters-toggle__chevron" width="15" height="15" stroke-width="2.5" />
            </button>

            <div class="asg-sort">
                <label for="asg-sort" class="asg-sort__label">Ordenar por:</label>
                <select id="asg-sort" name="sort" class="asg-sort__select" data-asg-autosubmit>
                    @foreach ($sortOptions as $value => $label)
                        <option value="{{ $value }}" @selected($board->sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn-primary asg-toolbar-submit">Buscar</button>
        </div>

        {{-- Compact filter row — small inline selects, never a giant panel. --}}
        <div class="asg-filters" id="asg-filters" @unless ($hasActiveFilters) hidden @endunless aria-label="Filtros de asignaciones">
            <div class="asg-filter">
                <label for="asg-f-state" class="asg-filter__label">Estado</label>
                <select id="asg-f-state" name="state" class="asg-filter__select" data-asg-autosubmit>
                    <option value="">Todos</option>
                    @foreach ($stateOptions as $value => $label)
                        <option value="{{ $value }}" @selected($stateValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="asg-filter">
                <label for="asg-f-priority" class="asg-filter__label">Prioridad</label>
                <select id="asg-f-priority" name="priority" class="asg-filter__select" data-asg-autosubmit>
                    <option value="">Todas</option>
                    @foreach ($priorityOptions as $value => $label)
                        <option value="{{ $value }}" @selected($priorityValue === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="asg-filter">
                <label for="asg-f-location" class="asg-filter__label">Laboratorio</label>
                <select id="asg-f-location" name="location_id" class="asg-filter__select" data-asg-autosubmit>
                    <option value="">Todos</option>
                    @foreach ($board->locations as $location)
                        <option value="{{ $location->id }}" @selected($locationValue === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="asg-filter">
                <label for="asg-f-category" class="asg-filter__label">Categoría</label>
                <select id="asg-f-category" name="category_id" class="asg-filter__select" data-asg-autosubmit>
                    <option value="">Todas</option>
                    @foreach ($board->categories as $category)
                        <option value="{{ $category->id }}" @selected($categoryValue === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('tickets.assignments', ['tab' => $board->activeTab]) }}" class="asg-filters__clear">
                <x-lucide-x width="14" height="14" stroke-width="2.5" />
                Limpiar filtros
            </a>
        </div>
    </form>

    {{-- ── 5. CONTENT LAYOUT: list + detail rail ─────────────────── --}}
    <div class="asg-layout">

        {{-- LEFT: assignment list --}}
        <div class="asg-main">
            <div class="asg-list">
                @forelse ($board->assignments as $a)
                    @php $isFocused = $detail !== null && $detail['id'] === $a['id']; @endphp
                    <article class="asg-item asg-tone-{{ $a['tone'] }} {{ $isFocused ? 'is-selected' : '' }}"
                             @if ($isFocused) aria-current="true" @endif
                             aria-labelledby="asg-row-title-{{ $a['id'] }}">
                        <span class="asg-item__rail" aria-hidden="true"></span>

                        <div class="asg-item__icon">
                            <x-dynamic-component :component="'lucide-' . $a['icon']" width="20" height="20" stroke-width="2" />
                        </div>

                        <div class="asg-item__body">
                            <div class="asg-item__top">
                                <h3 class="asg-item__title" id="asg-row-title-{{ $a['id'] }}">
                                    <a class="asg-item__title-link"
                                       href="{{ route('tickets.assignments', array_merge($focusBase, ['focus' => $a['id']])) }}#asg-detail">
                                        {{ $a['title'] }}
                                    </a>
                                </h3>
                                <span class="asg-item__id">{{ $a['ref'] }}</span>
                            </div>

                            <div class="asg-item__meta">
                                <x-lucide-map-pin width="13" height="13" stroke-width="2" />
                                <span>{{ $a['location'] }}</span>
                                <span class="asg-item__meta-sep" aria-hidden="true"></span>
                                <span>{{ $a['category'] }}</span>
                                @if ($a['type'] !== '')
                                    <span class="asg-item__meta-sep" aria-hidden="true"></span>
                                    <span>{{ $a['type'] }}</span>
                                @endif
                            </div>

                            <div class="asg-item__tags">
                                <span class="asg-badge asg-state--{{ $a['state'] }}">
                                    <span class="asg-badge__dot" aria-hidden="true"></span>
                                    {{ $a['state_label'] }}
                                </span>
                                <span class="asg-state__note">{{ $a['state_note'] }}</span>
                                <span class="asg-badge asg-prio asg-tone-{{ $a['priority'] }}">
                                    <span class="asg-badge__dot" aria-hidden="true"></span>
                                    {{ $a['priority_label'] }}
                                </span>
                                @if ($a['overdue'])
                                    <span class="asg-overdue">
                                        <x-lucide-alert-triangle width="12" height="12" stroke-width="2" />
                                        Vencida
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="asg-item__side">
                            <div class="asg-item__stamp">
                                <span class="asg-item__date">{{ $a['created'] }}</span>
                                <span class="asg-item__date-label">Creado</span>
                            </div>

                            @if (! empty($a['elapsed']))
                                <span class="asg-elapsed" title="Tiempo transcurrido">
                                    <x-lucide-clock width="12" height="12" stroke-width="2" />
                                    {{ $a['elapsed'] }}
                                </span>
                            @endif

                            <div class="asg-item__actions">
                                @if ($a['can_start'])
                                    <form method="POST" action="{{ route('tickets.update-state', $a['id']) }}" class="asg-action-form">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                        <input type="hidden" name="to_state" value="in_progress">
                                        <button type="submit" class="asg-btn asg-btn--primary">
                                            <x-lucide-play width="14" height="14" stroke-width="2.5" />
                                            Iniciar atención
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('tickets.show', $a['id']) }}" class="asg-btn asg-btn--{{ $a['action_kind'] }}">
                                        {{ $a['action'] }}
                                    </a>
                                @endif

                                <div class="asg-kebab" data-asg-kebab>
                                    <button type="button" class="asg-kebab__btn"
                                            aria-label="Más acciones para {{ $a['title'] }}"
                                            aria-haspopup="true" aria-expanded="false" data-asg-kebab-btn>
                                        <x-lucide-more-vertical width="18" height="18" stroke-width="2" />
                                    </button>
                                    <div class="asg-kebab__menu" hidden data-asg-kebab-menu>
                                        <a href="{{ route('tickets.assignments', array_merge($focusBase, ['focus' => $a['id']])) }}#asg-detail" class="asg-kebab__item">
                                            <x-lucide-eye width="15" height="15" stroke-width="2" /> Ver detalle
                                        </a>
                                        <a href="{{ route('tickets.show', $a['id']) }}" class="asg-kebab__item">
                                            <x-lucide-external-link width="15" height="15" stroke-width="2" /> Abrir ticket
                                        </a>
                                        @if ($a['can_release'])
                                            <form method="POST" action="{{ route('tickets.release', $a['id']) }}" class="asg-kebab__form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                <button type="submit" class="asg-kebab__item asg-kebab__item--danger">
                                                    <x-lucide-hand width="15" height="15" stroke-width="2" /> Liberar ticket
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="asg-empty">
                        <x-lucide-clipboard-list width="34" height="34" stroke-width="1.5" />
                        @if ($hasActiveFilters)
                            <p class="asg-empty__title">Sin resultados</p>
                            <p class="asg-empty__note">Ningún ticket coincide con los filtros actuales. Prueba a limpiarlos.</p>
                            <a href="{{ route('tickets.assignments', ['tab' => $board->activeTab]) }}" class="asg-btn asg-btn--ghost">Limpiar filtros</a>
                        @else
                            <p class="asg-empty__title">{{ $emptyCurrent['title'] }}</p>
                            <p class="asg-empty__note">{{ $emptyCurrent['note'] }}</p>
                            @if ($board->activeTab !== 'completed')
                                <a href="{{ route('tickets.available') }}" class="asg-btn asg-btn--ghost">Ver tickets disponibles</a>
                            @endif
                        @endif
                    </div>
                @endforelse
            </div>

            @if ($board->shownCount > 0)
                <div class="asg-list-foot">
                    <span class="asg-list-foot__count">
                        Mostrando 1 a {{ $board->shownCount }} de {{ $board->totalCount }} asignaciones
                    </span>
                    @if ($board->hasMore)
                        <a href="{{ route('tickets.assignments', array_merge($focusBase, ['all' => 1])) }}" class="asg-list-foot__more">
                            Ver más
                            <x-lucide-chevron-down width="15" height="15" stroke-width="2.5" />
                        </a>
                    @endif
                </div>
            @endif
        </div>

        {{-- RIGHT: detail rail --}}
        <aside class="asg-rail" id="asg-detail" aria-label="Detalle de la asignación seleccionada">
            @if ($detail === null)
                <section class="asg-panel">
                    <h2 class="asg-panel__title">
                        <x-lucide-clipboard-list width="16" height="16" stroke-width="2" />
                        Detalle de mi asignación
                    </h2>
                    <div class="asg-empty">
                        <x-lucide-mouse-pointer-click width="28" height="28" stroke-width="1.5" />
                        <p class="asg-empty__title">Sin asignación seleccionada</p>
                        <p class="asg-empty__note">Selecciona una asignación de la lista para ver su detalle, progreso y actividad.</p>
                    </div>
                </section>
            @else
                {{-- A. Assignment detail --}}
                <section class="asg-panel asg-detail asg-tone-{{ $detail['priority'] }}">
                    <h2 class="asg-panel__title">
                        <x-lucide-clipboard-list width="16" height="16" stroke-width="2" />
                        Detalle de mi asignación
                    </h2>

                    <div class="asg-detail__head">
                        <div>
                            <h3 class="asg-detail__name">{{ $detail['title'] }}</h3>
                            <span class="asg-detail__id">{{ $detail['ref'] }}</span>
                        </div>
                        <div class="asg-detail__badges">
                            <span class="asg-badge asg-prio asg-tone-{{ $detail['priority'] }}">
                                <span class="asg-badge__dot" aria-hidden="true"></span>
                                {{ $detail['priority_label'] }}
                            </span>
                            <span class="asg-badge asg-state--{{ $detail['state'] }}">
                                <span class="asg-badge__dot" aria-hidden="true"></span>
                                {{ $detail['state_label'] }}
                            </span>
                        </div>
                    </div>

                    <p class="asg-detail__context">{{ $detail['context'] }}</p>
                    @if ($detail['description'] !== '')
                        <p class="asg-detail__desc">{{ $detail['description'] }}</p>
                    @endif

                    <dl class="asg-detail__facts">
                        <div class="asg-fact">
                            <dt class="asg-fact__label">Asignado el</dt>
                            <dd class="asg-fact__value">{{ $detail['assigned_at'] }}</dd>
                        </div>
                        <div class="asg-fact">
                            <dt class="asg-fact__label">Asignado por</dt>
                            <dd class="asg-fact__value">{{ $detail['assigned_by'] }}</dd>
                        </div>
                        <div class="asg-fact">
                            <dt class="asg-fact__label">Estado actual</dt>
                            <dd class="asg-fact__value">{{ $detail['state_label'] }}</dd>
                        </div>
                        <div class="asg-fact">
                            <dt class="asg-fact__label">Tomado el</dt>
                            <dd class="asg-fact__value">{{ $detail['taken_at'] }}</dd>
                        </div>
                        <div class="asg-fact">
                            <dt class="asg-fact__label">Tiempo transcurrido</dt>
                            <dd class="asg-fact__value">{{ $detail['elapsed'] }}</dd>
                        </div>
                        <div class="asg-fact">
                            <dt class="asg-fact__label">Prioridad</dt>
                            <dd class="asg-fact__value">{{ $detail['priority_label'] }}</dd>
                        </div>
                    </dl>

                    <div class="asg-detail__actions">
                        @if ($detail['can_start'])
                            <form method="POST" action="{{ route('tickets.update-state', $detail['id']) }}" class="asg-action-form asg-action-form--block">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="to_state" value="in_progress">
                                <button type="submit" class="asg-btn asg-btn--primary asg-btn--block">
                                    <x-lucide-play width="15" height="15" stroke-width="2.5" />
                                    Iniciar atención
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('tickets.show', $detail['id']) }}" class="asg-btn {{ $detail['can_start'] ? 'asg-btn--ghost' : 'asg-btn--primary' }} asg-btn--block">
                            <x-lucide-external-link width="15" height="15" stroke-width="2" />
                            Abrir ticket
                        </a>
                        @if ($detail['can_release'])
                            <form method="POST" action="{{ route('tickets.release', $detail['id']) }}" class="asg-action-form asg-action-form--block">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                <button type="submit" class="asg-btn asg-btn--link asg-btn--block asg-release-btn">
                                    <x-lucide-hand width="14" height="14" stroke-width="2" />
                                    Liberar ticket
                                </button>
                            </form>
                        @endif
                    </div>

                    @if (in_array($detail['state'], ['open', 'in_progress'], true))
                        <p class="asg-detail__hint">
                            <x-lucide-info width="13" height="13" stroke-width="2" />
                            Resolver o rechazar se realiza desde el detalle del ticket para registrar el comentario.
                        </p>
                    @endif
                </section>

                {{-- B. Progress stepper --}}
                <section class="asg-panel">
                    <h2 class="asg-panel__title">
                        <x-lucide-trending-up width="16" height="16" stroke-width="2" />
                        Progreso del ticket
                    </h2>

                    <div class="asg-stepper">
                        @foreach ($detail['steps'] as $step)
                            <div class="asg-step asg-step--{{ $step['status'] }}">
                                <div class="asg-step__rail">
                                    <span class="asg-step__marker">
                                        @if ($step['status'] === 'done')
                                            <x-lucide-check width="14" height="14" stroke-width="3" />
                                        @elseif ($step['status'] === 'current')
                                            <x-lucide-circle-dot width="14" height="14" stroke-width="2.5" />
                                        @else
                                            <x-lucide-circle width="10" height="10" stroke-width="2.5" />
                                        @endif
                                    </span>
                                    <span class="asg-step__line" aria-hidden="true"></span>
                                </div>
                                <div class="asg-step__body">
                                    <div class="asg-step__label">{{ $step['label'] }}</div>
                                    <span class="asg-step__state">{{ $stepStatusLabel[$step['status']] }}</span>
                                    <span class="asg-step__at">{{ $step['at'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- C. Recent activity --}}
                <section class="asg-panel">
                    <h2 class="asg-panel__title">
                        <x-lucide-clock width="16" height="16" stroke-width="2" />
                        Actividad reciente
                    </h2>

                    @if (empty($detail['activity']))
                        <p class="asg-empty__note">Aún no hay actividad registrada para este ticket.</p>
                    @else
                        <div class="asg-timeline">
                            @foreach ($detail['activity'] as $event)
                                <div class="asg-event asg-tone-{{ $event['tone'] }}">
                                    <div class="asg-event__rail">
                                        <span class="asg-event__dot">
                                            <x-dynamic-component :component="'lucide-' . $event['icon']" width="15" height="15" stroke-width="2" />
                                        </span>
                                        <span class="asg-event__line" aria-hidden="true"></span>
                                    </div>
                                    <div class="asg-event__body">
                                        <div class="asg-event__text">{{ $event['text'] }}</div>
                                        <div class="asg-event__actor">{{ $event['actor'] }}</div>
                                        <span class="asg-event__at">{{ $event['at'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="asg-panel__foot">
                        <a href="{{ route('tickets.show', $detail['id']) }}" class="asg-link">
                            Ver toda la actividad
                            <x-lucide-chevron-down width="14" height="14" stroke-width="2.5" style="transform: rotate(-90deg);" />
                        </a>
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>

{{-- ============================================================
   Progressive JS — filters toggle, select auto-submit, kebab menus.
   Tabs, filtering and selection all work server-side without JS.
   ============================================================ --}}
<script>
(function () {
    'use strict';

    // ── 1. Filters toggle ────────────────────────────────────────
    var filtersBtn = document.querySelector('[data-asg-filters-toggle]');
    var filters = document.getElementById('asg-filters');
    if (filtersBtn && filters) {
        filtersBtn.addEventListener('click', function () {
            var open = filtersBtn.getAttribute('aria-expanded') === 'true';
            filtersBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
            filters.hidden = open;
        });
    }

    // ── 2. Auto-submit selects (sort + compact filters) ──────────
    document.querySelectorAll('[data-asg-autosubmit]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (sel.form) { sel.form.submit(); }
        });
    });

    // ── 3. Kebab menus ───────────────────────────────────────────
    var kebabs = Array.prototype.slice.call(document.querySelectorAll('[data-asg-kebab]'));
    function closeAll(except) {
        kebabs.forEach(function (k) {
            if (k === except) { return; }
            var menu = k.querySelector('[data-asg-kebab-menu]');
            var btn = k.querySelector('[data-asg-kebab-btn]');
            if (menu) { menu.hidden = true; }
            if (btn) { btn.setAttribute('aria-expanded', 'false'); }
        });
    }
    kebabs.forEach(function (kebab) {
        var btn = kebab.querySelector('[data-asg-kebab-btn]');
        var menu = kebab.querySelector('[data-asg-kebab-menu]');
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
