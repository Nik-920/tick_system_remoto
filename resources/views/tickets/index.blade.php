@extends('layouts.app')

@section('title', 'Tickets')

@section('content')
    @php
        $searchValue   = (string) ($filters['search']      ?? '');
        $stateValue    = (string) ($filters['state']       ?? '');
        $priorityValue = (string) ($filters['priority']    ?? '');
        $locationValue = (string) ($filters['location_id'] ?? '');
        $categoryValue = (string) ($filters['category_id'] ?? '');
        $perPageValue  = (string) ($filters['per_page']    ?? '');
        $fromValue     = (string) ($filters['from']        ?? '');
        $toValue       = (string) ($filters['to']          ?? '');

        $activeFilterCount = 0;
        foreach ([$searchValue, $stateValue, $priorityValue, $locationValue, $categoryValue, $fromValue, $toValue] as $v) {
            if ($v !== '') $activeFilterCount++;
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
    @endphp

    <div class="tickets-page">

        {{-- ── HERO ── --}}
        <section class="panel panel-pad tickets-hero">
            <div class="tickets-hero-head">
                <div>
                    <p class="tickets-overline">Operación de incidencias</p>
                    <h1 class="tickets-title">Tickets</h1>
                    <p class="tickets-subtitle">Vista centralizada para monitorear estado, prioridad y ritmo de atención en cada incidencia.</p>
                </div>

                <a href="{{ route('tickets.create') }}" class="btn-primary" style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nuevo ticket
                </a>
            </div>

            <div class="tickets-kpi-grid">
                <article class="tickets-kpi-card">
                    <p class="tickets-kpi-label">Total tickets</p>
                    <p class="tickets-kpi-value">{{ number_format($tickets->total()) }}</p>
                    <p class="tickets-kpi-note">Registros en la consulta actual</p>
                </article>

                <article class="tickets-kpi-card">
                    <p class="tickets-kpi-label">Mostrando</p>
                    <p class="tickets-kpi-value">{{ number_format($tickets->count()) }}</p>
                    <p class="tickets-kpi-note">Elementos visibles en esta página</p>
                </article>

                <article class="tickets-kpi-card">
                    <p class="tickets-kpi-label">Filtros activos</p>
                    <p class="tickets-kpi-value">{{ $activeFilterCount }}</p>
                    <p class="tickets-kpi-note">Condiciones aplicadas al listado</p>
                </article>
            </div>
        </section>

        {{-- ── ALERTA DE SESIÓN ── --}}
        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        {{-- ── FILTROS ── --}}
        <section class="panel panel-pad">
            <div class="tickets-section-head">
                <div>
                    <h2 class="tickets-section-title">Filtros de búsqueda</h2>
                    <p class="tickets-section-note">Refina por estado, prioridad, ubicación, categoría, fecha y densidad de página.</p>
                </div>
                <span class="tickets-filter-counter">Activos: {{ $activeFilterCount }}</span>
            </div>

            <form method="GET" action="{{ route('tickets.index') }}">
                <div class="tickets-filter-grid">
                    <div>
                        <label for="search" class="tickets-field-label">Búsqueda</label>
                        <input id="search" type="text" name="search" value="{{ $searchValue }}" placeholder="Título o descripción" class="field">
                    </div>

                    <div>
                        <label for="state" class="tickets-field-label">Estado</label>
                        <select id="state" name="state" class="field">
                            <option value="">Todos</option>
                            @foreach ($stateLabels as $value => $label)
                                <option value="{{ $value }}" @selected($stateValue === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="priority" class="tickets-field-label">Prioridad</label>
                        <select id="priority" name="priority" class="field">
                            <option value="">Todas</option>
                            @foreach ($priorityLabels as $value => $label)
                                <option value="{{ $value }}" @selected($priorityValue === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="location_id" class="tickets-field-label">Ubicación</label>
                        <select id="location_id" name="location_id" class="field">
                            <option value="">Todas</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected($locationValue === (string) $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="category_id" class="tickets-field-label">Categoría</label>
                        <select id="category_id" name="category_id" class="field">
                            <option value="">Todas</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($categoryValue === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="per_page" class="tickets-field-label">Por página</label>
                        <select id="per_page" name="per_page" class="field">
                            <option value="">15</option>
                            @foreach ([10, 15, 25, 50] as $option)
                                <option value="{{ $option }}" @selected($perPageValue === (string) $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="from" class="tickets-field-label">Desde</label>
                        <input id="from" type="date" name="from" value="{{ $fromValue }}" class="field">
                    </div>

                    <div class="tickets-filter-actions">
                        <button type="submit" class="btn-primary">Filtrar</button>
                        <a href="{{ route('tickets.index') }}" class="btn-secondary" style="text-align:center;">Limpiar</a>
                    </div>
                </div>
            </form>
        </section>

        {{-- ── TABLA ── --}}
        <section class="panel overflow-hidden tickets-table-shell">
            <div class="tickets-dataset-head">
                <p class="tickets-dataset-count">{{ number_format($tickets->count()) }} tickets en la vista actual</p>
                <span class="tickets-dataset-chip">Página {{ $tickets->currentPage() }} de {{ $tickets->lastPage() }}</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th>Ubicación</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td>
                                <span class="tickets-title-cell">{{ $ticket->title }}</span>
                            </td>
                            <td>
                                <span class="tickets-chip tickets-chip-state-{{ $ticket->state }}">
                                    {{ $stateLabels[$ticket->state] ?? str_replace('_', ' ', $ticket->state) }}
                                </span>
                            </td>
                            <td>
                                <span class="tickets-chip tickets-chip-priority-{{ $ticket->priority }}">
                                    {{ $priorityLabels[$ticket->priority] ?? $ticket->priority }}
                                </span>
                            </td>
                            <td>
                                <span class="tickets-meta-cell">{{ $ticket->location?->name ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="tickets-meta-cell">{{ $ticket->created_at?->format('d/m/Y H:i') }}</span>
                            </td>
                            <td>
                                <div class="tickets-actions">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="tickets-action-link">Ver detalle</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="tickets-empty-state">
                                    <p class="tickets-empty-title">No hay tickets para mostrar</p>
                                    <p class="tickets-empty-note">Prueba ajustar o limpiar los filtros para ampliar resultados. Si aún no existen registros, crea un nuevo ticket para iniciar el seguimiento operativo.</p>
                                    <a href="{{ route('tickets.create') }}" class="btn-primary">Crear ticket</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- ── PAGINACIÓN ── --}}
        <div class="tickets-pagination">
            {{ $tickets->links() }}
        </div>

    </div>
@endsection