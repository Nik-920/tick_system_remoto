@extends('layouts.app')

@section('title', 'Mis tickets')

@section('content')
@php
    /** @var \App\ViewModels\Tickets\ReporterTicketsBoardViewModel $board */
    $f = $board->filters;
    $searchValue = (string) ($f['search'] ?? '');
    $sortOptions  = $board->sortOptions();

    // Querystring carried when switching the status chip (drop volatile keys).
    $chipBase = collect(request()->query())->except(['status', 'page'])->all();
    $pageBase = collect(request()->query())->except(['page'])->all();

    // Build the donut conic-gradient from cumulative ring percentages.
    $donutStops = collect($board->summary['donut'])
        ->map(fn ($s) => "{$s['color']} {$s['start']}% {$s['end']}%")
        ->implode(', ');
    $donutAria = collect($board->summary['donut'])
        ->map(fn ($s) => "{$s['label']} {$s['count']} ({$s['percent']}%)")
        ->implode(', ');
    $hasDonut    = $board->summary['total'] > 0;
    $filterCount = $board->activeFiltersCount();
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

            {{-- Advanced filters trigger — opens the filter modal via JS. --}}
            <button
                type="button"
                class="rep-filter-trigger {{ $filterCount > 0 ? 'rep-filter-trigger--active' : '' }}"
                id="rep-filter-btn"
                aria-expanded="false"
                aria-controls="rep-filter-modal"
                aria-label="Abrir filtros avanzados"
            >
                <x-lucide-sliders-horizontal width="15" height="15" stroke-width="2" />
                <span>Filtros</span>
                @if ($filterCount > 0)
                    <span class="rep-filter-badge" aria-label="{{ $filterCount }} filtros activos">{{ $filterCount }}</span>
                @endif
            </button>
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
                                @if ($t['is_duplicate'] ?? false)
                                    <span class="rep-badge rep-badge-duplicate rep-tone-warning" title="La IA detectó un ticket similar a este reporte">
                                        ⚠ Posible duplicado
                                    </span>
                                @endif
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

                                {{-- Kebab only renders while the reporter may still
                                     act on the ticket (own + open + unassigned +
                                     unlocked). Once maintenance takes it, it
                                     disappears. Both "Editar" and "Cancelar
                                     solicitud" are REAL now. Cancelar withdraws the
                                     request (open → cancelled) via a PATCH form —
                                     NOT a rejection and NOT a destructive delete. --}}
                                @if ($t['show_actions_menu'])
                                    <div class="rep-kebab" data-rep-kebab>
                                        <button type="button" class="rep-kebab__btn"
                                                aria-label="Más acciones para {{ $t['title'] }}"
                                                aria-haspopup="true" aria-expanded="false" data-rep-kebab-btn>
                                            <x-lucide-more-vertical width="18" height="18" stroke-width="2" />
                                        </button>
                                        <div class="rep-kebab__menu" hidden data-rep-kebab-menu>
                                            <a href="{{ route('tickets.show', $t['id']) }}" class="rep-kebab__item">
                                                <x-lucide-file-text width="15" height="15" stroke-width="2" /> Ficha completa
                                            </a>
                                            @if ($t['can_edit'])
                                                <a href="{{ route('reporter.tickets.edit', $t['id']) }}" class="rep-kebab__item">
                                                    <x-lucide-edit width="15" height="15" stroke-width="2" /> Editar
                                                </a>
                                            @endif
                                            @if ($t['can_cancel'])
                                                <button type="button" class="rep-kebab__item rep-kebab__item--danger"
                                                        data-cancel-trigger
                                                        data-cancel-action="{{ route('reporter.tickets.cancel', $t['id']) }}"
                                                        data-cancel-title="{{ e($t['title']) }}"
                                                        data-cancel-code="{{ e($t['code'] ?? '') }}"
                                                        data-idempotency="{{ (string) \Illuminate\Support\Str::uuid() }}">
                                                    <x-lucide-x width="15" height="15" stroke-width="2" /> Cancelar solicitud
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
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
                                    <span class="rep-bar__label" title="{{ $lab['label'] }}">{{ $lab['label'] }}</span>
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
   Advanced filters modal
   ============================================================ --}}
@include('tickets.reporter.partials.advanced-filters-modal')

{{-- ============================================================
   Cancel-confirmation modal (replaces native browser confirm)
   ============================================================ --}}
<div id="rep-cancel-modal" class="rep-modal" role="dialog" aria-modal="true"
     aria-labelledby="rep-cancel-modal-title" aria-describedby="rep-cancel-modal-desc" hidden>
    <div class="rep-modal__backdrop" id="rep-cancel-backdrop"></div>
    <div class="rep-modal__dialog">
        <div class="rep-modal__icon-wrap">
            <div class="rep-modal__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                </svg>
            </div>
        </div>
        <div class="rep-modal__body">
            <h2 class="rep-modal__title" id="rep-cancel-modal-title">¿Retirar esta solicitud?</h2>
            <p class="rep-modal__code" id="rep-cancel-modal-code"></p>
            <p class="rep-modal__desc" id="rep-cancel-modal-desc">
                Esta acción no puede deshacerse. El ticket quedará marcado como
                <strong>cancelado</strong> y no podrá reabrirse.
            </p>
            <div class="rep-modal__warning">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                ¿Seguro que deseas continuar?
            </div>
        </div>
        <form id="rep-cancel-form" method="POST">
            @csrf
            @method('PATCH')
            <input type="hidden" name="idempotency_key" id="rep-cancel-idempotency">
            <div class="rep-modal__actions">
                <button type="button" class="rep-modal__btn rep-modal__btn--ghost" id="rep-cancel-dismiss">
                    Mantener ticket
                </button>
                <button type="submit" class="rep-modal__btn rep-modal__btn--danger" id="rep-cancel-confirm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Sí, cancelar
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ============================================================
   Progressive JS — filter modal + kebab menus + cancel confirm.
   Search, chips, and pagination work server-side without JS.
   ============================================================ --}}
