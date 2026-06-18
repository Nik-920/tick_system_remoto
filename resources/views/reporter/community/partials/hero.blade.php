{{-- ── COMMUNITY HERO BANNER (REAL) ───────────────────────────────
     Receives $feed (CommunityFeedViewModel).
     Shows live quick-summary stats. No user-personal data exposed.
──────────────────────────────────────────────────────────── --}}
<div class="comm-hero">

    {{-- Decorative blobs --}}
    <div class="comm-hero__orb comm-hero__orb--1" aria-hidden="true"></div>
    <div class="comm-hero__orb comm-hero__orb--2" aria-hidden="true"></div>

    <div class="comm-hero__body">

        {{-- TOP ROW — eyebrow + title + actions --}}
        <div class="comm-hero__top">
            <div class="comm-hero__copy">
                <p class="comm-hero__eyebrow-text">
                    <x-lucide-globe width="11" height="11" stroke-width="2.5" style="display:inline;vertical-align:middle;margin-right:0.25rem;" />
                    Comunidad del campus
                </p>
                <h2 class="comm-hero__title-text">Reportes públicos</h2>
            </div>

            <div class="comm-hero__actions">
                <a href="{{ route('tickets.create') }}" class="comm-hero__btn-real comm-hero__btn-real--primary">
                    <x-lucide-plus width="15" height="15" stroke-width="2.5" />
                    Crear reporte
                </a>
                <span class="comm-hero__btn-real comm-hero__btn-real--ghost" aria-disabled="true" title="Próximamente">
                    <x-lucide-bell width="15" height="15" stroke-width="2" />
                    Suscribirme
                    <span class="comm-hero__soon-badge">Pronto</span>
                </span>
            </div>
        </div>

        {{-- BOTTOM ROW — stat cards horizontal --}}
        <div class="comm-hero__stats-grid">

            <div class="comm-hero__stat-card">
                <div class="comm-hero__stat-icon comm-hero__stat-icon--active">
                    <x-lucide-activity width="18" height="18" stroke-width="2" />
                </div>
                <div class="comm-hero__stat-body">
                    <span class="comm-hero__stat-value">{{ $feed->quickSummary['active'] }}</span>
                    <span class="comm-hero__stat-label">Activos</span>
                </div>
            </div>

            <div class="comm-hero__stat-card">
                <div class="comm-hero__stat-icon comm-hero__stat-icon--resolved">
                    <x-lucide-check-circle width="18" height="18" stroke-width="2" />
                </div>
                <div class="comm-hero__stat-body">
                    <span class="comm-hero__stat-value">{{ $feed->quickSummary['resolved'] }}</span>
                    <span class="comm-hero__stat-label">Resueltos</span>
                </div>
            </div>

            <div class="comm-hero__stat-card">
                <div class="comm-hero__stat-icon comm-hero__stat-icon--zones">
                    <x-lucide-map-pin width="18" height="18" stroke-width="2" />
                </div>
                <div class="comm-hero__stat-body">
                    <span class="comm-hero__stat-value">{{ $feed->quickSummary['locations'] }}</span>
                    <span class="comm-hero__stat-label">{{ $feed->quickSummary['locations'] === 1 ? 'Zona' : 'Zonas' }}</span>
                </div>
            </div>

        </div>

    </div>
</div>
