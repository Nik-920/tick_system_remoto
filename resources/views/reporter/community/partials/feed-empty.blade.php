{{-- ── FEED EMPTY STATE ────────────────────────────────────────────
     Shown when CommunityFeedQuery returns no posts for current filters.
──────────────────────────────────────────────────────────── --}}
<div class="comm-empty">
    <span class="comm-empty__icon" aria-hidden="true">
        <x-lucide-search-x width="40" height="40" stroke-width="1.5" />
    </span>
    <p class="comm-empty__title">Sin reportes públicos</p>
    <p class="comm-empty__note">
        No hay reportes con estos filtros. Prueba cambiando la búsqueda
        o seleccionando otra categoría.
    </p>
    <a href="{{ route('reporter.community') }}" class="comm-empty__reset">
        Ver todos los reportes
    </a>
</div>
