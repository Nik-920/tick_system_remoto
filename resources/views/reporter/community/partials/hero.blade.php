{{-- ── COMMUNITY HERO BANNER (REAL) ───────────────────────────────
     Receives $feed (CommunityFeedViewModel).
     Shows live quick-summary stats. No user-personal data exposed.
──────────────────────────────────────────────────────────── --}}
<div class="comm-hero">
    <div class="comm-hero__body">

        <div class="comm-hero__copy">
            <p class="comm-hero__eyebrow-text">Comunidad del campus</p>
            <h2 class="comm-hero__title-text">Reportes públicos</h2>
            <p class="comm-hero__desc-text">
                Incidencias activas, equipos fuera de servicio y objetos encontrados
                reportados por la comunidad universitaria.
            </p>
            <div class="comm-hero__stats">
                <span class="comm-hero__stat">
                    <strong>{{ $feed->quickSummary['active'] }}</strong> activos
                </span>
                <span class="comm-hero__stat-sep">·</span>
                <span class="comm-hero__stat">
                    <strong>{{ $feed->quickSummary['resolved'] }}</strong> resueltos
                </span>
                <span class="comm-hero__stat-sep">·</span>
                <span class="comm-hero__stat">
                    <strong>{{ $feed->quickSummary['locations'] }}</strong>
                    {{ $feed->quickSummary['locations'] === 1 ? 'zona' : 'zonas' }} con incidencias
                </span>
            </div>
        </div>

        <div class="comm-hero__actions">
            <a href="{{ route('tickets.create') }}" class="comm-hero__btn-real comm-hero__btn-real--primary">
                <x-lucide-plus width="15" height="15" stroke-width="2.5" />
                Crear reporte
            </a>
            <span class="comm-hero__btn-real comm-hero__btn-real--disabled" aria-disabled="true" title="Próximamente">
                <x-lucide-bell width="15" height="15" stroke-width="2" />
                Suscribirme
            </span>
        </div>

    </div>
</div>
