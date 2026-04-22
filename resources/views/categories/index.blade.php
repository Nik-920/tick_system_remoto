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

    <div class="categories-page">
        <section class="panel panel-pad categories-hero">
            <div class="categories-hero-head">
                <div>
                    <p class="categories-overline">Catalogo de clasificacion</p>
                    <h1 class="categories-title">Categorias</h1>
                    <p class="categories-subtitle">Administra las categorias que organizan incidencias y tickets, con visibilidad rapida de uso y volumen.</p>
                </div>

                <div>
                    <a href="{{ route('categories.create') }}" class="btn-primary">Nueva categoria</a>
                </div>
            </div>

            <div class="categories-kpi-grid">
                <article class="categories-kpi-card">
                    <p class="categories-kpi-label">Total categorias</p>
                    <p class="categories-kpi-value">{{ number_format($categories->total()) }}</p>
                    <p class="categories-kpi-note">Registros en la consulta actual.</p>
                </article>

                <article class="categories-kpi-card">
                    <p class="categories-kpi-label">Mostrando</p>
                    <p class="categories-kpi-value">{{ number_format($categories->count()) }}</p>
                    <p class="categories-kpi-note">Elementos visibles en esta pagina.</p>
                </article>

                <article class="categories-kpi-card">
                    <p class="categories-kpi-label">Con icono</p>
                    <p class="categories-kpi-value">{{ number_format($iconsVisibleCount) }}</p>
                    <p class="categories-kpi-note">Categorias con icono configurado en esta vista.</p>
                </article>
            </div>
        </section>

        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        <section class="panel panel-pad">
            <div class="categories-section-head">
                <div>
                    <h2 class="categories-section-title">Filtros de busqueda</h2>
                    <p class="categories-section-note">Refina por nombre o descripcion y ajusta la densidad de pagina.</p>
                </div>
                <span class="categories-filter-counter">Activos: {{ $activeFilterCount }}</span>
            </div>

            <form method="GET" action="{{ route('categories.index') }}">
                <div class="categories-filter-grid">
                    <div>
                        <label for="search" class="categories-field-label">Busqueda</label>
                        <input
                            id="search"
                            type="text"
                            name="search"
                            value="{{ $searchValue }}"
                            placeholder="Nombre o descripcion"
                            class="field"
                        >
                    </div>

                    <div>
                        <label for="per_page" class="categories-field-label">Por pagina</label>
                        <select id="per_page" name="per_page" class="field">
                            @foreach ([10, 15, 25, 50] as $option)
                                <option value="{{ $option }}" @selected($perPageValue === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="categories-filter-actions">
                        <button type="submit" class="btn-primary">Filtrar</button>
                        <a href="{{ route('categories.index') }}" class="btn-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="panel overflow-hidden categories-table-shell">
            <div class="categories-dataset-head">
                <p class="categories-dataset-count">{{ number_format($categories->count()) }} categorias en la vista actual</p>
                <span class="categories-dataset-chip">Pagina {{ $categories->currentPage() }} de {{ $categories->lastPage() }}</span>
            </div>

            <table>
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
                            <span class="categories-name">{{ $category->name }}</span>
                        </td>
                        <td>
                            @if (is_string($category->icon) && filter_var($category->icon, FILTER_VALIDATE_URL))
                                <img src="{{ $category->icon }}" alt="Icono de categoria" class="categories-icon-preview">
                            @elseif (is_string($category->icon) && trim($category->icon) !== '')
                                <span class="categories-icon-chip" title="{{ $category->icon }}">{{ $category->icon }}</span>
                            @else
                                <span class="categories-icon-chip">N/A</span>
                            @endif
                        </td>
                        <td>
                            <span class="categories-count-chip">{{ number_format((int) $category->incident_history_count) }}</span>
                        </td>
                        <td>
                            <span class="categories-count-chip categories-count-chip-alt">{{ number_format((int) $category->tickets_count) }}</span>
                        </td>
                        <td>
                            <div class="categories-actions">
                                <a href="{{ route('categories.edit', $category) }}" class="categories-action-link">Editar</a>
                                @can('delete', $category)
                                    <form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Esta accion eliminara la categoria y sus datos relacionados. Deseas continuar?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="categories-action-danger">Eliminar</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="categories-empty-state">
                                <p class="categories-empty-title">No hay categorias para mostrar</p>
                                <p class="categories-empty-note">Prueba ajustar o limpiar filtros para ampliar resultados. Si aun no existen categorias, registra una nueva para clasificar incidencias.</p>
                                <a href="{{ route('categories.create') }}" class="btn-primary">Crear categoria</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <div class="categories-pagination">
            {{ $categories->links() }}
        </div>
    </div>
@endsection
