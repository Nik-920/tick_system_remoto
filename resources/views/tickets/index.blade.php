@extends('layouts.app')

@section('title', 'Tickets')

@section('content')
@php
$searchValue   = (string) ($filters['search']      ?? '');
$stateValue    = (string) ($filters['state']        ?? '');
$priorityValue = (string) ($filters['priority']     ?? '');
$locationValue = (string) ($filters['location_id']  ?? '');
$categoryValue = (string) ($filters['category_id']  ?? '');
$assignmentValue = (string) ($filters['assignment'] ?? '');
$perPageValue  = (string) ($filters['per_page']     ?? '');
$fromValue     = (string) ($filters['from']         ?? '');
$toValue       = (string) ($filters['to']           ?? '');
$duplicatesOn  = ! empty($filters['duplicates']);

$activeFilterCount = 0;
foreach ([$searchValue, $stateValue, $priorityValue, $locationValue, $categoryValue, $fromValue, $toValue] as $filterValue) {
    if ($filterValue !== '') {
        $activeFilterCount++;
    }
}
if ($assignmentValue !== '' && $assignmentValue !== 'all') {
    $activeFilterCount++;
}
if ($duplicatesOn) {
    $activeFilterCount++;
}

$stateLabels = [
    'open'        => 'Abierto',
    'in_progress' => 'En progreso',
    'resolved'    => 'Resuelto',
    'rejected'    => 'Rechazado',
    'cancelled'   => 'Cancelado',
];

$priorityLabels = [
    'low'      => 'Baja',
    'medium'   => 'Media',
    'high'     => 'Alta',
    'critical' => 'Crítica',
];

$user = auth()->user();
$isReporterOnly = $user->hasRole('reporter') && ! $user->hasAnyRole(['maintenance', 'admin', 'super_admin']);
$isMaintenance  = $user->hasRole('maintenance') && ! $user->hasAnyRole(['admin', 'super_admin']);
@endphp

