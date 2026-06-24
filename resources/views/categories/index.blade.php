@extends('layouts.app')

@section('title', 'Categorias')

@section('content')
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
            <span class="cats-filter-badge">{{ (($filters['search'] ?? '') !== '') ? 1 : 0 }} activos</span>
        </div>

        <form method="GET" action="{{ route('categories.index') }}" class="cats-filter-form">
            <div class="cats-filter-grid">
                <div>
                    <label for="search" class="cats-field-label">Búsqueda</label>
                    <input id="search" type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                        placeholder="Nombre o descripción" class="cats-field">
                </div>
                <div>
                    <label for="per_page" class="cats-field-label">Por página</label>
                    <select id="per_page" name="per_page" class="cats-field">
                        @foreach ([10, 15, 25, 50] as $option)
                        <option value="{{ $option }}" @selected(((int) ($filters['per_page'] ?? 15)) === $option)>{{ $option }}</option>
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

    {{-- ===== GRID DE CARDS ===== --}}
    <section class="cats-table-shell">
        <div class="cats-dataset-head">
            <p class="cats-dataset-count">{{ number_format($categories->count()) }} categorías en la vista actual</p>
            <span class="cats-dataset-chip">Página {{ $categories->currentPage() }} de {{ $categories->lastPage() }}</span>
        </div>

        @if ($categories->count())
            <div class="cat-grid">
                @foreach ($categories as $category)
                    @include('categories.partials.card', [
                        'category'    => $category,
                        'maxActivity' => $maxActivity,
                        'toneIndex'   => $loop->index % 7,
                    ])
                @endforeach
            </div>
        @else
            <x-empty-state
                base-class="empty-state"
                title="No se encontraron categorías"
                note="Prueba ajustar o limpiar filtros. Si aún no existen categorías, crea una nueva para clasificar incidencias."
                title-class="empty-state__title"
                note-class="empty-state__note"
            >
                <a href="{{ route('categories.create') }}" class="btn-primary">Crear categoría</a>
            </x-empty-state>
        @endif
    </section>

    <div class="c-pagination">
        {{ $categories->links() }}
    </div>
</div>
@endsection
