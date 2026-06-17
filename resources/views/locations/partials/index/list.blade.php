{{-- Listado de ubicaciones (card/row format) --}}
<div class="loc-list">
    @forelse ($locations as $location)
        @include('locations.partials.index.row', ['location' => $location])
    @empty
        <x-empty-state
            base-class="empty-state"
            title="No hay ubicaciones para mostrar"
            note="Prueba ajustar o limpiar filtros. Si aún no existen ubicaciones, registra una nueva para habilitar reportes."
            title-class="empty-state__title"
            note-class="empty-state__note"
        >
            <a href="{{ route('locations.create') }}" class="btn-primary">Crear ubicación</a>
        </x-empty-state>
    @endforelse
</div>