<script>
(function () {
    'use strict';

    /* ── Advanced filter modal ──────────────────────────────── */
    var filterBtn      = document.getElementById('rep-filter-btn');
    var filterModal    = document.getElementById('rep-filter-modal');
    var filterBackdrop = document.getElementById('rep-filter-backdrop');
    var filterClose    = document.getElementById('rep-filter-close');
    var filterPrevFocus = null;

    function openFilter() {
        if (!filterModal) { return; }
        filterPrevFocus = document.activeElement;
        filterModal.classList.add('rep-filter-modal--open');
        filterModal.setAttribute('aria-hidden', 'false');
        if (filterBtn) { filterBtn.setAttribute('aria-expanded', 'true'); }
        document.body.style.overflow = 'hidden';
        if (filterClose) { filterClose.focus(); }
    }

    function closeFilter() {
        if (!filterModal) { return; }
        filterModal.classList.remove('rep-filter-modal--open');
        filterModal.setAttribute('aria-hidden', 'true');
        if (filterBtn) { filterBtn.setAttribute('aria-expanded', 'false'); }
        document.body.style.overflow = '';
        if (filterPrevFocus) { filterPrevFocus.focus(); }
    }

    if (filterBtn)      { filterBtn.addEventListener('click', openFilter); }
    if (filterBackdrop) { filterBackdrop.addEventListener('click', closeFilter); }
    if (filterClose)    { filterClose.addEventListener('click', closeFilter); }

    if (filterModal) {
        filterModal.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeFilter(); return; }
            if (e.key !== 'Tab') { return; }
            var focusable = Array.prototype.slice.call(
                filterModal.querySelectorAll('button, [href], input, select, [tabindex]:not([tabindex="-1"])')
            ).filter(function (el) { return !el.disabled && !el.hidden; });
            if (!focusable.length) { return; }
            var first = focusable[0], last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault(); first.focus();
            }
        });

        filterModal.querySelectorAll('.rep-fm-opts .rep-fm-opt input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                radio.closest('.rep-fm-opts').querySelectorAll('.rep-fm-opt').forEach(function (opt) {
                    opt.classList.toggle('rep-fm-opt--on', opt.querySelector('input[type="radio"]') === radio);
                });
            });
        });
    }

    /* ── Kebab menus ─────────────────────────────────────────── */
    var kebabs = Array.prototype.slice.call(document.querySelectorAll('[data-rep-kebab]'));
    function closeAll(except) {
        kebabs.forEach(function (k) {
            if (k === except) { return; }
            var menu = k.querySelector('[data-rep-kebab-menu]');
            var btn = k.querySelector('[data-rep-kebab-btn]');
            if (menu) { menu.hidden = true; }
            if (btn) { btn.setAttribute('aria-expanded', 'false'); }
            setItemOpen(k, false);
        });
    }
    function setItemOpen(kebab, open) {
        var item = kebab.closest('.rep-item');
        if (item) { item.classList.toggle('rep-item--menu-open', open); }
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
            setItemOpen(kebab, !open);
        });
    });
    document.addEventListener('click', function () { closeAll(null); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(null); cancelModal.close(); closeFilter(); } });

    /* ── Cancel confirmation modal ───────────────────────────── */
    var cancelModal = (function () {
        var modal   = document.getElementById('rep-cancel-modal');
        var form    = document.getElementById('rep-cancel-form');
        var codeEl  = document.getElementById('rep-cancel-modal-code');
        var idem    = document.getElementById('rep-cancel-idempotency');
        var dismiss = document.getElementById('rep-cancel-dismiss');
        var backdrop = document.getElementById('rep-cancel-backdrop');
        var prevFocus = null;

        function open(trigger) {
            form.action   = trigger.dataset.cancelAction;
            idem.value    = trigger.dataset.idempotency;
            codeEl.textContent = trigger.dataset.cancelCode
                ? 'Ticket ' + trigger.dataset.cancelCode
                : '';
            prevFocus = document.activeElement;
            modal.hidden = false;
            modal.classList.remove('rep-modal--leaving');
            modal.classList.add('rep-modal--visible');
            document.body.classList.add('rep-modal-open');
            /* focus first interactive element */
            dismiss.focus();
        }

        function close() {
            if (modal.hidden) { return; }
            modal.classList.add('rep-modal--leaving');
            modal.classList.remove('rep-modal--visible');
            setTimeout(function () {
                modal.hidden = true;
                modal.classList.remove('rep-modal--leaving');
                document.body.classList.remove('rep-modal-open');
                if (prevFocus) { prevFocus.focus(); }
            }, 220);
        }

        if (dismiss)  { dismiss.addEventListener('click', close); }
        if (backdrop) { backdrop.addEventListener('click', close); }

        /* trap focus inside modal */
        if (modal) {
            modal.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { close(); return; }
                if (e.key !== 'Tab') { return; }
                var focusable = Array.prototype.slice.call(
                    modal.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])')
                ).filter(function (el) { return !el.disabled && !el.hidden; });
                if (!focusable.length) { return; }
                var first = focusable[0], last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault(); last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault(); first.focus();
                }
            });
        }

        /* wire all cancel triggers */
        document.querySelectorAll('[data-cancel-trigger]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                closeAll(null);
                open(btn);
            });
        });

        return { open: open, close: close };
    }());
})();
</script>
@endsection
