{{-- ── HERO BANNER SKELETON ──────────────────────────────────────
     Blue gradient hero with shimmer placeholders.
     .comm-sk--light = light shimmer on dark background.
──────────────────────────────────────────────────────────── --}}
<div class="comm-hero" aria-hidden="true">
    <div class="comm-hero__orb comm-hero__orb--1"></div>
    <div class="comm-hero__orb comm-hero__orb--2"></div>
    <div class="comm-hero__body">

        {{-- Top row: eyebrow + title (left) + action buttons (right) --}}
        <div class="comm-hero__top">
            <div class="comm-hero__copy">
                <div class="comm-sk comm-sk--light comm-hero__eyebrow"></div>
                <div class="comm-sk comm-sk--light comm-hero__title"></div>
            </div>
            <div class="comm-hero__actions">
                <div class="comm-sk comm-sk--light comm-hero__btn"></div>
                <div class="comm-sk comm-sk--light comm-hero__btn"></div>
            </div>
        </div>

        {{-- Bottom row: stat cards horizontal --}}
        <div class="comm-hero__stats-grid">
            <div class="comm-sk comm-sk--light" style="height:3.25rem;border-radius:0.875rem;flex:1;min-width:8rem;"></div>
            <div class="comm-sk comm-sk--light" style="height:3.25rem;border-radius:0.875rem;flex:1;min-width:8rem;"></div>
            <div class="comm-sk comm-sk--light" style="height:3.25rem;border-radius:0.875rem;flex:1;min-width:8rem;"></div>
        </div>

    </div>
</div>
