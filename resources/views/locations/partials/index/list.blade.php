{{-- Listado de ubicaciones (card/row format) --}}
<div class="loc-list">
    @forelse ($locations as $location)
        @include('locations.partials.index.row', ['location' => $location])
    @empty
        <div class="empty-state">
            <p class="empty-state__title">No hay ubicaciones para mostrar</p>
            <p class="empty-state__note">Prueba ajustar o limpiar filtros. Si aún no existen ubicaciones, registra una nueva para habilitar reportes.</p>
            <a href="{{ route('locations.create') }}" class="btn-primary">Crear ubicación</a>
        </div>
    @endforelse
</div>
