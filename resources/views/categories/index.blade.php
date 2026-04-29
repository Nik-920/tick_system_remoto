@extends('layouts.app')

@section('title', 'Categorias')

@section('content')
    @php
        $searchValue = (string) ($filters['search'] ?? '');
        $perPageValue = (int) ($filters['per_page'] ?? 15);
        $activeFilterCount = $searchValue !== '' ? 1 : 0;
        $iconsVisibleCount = $categories->getCollection()->filter(static function ($category): bool {
            return is_string($category->icon) && trim($category->icon) !== '';
        })->count();
    @endphp

    <div class="cats-page">

        {{-- ===== HERO ===== --}}
        <section class="cats-hero">
            <div class="cats-hero-inner">
                <div>
                    <p class="cats-overline">Catálogo de clasificación</p>
                    <h1 class="cats-title">Categorías</h1>
                    <p class="cats-subtitle">Administra las categorías que organizan incidencias y tickets, con visibilidad rápida de uso y volumen.</p>
                </div>
                <a href="{{ route('categories.create') }}" class="btn-primary cats-btn-new">Nueva categoría</a>
            </div>

            <div class="cats-kpi-grid">
                <article class="cats-kpi-card">
                    <p class="cats-kpi-label">Total categorías</p>
                    <p class="cats-kpi-value">{{ number_format($categories->total()) }}</p>
                    <p class="cats-kpi-note">Registros en la consulta actual</p>
                </article>
                <article class="cats-kpi-card">
                    <p class="cats-kpi-label">Mostrando</p>
                    <p class="cats-kpi-value">{{ number_format($categories->count()) }}</p>
                    <p class="cats-kpi-note">Elementos visibles en esta página</p>
                </article>
                <article class="cats-kpi-card">
                    <p class="cats-kpi-label">Con icono</p>
                    <p class="cats-kpi-value">{{ number_format($iconsVisibleCount) }}</p>
                    <p class="cats-kpi-note">Categorías con icono configurado</p>
                </article>
            </div>
        </section>

        {{-- Alerts --}}
        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        {{-- ===== FILTROS ===== --}}
        <section class="cats-filters">
            <div class="cats-filters-header">
                <div>
                    <h2 class="cats-filters-title">Filtros de búsqueda</h2>
                    <p class="cats-filters-subtitle">Refina por nombre o descripción y ajusta la densidad de página</p>
                </div>
                <span class="cats-filter-badge">{{ $activeFilterCount }} activos</span>
            </div>

            <form method="GET" action="{{ route('categories.index') }}" class="cats-filter-form">
                <div class="cats-filter-grid">
                    <div>
                        <label for="search" class="cats-field-label">Búsqueda</label>
                        <input id="search" type="text" name="search" value="{{ $searchValue }}"
                               placeholder="Nombre o descripción" class="cats-field">
                    </div>
                    <div>
                        <label for="per_page" class="cats-field-label">Por página</label>
                        <select id="per_page" name="per_page" class="cats-field">
                            @foreach ([10, 15, 25, 50] as $option)
                                <option value="{{ $option }}" @selected($perPageValue === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cats-filter-actions">
                        <button type="submit" class="btn-primary">Filtrar</button>
                        <a href="{{ route('categories.index') }}" class="btn-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
        </section>

        {{-- ===== TABLA CRUD ===== --}}
        <section class="cats-table-shell">
            <div class="cats-dataset-head">
                <p class="cats-dataset-count">{{ number_format($categories->count()) }} categorías en la vista actual</p>
                <span class="cats-dataset-chip">Página {{ $categories->currentPage() }} de {{ $categories->lastPage() }}</span>
            </div>

            <div class="table-wrap">
                <table class="cats-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Icono</th>
                            <th>Incidencias</th>
                            <th>Tickets</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td>
                                    <span class="cats-name">{{ $category->name }}</span>
                                </td>
                                <td>
                                    @if (is_string($category->icon) && filter_var($category->icon, FILTER_VALIDATE_URL))
                                        <img src="{{ $category->icon }}" alt="Icono" class="cats-icon-img">
                                    @elseif (is_string($category->icon) && trim($category->icon) !== '')
                                        <span class="cats-icon-chip">{{ $category->icon }}</span>
                                    @else
                                        <span class="cats-icon-empty">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="cats-count-badge cats-count-badge--blue">
                                        {{ number_format((int) $category->incident_history_count) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="cats-count-badge cats-count-badge--navy">
                                        {{ number_format((int) $category->tickets_count) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="cats-actions">
                                        <a href="{{ route('categories.edit', $category) }}" class="cats-btn-edit">
                                            Editar
                                        </a>
                                        @can('delete', $category)
                                            <form method="POST" action="{{ route('categories.destroy', $category) }}"
                                                  onsubmit="return confirm('Esta acción eliminará la categoría y sus datos relacionados. ¿Deseas continuar?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="cats-btn-delete">Eliminar</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="cats-empty-cell">
                                    <div class="cats-empty-state">
                                        <p class="cats-empty-title">No hay categorías para mostrar</p>
                                        <p class="cats-empty-note">Prueba ajustar o limpiar filtros. Si aún no existen categorías, crea una nueva para clasificar incidencias.</p>
                                        <a href="{{ route('categories.create') }}" class="btn-primary">Crear categoría</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div class="cats-pagination">
            {{ $categories->links() }}
        </div>
    </div>
@endsection