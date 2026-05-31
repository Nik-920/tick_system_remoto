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
];

$priorityLabels = [
    'low'      => 'Baja',
    'medium'   => 'Media',
    'high'     => 'Alta',
    'critical' => 'Crítica',
];

$user = auth()->user();
$isReporterOnly = $user->hasRole('reporter') && ! $user->hasAnyRole(['maintenance', 'admin', 'super_admin']);
@endphp

<div class="tickets-page">

    {{-- ===== HERO ===== --}}
    <section class="tickets-hero">
        <div class="tickets-hero-inner">
            <div>
                <p class="tickets-overline">Operación de incidencias</p>
                <h1 class="tickets-title">{{ $isReporterOnly ? 'Mis tickets' : 'Tickets' }}</h1>
                <p class="tickets-subtitle">Vista centralizada para monitorear estado, prioridad y ritmo de atención en cada incidencia.</p>
            </div>
            <a href="{{ route('tickets.create') }}" class="btn-primary tickets-btn-create">Nuevo ticket</a>
        </div>

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

    {{-- ===== TABLA ===== --}}
    <section class="tickets-table-shell">
        <div class="tickets-dataset-head">
            <p class="tickets-dataset-count">{{ number_format($tickets->count()) }} tickets visibles</p>
            <span class="tickets-dataset-chip">Página {{ $tickets->currentPage() }} de {{ $tickets->lastPage() }}</span>
        </div>

        <div class="table-wrap">
            <table class="tickets-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Ubicación</th>
                        <th>Asignado a</th>
                        <th>Creado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                    @php
                        $te             = $ticket->relationLoaded('embedding') ? $ticket->embedding : null;
                        $teMatch        = $te?->relationLoaded('matchedTicket') ? $te->matchedTicket : null;
                        $effectiveDup   = $te && $te->effective_duplicate
                                          && $teMatch
                                          && in_array($teMatch->state, ['open', 'in_progress'], true);
                        $reviewStatus   = $te?->review_status;
                    @endphp
                    <tr>
                        <td class="tickets-td-title">
                            {{ $ticket->title }}
                            {{-- Duplicate badge --}}
                            @if ($effectiveDup)
                                @if ($reviewStatus === 'confirmed')
                                    <span class="ticket-badge ticket-badge--warning" title="Duplicado confirmado manualmente" style="font-size:0.72rem; margin-left:0.3rem;">
                                        ✅ Duplicado confirmado
                                    </span>
                                @else
                                    <span class="ticket-badge ticket-badge--warning" title="Posible duplicado detectado por IA" style="font-size:0.72rem; margin-left:0.3rem;">
                                        ⚠️ Posible duplicado
                                    </span>
                                @endif
                            @endif
                        </td>
                        <td>
                            <span class="ticket-badge ticket-badge--{{ $ticket->state }}">
                                {{ $stateLabels[$ticket->state] ?? str_replace('_', ' ', $ticket->state) }}
                            </span>
                        </td>
                        <td>
                            <span class="ticket-badge ticket-badge--{{ $ticket->priority }}">
                                {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                            </span>
                        </td>
                        <td class="tickets-td-meta">{{ $ticket->location?->name ?? 'N/A' }}</td>
                        <td>
                            <div class="tickets-td-meta">
                                {{ $ticket->assignee?->name ?? $ticket->assignee?->email ?? 'Sin asignar' }}
                            </div>
                            @if ($ticket->assignment_locked)
                                <span class="assignment-badge assignment-badge--locked">Fija</span>
                            @elseif ($ticket->assignment_source === \App\Models\Ticket::ASSIGNMENT_SOURCE_SELF)
                                <span class="assignment-badge assignment-badge--claimed">Tomado</span>
                            @endif
                        </td>
                        <td class="tickets-td-meta">{{ $ticket->created_at?->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('tickets.show', $ticket) }}" class="tickets-link-action">Ver</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="tickets-empty-cell">
                            <div class="empty-state">
                                <p class="empty-state__title">No hay tickets para mostrar</p>
                                <p class="empty-state__note">Prueba ajustar o limpiar filtros para ampliar resultados.</p>
                                <a href="{{ route('tickets.create') }}" class="btn-primary">Crear primer ticket</a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Paginación --}}
    <div class="c-pagination">
        {{ $tickets->links() }}
    </div>

</div>
@endsection