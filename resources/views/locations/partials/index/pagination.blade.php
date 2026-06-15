{{-- Paginación real con info de totales --}}
<div class="loc-pagination-bar">
    <p class="loc-pagination-bar__info">
        Mostrando {{ $locations->firstItem() ?? 0 }} a {{ $locations->lastItem() ?? 0 }}
        de {{ number_format($locations->total()) }} ubicaciones
    </p>

    <div class="c-pagination">
        {{ $locations->links() }}
    </div>
</div>