<div class="tickets-page">

    {{-- ===== HERO ===== --}}
    <section class="tickets-hero">
        <div class="tickets-hero-inner">
            <div>
                <p class="tickets-overline">Operación de incidencias</p>
                @if ($isMaintenance)
                    @if ($assignmentValue === 'mine')
                        <h1 class="tickets-title">Mis tickets asignados</h1>
                        <p class="tickets-subtitle">Tickets que tienes actualmente a tu cargo para atención.</p>
                    @else
                        <h1 class="tickets-title">Mis tickets y cola disponible</h1>
                        <p class="tickets-subtitle">Gestiona tus tickets asignados y toma incidencias disponibles para iniciar atención.</p>
                    @endif
                @else
                    <h1 class="tickets-title">{{ $isReporterOnly ? 'Mis tickets' : 'Tickets' }}</h1>
                    <p class="tickets-subtitle">Vista centralizada para monitorear estado, prioridad y ritmo de atención en cada incidencia.</p>
                @endif
            </div>
            <a href="{{ route('tickets.create') }}" class="btn-primary tickets-btn-create">Nuevo ticket</a>
        </div>

        {{-- ===== QUICK FILTERS MAINTENANCE ===== --}}
        @if ($isMaintenance)
        <div style="margin-top:0.75rem; display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
            <span style="font-size:0.85rem; font-weight:600; opacity:0.7;">Vista rápida:</span>
            <a id="maint-filter-mine"
               href="{{ route('tickets.index', ['assignment' => 'mine']) }}"
               class="{{ $assignmentValue === 'mine' ? 'btn-primary' : 'btn-secondary' }}"
               style="font-size:0.82rem; padding:0.25rem 0.75rem;">
               📌 Mis tickets
            </a>
            <a id="maint-filter-available"
               href="{{ route('tickets.available') }}"
               class="btn-secondary"
               style="font-size:0.82rem; padding:0.25rem 0.75rem;">
               📥 Disponibles para tomar
            </a>
            <a id="maint-filter-all"
               href="{{ route('tickets.index') }}"
               class="{{ ($assignmentValue === '' || $assignmentValue === 'all') ? 'btn-primary' : 'btn-secondary' }}"
               style="font-size:0.82rem; padding:0.25rem 0.75rem;">
               Todos los visibles
            </a>
        </div>
        @endif

    </section>

    {{-- Alerts --}}
    @if (session('status'))
    <div class="alert-success">{{ session('status') }}</div>
    @endif

    {{-- ===== FILTROS ===== --}}
    @include('tickets.partials.toolbar')

    {{-- ===== LISTADO DE TICKETS (filas, sin tabla) ===== --}}
    <section class="tickets-list-shell">
        <div class="tickets-list-head">
            <p class="tickets-list-count">{{ number_format($tickets->count()) }} tickets visibles</p>
            <span class="tickets-list-chip">Página {{ $tickets->currentPage() }} de {{ $tickets->lastPage() }}</span>
        </div>

        @if ($tickets->isNotEmpty())
        <div class="tickets-list-cols" aria-hidden="true">
            <span>Título</span>
            <span>Estado</span>
            <span>Prioridad</span>
            <span>Ubicación</span>
            <span>Asignado a</span>
            <span>Creado</span>
            <span>Acción</span>
        </div>
        @endif

        <div class="tickets-list">
            @forelse ($tickets as $ticket)
            @php
                $te             = $ticket->relationLoaded('embedding') ? $ticket->embedding : null;
                $teMatch        = $te?->relationLoaded('matchedTicket') ? $te->matchedTicket : null;
                $effectiveDup   = $te && $te->effective_duplicate
                                  && $teMatch
                                  && in_array($teMatch->state, ['open', 'in_progress'], true);
                $reviewStatus   = $te?->review_status;
                // Softer signal: reporter confirmed a precheck candidate was
                // distinct. Only surfaced when the AI hasn't independently
                // confirmed a duplicate — see 2026_07_01_000100 migration.
                $isRelated      = $te && $te->hasPrecheckCandidate() && ! $effectiveDup;

                // Same tone system as "Mis asignaciones" (tickets-assignments.css)
                // so both boards read as one visual language.
                $priorityTone = match ($ticket->priority) {
                    'critical', 'high' => 'high',
                    'low' => 'low',
                    default => 'medium',
                };
            @endphp
            <article class="tickets-row tickets-tone-{{ $priorityTone }}">
                <span class="tickets-row__rail" aria-hidden="true"></span>

                <div class="tickets-row__title-cell">
                    <h3 class="tickets-row__title">
                        <a href="{{ route('tickets.show', $ticket) }}" class="tickets-row__title-link">{{ $ticket->title }}</a>
                    </h3>

                    @if ((! $isReporterOnly && ! $isMaintenance) || $effectiveDup || $isRelated)
                    <div class="tickets-row__flags">
                        {{-- Community visibility flag (admin/super_admin only) --}}
                        @if (! $isReporterOnly && ! $isMaintenance)
                            @if ($ticket->community_visible)
                                <span class="tickets-flag tickets-flag--success" title="Visible en Comunidad">Comunidad: Visible</span>
                            @else
                                <span class="tickets-flag tickets-flag--neutral" title="Oculto en Comunidad">Comunidad: Oculto</span>
                            @endif
                        @endif

                        {{-- Duplicate flag --}}
                        @if ($effectiveDup)
                            @if ($reviewStatus === 'confirmed')
                                <span class="tickets-flag tickets-flag--warning" title="Duplicado confirmado manualmente">✅ Duplicado confirmado</span>
                            @else
                                <span class="tickets-flag tickets-flag--warning" title="Posible duplicado detectado por IA">⚠️ Posible duplicado</span>
                            @endif
                        @endif

                        {{-- Related flag (precheck candidate, reporter confirmed distinct) --}}
                        @if ($isRelated)
                            <span class="tickets-flag tickets-flag--info" title="{{ $te->precheck_reason ?? 'Reporte relacionado detectado al crear el ticket' }}">🔗 Relacionado</span>
                        @endif
                    </div>
                    @endif
                </div>

                <div class="tickets-row__cell tickets-row__cell--state">
                    <span class="tickets-row__label">Estado</span>
                    <span class="tickets-badge tickets-state--{{ $ticket->state }}">
                        <span class="tickets-badge__dot" aria-hidden="true"></span>
                        {{ $stateLabels[$ticket->state] ?? str_replace('_', ' ', $ticket->state) }}
                    </span>
                </div>

                <div class="tickets-row__cell tickets-row__cell--priority">
                    <span class="tickets-row__label">Prioridad</span>
                    <span class="tickets-badge tickets-prio tickets-tone-{{ $priorityTone }}">
                        <span class="tickets-badge__dot" aria-hidden="true"></span>
                        {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                    </span>
                </div>

                <div class="tickets-row__cell tickets-row__cell--location">
                    <span class="tickets-row__label">Ubicación</span>
                    <span class="tickets-row__location">
                        <x-lucide-map-pin width="13" height="13" stroke-width="2" />
                        <span>{{ $ticket->location?->name ?? 'N/A' }}</span>
                    </span>
                </div>

                <div class="tickets-row__cell tickets-row__cell--assignee">
                    <span class="tickets-row__label">Asignado a</span>
                    <span class="tickets-row__assignee-value">
                        @if ($isMaintenance)
                            @if ($ticket->assigned_to === $user->id)
                                <span class="assignment-badge assignment-badge--claimed">Asignado a mí</span>
                            @elseif ($ticket->assigned_to === null)
                                <span class="assignment-badge assignment-badge--none">Disponible</span>
                            @else
                                {{ $ticket->assignee?->name ?? $ticket->assignee?->email ?? 'Sin asignar' }}
                            @endif
                        @else
                            {{ $ticket->assignee?->name ?? $ticket->assignee?->email ?? 'Sin asignar' }}
                        @endif
                    </span>
                    @if ($ticket->assignment_locked)
                        <span class="assignment-badge assignment-badge--locked">Fija</span>
                    @elseif ($ticket->assignment_source === \App\Models\Ticket::ASSIGNMENT_SOURCE_SELF)
                        <span class="assignment-badge assignment-badge--claimed">Tomado</span>
                    @endif
                </div>

                <div class="tickets-row__cell tickets-row__cell--date">
                    <span class="tickets-row__label">Creado</span>
                    <span class="tickets-row__date">{{ $ticket->created_at?->format('d/m/Y') }}</span>
                </div>

                <div class="tickets-row__cell tickets-row__cell--action">
                    <a href="{{ route('tickets.show', $ticket) }}" class="tickets-row__action">
                        Ver
                        <x-lucide-chevron-right width="14" height="14" stroke-width="2.5" />
                    </a>
                </div>
            </article>
            @empty
                <x-empty-state
                    base-class="empty-state"
                    title="No hay tickets para mostrar"
                    note="Prueba ajustar o limpiar filtros para ampliar resultados."
                    title-class="empty-state__title"
                    note-class="empty-state__note"
                >
                    <a href="{{ route('tickets.create') }}" class="btn-primary">Crear primer ticket</a>
                </x-empty-state>
            @endforelse
        </div>
    </section>

    {{-- Paginación --}}
    <div class="c-pagination">
        {{ $tickets->links() }}
    </div>

</div>

{{-- ============================================================
   Advanced filters modal
   ============================================================ --}}
@include('tickets.partials.filter-modal')

{{-- ============================================================
   Progressive JS — filter modal toggle only. Search and the
   applied filters work server-side without JS.
   ============================================================ --}}
<script>
(function () {
    'use strict';

    var filterBtn      = document.getElementById('tickets-filter-btn');
    var filterModal     = document.getElementById('tickets-filter-modal');
    var filterBackdrop = document.getElementById('tickets-filter-backdrop');
    var filterClose    = document.getElementById('tickets-filter-close');
    var filterPrevFocus = null;

    function openFilter() {
        if (!filterModal) { return; }
        filterPrevFocus = document.activeElement;
        filterModal.classList.add('tickets-filter-modal--open');
        filterModal.setAttribute('aria-hidden', 'false');
        if (filterBtn) { filterBtn.setAttribute('aria-expanded', 'true'); }
        document.body.style.overflow = 'hidden';
        if (filterClose) { filterClose.focus(); }
    }

    function closeFilter() {
        if (!filterModal) { return; }
        filterModal.classList.remove('tickets-filter-modal--open');
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

        filterModal.querySelectorAll('.tickets-fm-opts .tickets-fopt input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                radio.closest('.tickets-fm-opts').querySelectorAll('.tickets-fopt').forEach(function (opt) {
                    opt.classList.toggle('tickets-fopt--on', opt.querySelector('input[type="radio"]') === radio);
                });
            });
        });
    }
})();
</script>
@endsection