@extends('layouts.app')

@section('title', 'Ubicaciones')

@section('content')
    @php
        $searchValue = (string) ($filters['search'] ?? '');
        $buildingValue = (string) ($filters['building'] ?? '');
        $floorValue = (string) ($filters['floor'] ?? '');
        $perPageValue = (int) ($filters['per_page'] ?? 15);

        $isActiveRaw = $filters['is_active'] ?? null;
        $isActiveValue = '';
        if ($isActiveRaw === true || $isActiveRaw === 1 || $isActiveRaw === '1') {
            $isActiveValue = '1';
        } elseif ($isActiveRaw === false || $isActiveRaw === 0 || $isActiveRaw === '0') {
            $isActiveValue = '0';
        }

        $activeFilterCount = 0;
        foreach ([$searchValue, $buildingValue, $floorValue, $isActiveValue] as $filterValue) {
            if ($filterValue !== '') $activeFilterCount++;
        }

        $activeLocationsCount = $locations->getCollection()->filter(static function ($location): bool {
            return (bool) $location->is_active;
        })->count();

        $qrStatusLabels = [
            'pending'    => 'Pendiente',
            'processing' => 'Procesando',
            'ready'      => 'Listo',
            'failed'     => 'Fallido',
        ];
    @endphp

    <div class="locs-page">

        {{-- ===== HERO ===== --}}
        <section class="locs-hero">
            <div class="locs-hero-inner">
                <div>
                    <p class="locs-overline">Operación de espacios</p>
                    <h1 class="locs-title">Ubicaciones</h1>
                    <p class="locs-subtitle">Gestiona espacios, actividad y estado de generación QR con una vista uniforme para seguimiento operativo.</p>
                </div>
                <a href="{{ route('locations.create') }}" class="btn-primary locs-btn-new">Nueva ubicación</a>
            </div>

            <div class="locs-kpi-grid">
                <article class="locs-kpi-card">
                    <p class="locs-kpi-label">Total ubicaciones</p>
                    <p class="locs-kpi-value">{{ number_format($locations->total()) }}</p>
                    <p class="locs-kpi-note">Registros en la consulta actual</p>
                </article>
                <article class="locs-kpi-card">
                    <p class="locs-kpi-label">Activas visibles</p>
                    <p class="locs-kpi-value">{{ number_format($activeLocationsCount) }}</p>
                    <p class="locs-kpi-note">Ubicaciones activas en esta página</p>
                </article>
                <article class="locs-kpi-card">
                    <p class="locs-kpi-label">Filtros activos</p>
                    <p class="locs-kpi-value">{{ $activeFilterCount }}</p>
                    <p class="locs-kpi-note">Condiciones aplicadas al listado</p>
                </article>
            </div>
        </section>

        {{-- Alerts --}}
        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert-error">{{ session('error') }}</div>
        @endif

        {{-- ===== FILTROS ===== --}}
        <section class="locs-filters">
            <div class="locs-filters-header">
                <div>
                    <h2 class="locs-filters-title">Filtros de búsqueda</h2>
                    <p class="locs-filters-subtitle">Refina por identificadores del espacio, estado y densidad de página</p>
                </div>
                <span class="locs-filter-badge">{{ $activeFilterCount }} activos</span>
            </div>

            <form method="GET" action="{{ route('locations.index') }}">
                <div class="locs-filter-grid">
                    <div>
                        <label for="search" class="locs-field-label">Búsqueda</label>
                        <input id="search" type="text" name="search" value="{{ $searchValue }}"
                               placeholder="Nombre, edificio o aula" class="locs-field">
                    </div>
                    <div>
                        <label for="building" class="locs-field-label">Edificio</label>
                        <input id="building" type="text" name="building" value="{{ $buildingValue }}" class="locs-field">
                    </div>
                    <div>
                        <label for="floor" class="locs-field-label">Piso</label>
                        <input id="floor" type="text" name="floor" value="{{ $floorValue }}" class="locs-field">
                    </div>
                    <div>
                        <label for="is_active" class="locs-field-label">Estado</label>
                        <select id="is_active" name="is_active" class="locs-field">
                            <option value="">Todas</option>
                            <option value="1" @selected($isActiveValue === '1')>Activas</option>
                            <option value="0" @selected($isActiveValue === '0')>Inactivas</option>
                        </select>
                    </div>
                    <div>
                        <label for="per_page" class="locs-field-label">Por página</label>
                        <select id="per_page" name="per_page" class="locs-field">
                            @foreach ([10, 15, 25, 50] as $option)
                                <option value="{{ $option }}" @selected($perPageValue === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="locs-filter-actions">
                        <button type="submit" class="btn-primary">Filtrar</button>
                        <a href="{{ route('locations.index') }}" class="btn-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
        </section>

        {{-- ===== TABLA ===== --}}
        <section class="locs-table-shell">
            <div class="locs-dataset-head">
                <p class="locs-dataset-count">{{ number_format($locations->count()) }} ubicaciones en la vista actual</p>
                <span class="locs-dataset-chip">Página {{ $locations->currentPage() }} de {{ $locations->lastPage() }}</span>
            </div>

            <div class="table-wrap">
                <table class="locs-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Edificio</th>
                            <th>Piso</th>
                            <th>Aula</th>
                            <th>QR</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($locations as $location)
                            @php
                                $qrStatus = (string) ($location->qr_generation_status ?? 'pending');
                            @endphp
                            <tr>
                                <td class="locs-td-name">{{ $location->name }}</td>
                                <td class="locs-td-meta">{{ $location->building }}</td>
                                <td class="locs-td-meta">{{ $location->floor ?? '—' }}</td>
                                <td class="locs-td-meta">{{ $location->room_code }}</td>
                                <td>
                                    <span class="locs-badge locs-badge--qr-{{ $qrStatus }}">
                                        {{ $qrStatusLabels[$qrStatus] ?? $qrStatus }}
                                    </span>
                                </td>
                                <td>
                                    <span class="locs-badge locs-badge--{{ $location->is_active ? 'active' : 'inactive' }}">
                                        {{ $location->is_active ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="locs-actions">
                                        <a href="{{ route('locations.edit', $location) }}" class="locs-btn-edit">Editar</a>
                                        @can('delete', $location)
                                            <form method="POST" action="{{ route('locations.destroy', $location) }}"
                                                  onsubmit="return confirm('Esta acción eliminará la ubicación de forma permanente. ¿Deseas continuar?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="locs-btn-delete">Eliminar</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="locs-empty-cell">
                                    <div class="locs-empty-state">
                                        <p class="locs-empty-title">No hay ubicaciones para mostrar</p>
                                        <p class="locs-empty-note">Prueba ajustar o limpiar filtros. Si aún no existen ubicaciones, registra una nueva para habilitar reportes.</p>
                                        <a href="{{ route('locations.create') }}" class="btn-primary">Crear ubicación</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="locs-pagination">
            {{ $locations->links() }}
        </div>
    </div>
@endsection