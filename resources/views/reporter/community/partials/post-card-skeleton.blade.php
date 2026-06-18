{{-- ── POST CARD SKELETON ───────────────────────────────────────
     Generic post card: category icon + content + thumbnail + action bar.
     Renders a single placeholder card. Include multiple times for the feed.
──────────────────────────────────────────────────────────── --}}
<article class="comm-post" aria-hidden="true">
    <div class="comm-post__body">

        {{-- Category icon block --}}
        <div class="comm-post__category">
            <div class="comm-sk comm-post__category-icon"></div>
            <div class="comm-sk comm-post__category-label comm-post__category-label--w-20"></div>
        </div>

        {{-- Main content --}}
        <div class="comm-post__content">
            {{-- Meta: "Reporte público · Hace X tiempo" --}}
            <div class="comm-post__meta-row">
                <div class="comm-sk comm-post__meta-label"></div>
                <div class="comm-sk comm-post__meta-dot"></div>
                <div class="comm-sk comm-post__meta-time"></div>
            </div>

            {{-- Title + status badges --}}
            <div class="comm-post__title-row">
                <div class="comm-sk comm-post__title comm-post__title--w-56"></div>
                <div class="comm-sk comm-post__badge comm-post__badge--w-16"></div>
                <div class="comm-sk comm-post__badge comm-post__badge--w-12"></div>
            </div>

            {{-- Description --}}
            <div class="comm-sk comm-post__desc"></div>

            {{-- Location row --}}
            <div class="comm-post__loc-row">
                <div class="comm-sk comm-post__loc-icon"></div>
                <div class="comm-sk comm-post__loc-part comm-post__loc-part--w-16"></div>
                <div class="comm-sk comm-post__loc-sep"></div>
                <div class="comm-sk comm-post__loc-part comm-post__loc-part--w-28"></div>
                <div class="comm-sk comm-post__loc-sep"></div>
                <div class="comm-sk comm-post__loc-part comm-post__loc-part--w-10"></div>
            </div>

            {{-- Tag badge --}}
            <div class="comm-sk comm-post__tag comm-post__tag--w-24"></div>

            {{-- Alert box --}}
            <div class="comm-sk comm-post__alert"></div>

            {{-- Social counts --}}
            <div class="comm-post__social-row">
                <div class="comm-sk comm-post__social-icon"></div>
                <div class="comm-sk comm-post__social-label comm-post__social-label--w-28"></div>
                <div class="comm-sk comm-post__social-icon"></div>
                <div class="comm-sk comm-post__social-label comm-post__social-label--w-20"></div>
            </div>
        </div>

        {{-- Thumbnail --}}
        <div class="comm-post__media">
            <div class="comm-sk comm-post__thumbnail"></div>
            <div class="comm-sk comm-post__photo-count"></div>
        </div>

    </div>

    {{-- Action bar --}}
    <div class="comm-post__actions">
        {{-- Me interesa --}}
        <div class="comm-action">
            <div class="comm-sk comm-action__icon"></div>
            <div class="comm-sk comm-action__label comm-action__label--w-16"></div>
            <div class="comm-sk comm-action__badge"></div>
        </div>
        {{-- Comentar --}}
        <div class="comm-action">
            <div class="comm-sk comm-action__icon"></div>
            <div class="comm-sk comm-action__label comm-action__label--w-14"></div>
            <div class="comm-sk comm-action__badge"></div>
        </div>
        {{-- Guardar --}}
        <div class="comm-action">
            <div class="comm-sk comm-action__icon"></div>
            <div class="comm-sk comm-action__label comm-action__label--w-14"></div>
            <div class="comm-sk comm-action__badge"></div>
        </div>
        {{-- More --}}
        <div class="comm-sk comm-action--more"></div>
    </div>
</article>
