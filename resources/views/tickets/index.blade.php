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
    <section class="tickets-filters">
        <div class="tickets-filters-header">
            <div>
                <h2 class="tickets-filters-title">Filtros de búsqueda</h2>
                <p class="tickets-filters-subtitle">Refina por estado, prioridad, ubicación, categoría, fecha y densidad de página</p>
            </div>
            <span class="tickets-filter-badge">{{ $activeFilterCount }} activos</span>
        </div>

        <form method="GET" action="{{ route('tickets.index') }}" class="tickets-filter-form">
            <div class="tickets-filter-grid">
                <div>
                    <label for="search" class="tickets-field-label">Búsqueda</label>
                    <input id="search" type="text" name="search" value="{{ $searchValue }}" placeholder="Título o descripción" class="tickets-field">
                </div>

                <div>
                    <label for="state" class="tickets-field-label">Estado</label>
                    <select id="state" name="state" class="tickets-field">
                        <option value="">Todos</option>
                        @foreach ($stateLabels as $value => $label)
                        <option value="{{ $value }}" @selected($stateValue===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="priority" class="tickets-field-label">Prioridad</label>
                    <select id="priority" name="priority" class="tickets-field">
                        <option value="">Todas</option>
                        @foreach ($priorityLabels as $value => $label)
                        <option value="{{ $value }}" @selected($priorityValue===$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="location_id" class="tickets-field-label">Ubicación</label>
                    <select id="location_id" name="location_id" class="tickets-field">
                        <option value="">Todas</option>
                        @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected($locationValue===(string)$location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="category_id" class="tickets-field-label">Categoría</label>
                    <select id="category_id" name="category_id" class="tickets-field">
                        <option value="">Todas</option>
                        @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($categoryValue===(string)$category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                @unless ($isReporterOnly)
                <div>
                    <label for="assignment" class="tickets-field-label">Asignación</label>
                    <select id="assignment" name="assignment" class="tickets-field">
                        <option value="all" @selected($assignmentValue==='' || $assignmentValue==='all')>Todos</option>
                        <option value="unassigned" @selected($assignmentValue==='unassigned')>Sin asignar</option>
                        <option value="mine" @selected($assignmentValue==='mine')>Mis tickets</option>
                        <option value="assigned" @selected($assignmentValue==='assigned')>Asignados</option>
                    </select>
                </div>
                @endunless

                <div>
                    <label for="per_page" class="tickets-field-label">Por página</label>
                    <select id="per_page" name="per_page" class="tickets-field">
                        <option value="">15</option>
                        @foreach ([10, 15, 25, 50] as $option)
                        <option value="{{ $option }}" @selected($perPageValue===(string) $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="from" class="tickets-field-label">Desde</label>
                    <input id="from" type="date" name="from" value="{{ $fromValue }}" class="tickets-field">
                </div>

                <div>
                    <label for="to" class="tickets-field-label">Hasta</label>
                    <input id="to" type="date" name="to" value="{{ $toValue }}" class="tickets-field">
                </div>

                <div class="tickets-filter-actions">
                    <button type="submit" class="btn-primary">Filtrar</button>
                    <a href="{{ route('tickets.index') }}" class="btn-secondary">Limpiar</a>
                </div>
            </div>

            @unless ($isReporterOnly)
            {{-- Duplicate quick-filter --}}
            <div style="margin-top:0.75rem; display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                <span style="font-size:0.85rem; font-weight:600; opacity:0.7;">Vista rápida:</span>
                <a href="{{ route('tickets.index', array_merge(request()->except(['duplicates', 'page']), [])) }}"
                   class="{{ ! $duplicatesOn ? 'btn-primary' : 'btn-secondary' }}"
                   style="font-size:0.82rem; padding:0.25rem 0.75rem;">
                   Todos
                </a>
                <a href="{{ route('tickets.index', array_merge(request()->except(['duplicates', 'page']), ['duplicates' => '1'])) }}"
                   class="{{ $duplicatesOn ? 'btn-primary' : 'btn-secondary' }}"
                   style="font-size:0.82rem; padding:0.25rem 0.75rem;">
                   ⚠️ Posibles duplicados
                </a>
            </div>
            @endunless
        </form>
    </section>

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
@endsection