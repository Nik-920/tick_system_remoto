{{-- Empty state for the community moderation queue --}}
<div class="adm-comm-empty">
    <x-empty-state
        base-class="empty-state"
        title="No hay tickets para esta selección"
        note="Prueba ajustar o limpiar los filtros para ver más resultados."
        title-class="empty-state__title"
        note-class="empty-state__note"
    >
        <a href="{{ route('admin.community.moderation') }}" class="btn-secondary">Limpiar filtros</a>
    </x-empty-state>
</div>
