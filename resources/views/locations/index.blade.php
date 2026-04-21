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
            if ($filterValue !== '') {
                $activeFilterCount++;
            }
        }

        $activeLocationsCount = $locations->getCollection()->filter(static function ($location): bool {
            return (bool) $location->is_active;
        })->count();

        $qrStatusLabels = [
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'ready' => 'Listo',
            'failed' => 'Fallido',
        ];
    @endphp

    <div class="locations-page">
        <section class="panel panel-pad locations-hero">
            <div class="locations-hero-head">
                <div>
                    <p class="locations-overline">Operacion de espacios</p>
                    <h1 class="locations-title">Ubicaciones</h1>
                    <p class="locations-subtitle">Gestiona espacios, actividad y estado de generacion QR con una vista uniforme para seguimiento operativo.</p>
                </div>

                <div>
                    <a href="{{ route('locations.create') }}" class="btn-primary">Nueva ubicacion</a>
                </div>
            </div>

            <div class="locations-kpi-grid">
                <article class="locations-kpi-card">
                    <p class="locations-kpi-label">Total ubicaciones</p>
                    <p class="locations-kpi-value">{{ number_format($locations->total()) }}</p>
                    <p class="locations-kpi-note">Registros en la consulta actual.</p>
                </article>

                <article class="locations-kpi-card">
                    <p class="locations-kpi-label">Activas visibles</p>
                    <p class="locations-kpi-value">{{ number_format($activeLocationsCount) }}</p>
                    <p class="locations-kpi-note">Ubicaciones activas en esta pagina.</p>
                </article>

                <article class="locations-kpi-card">
                    <p class="locations-kpi-label">Filtros activos</p>
                    <p class="locations-kpi-value">{{ $activeFilterCount }}</p>
                    <p class="locations-kpi-note">Condiciones aplicadas al listado.</p>
                </article>
            </div>
        </section>

        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        <section class="panel panel-pad">
            <div class="locations-section-head">
                <div>
                    <h2 class="locations-section-title">Filtros de busqueda</h2>
                    <p class="locations-section-note">Refina por identificadores del espacio, estado y densidad de pagina.</p>
                </div>
                <span class="locations-filter-counter">Activos: {{ $activeFilterCount }}</span>
            </div>

            <form method="GET" action="{{ route('locations.index') }}">
                <div class="locations-filter-grid">
                    <div>
                        <label for="search" class="locations-field-label">Busqueda</label>
                        <input
                            id="search"
                            type="text"
                            name="search"
                            value="{{ $searchValue }}"
                            placeholder="Nombre, edificio o aula"
                            class="field"
                        >
                    </div>

                    <div>
                        <label for="building" class="locations-field-label">Edificio</label>
                        <input id="building" type="text" name="building" value="{{ $buildingValue }}" class="field">
                    </div>

                    <div>
                        <label for="floor" class="locations-field-label">Piso</label>
                        <input id="floor" type="text" name="floor" value="{{ $floorValue }}" class="field">
                    </div>

                    <div>
                        <label for="is_active" class="locations-field-label">Estado</label>
                        <select id="is_active" name="is_active" class="field">
                            <option value="">Todas</option>
                            <option value="1" @selected($isActiveValue === '1')>Activas</option>
                            <option value="0" @selected($isActiveValue === '0')>Inactivas</option>
                        </select>
                    </div>

                    <div>
                        <label for="per_page" class="locations-field-label">Por pagina</label>
                        <select id="per_page" name="per_page" class="field">
                            @foreach ([10, 15, 25, 50] as $option)
                                <option value="{{ $option }}" @selected($perPageValue === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="locations-filter-actions">
                        <button type="submit" class="btn-primary">Filtrar</button>
                        <a href="{{ route('locations.index') }}" class="btn-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="panel overflow-hidden locations-table-shell">
            <div class="locations-dataset-head">
                <p class="locations-dataset-count">{{ number_format($locations->count()) }} ubicaciones en la vista actual</p>
                <span class="locations-dataset-chip">Pagina {{ $locations->currentPage() }} de {{ $locations->lastPage() }}</span>
            </div>

            <table>
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Edificio</th>
                    <th>Piso</th>
                    <th>Aula</th>
                    <th>QR</th>
                    <th>Activa</th>
                    <th>Acciones</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($locations as $location)
                    @php
                        $qrStatus = (string) ($location->qr_generation_status ?? 'pending');
                        $qrStatusClass = 'locations-chip-qr-pending';

                        if ($qrStatus === 'processing') {
                            $qrStatusClass = 'locations-chip-qr-processing';
                        } elseif ($qrStatus === 'ready') {
                            $qrStatusClass = 'locations-chip-qr-ready';
                        } elseif ($qrStatus === 'failed') {
                            $qrStatusClass = 'locations-chip-qr-failed';
                        }
                    @endphp
                    <tr>
                        <td>
                            <span class="locations-name">{{ $location->name }}</span>
                        </td>
                        <td>
                            <span class="locations-meta">{{ $location->building }}</span>
                        </td>
                        <td>
                            <span class="locations-meta">{{ $location->floor ?? 'N/A' }}</span>
                        </td>
                        <td>
                            <span class="locations-meta">{{ $location->room_code }}</span>
                        </td>
                        <td>
                            <span class="locations-chip {{ $qrStatusClass }}">{{ $qrStatusLabels[$qrStatus] ?? $qrStatus }}</span>
                        </td>
                        <td>
                            <span class="locations-chip {{ $location->is_active ? 'locations-chip-active' : 'locations-chip-inactive' }}">{{ $location->is_active ? 'Activa' : 'Inactiva' }}</span>
                        </td>
                        <td>
                            <div class="locations-actions">
                                <a href="{{ route('locations.edit', $location) }}" class="locations-action-link">Editar</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="locations-empty-state">
                                <p class="locations-empty-title">No hay ubicaciones para mostrar</p>
                                <p class="locations-empty-note">Prueba ajustar o limpiar filtros para ampliar resultados. Si aun no existen ubicaciones, registra una nueva para habilitar reportes.</p>
                                <a href="{{ route('locations.create') }}" class="btn-primary">Crear ubicacion</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <div class="locations-pagination">
            {{ $locations->links() }}
        </div>
    </div>
@endsection
