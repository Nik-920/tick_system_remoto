{{-- ── REAL POST CARD ──────────────────────────────────────────────
     Receives $post (array from CommunityFeedQuery::toPost).
     No user data (reporter/assignee) is present in $post by design.
     Social actions (Me interesa / Comentar / Guardar) are placeholders.
──────────────────────────────────────────────────────────── --}}
<article class="comm-post" aria-label="Reporte público: {{ $post['title'] }}">
    <div class="comm-post__body">

        {{-- Category icon + label --}}
        <div class="comm-post__category">
            @if ($post['category'] !== null)
                <span class="comm-post__category-icon" aria-hidden="true">
                    <x-dynamic-component
                        :component="'lucide-'.($post['category']['icon'] ?: 'tag')"
                        width="18"
                        height="18"
                        stroke-width="1.75"
                    />
                </span>
                <span class="comm-post__category-name">{{ $post['category']['name'] }}</span>
            @else
                <span class="comm-post__category-icon" aria-hidden="true">
                    <x-lucide-wrench width="18" height="18" stroke-width="1.75" />
                </span>
                <span class="comm-post__category-name">General</span>
            @endif
        </div>

        {{-- Main content --}}
        <div class="comm-post__content">

            {{-- Meta: "Reporte público · hace X tiempo" --}}
            <div class="comm-post__meta-row">
                <span class="comm-post__meta-label">Reporte público</span>
                <span class="comm-post__meta-dot" aria-hidden="true">·</span>
                <span class="comm-post__meta-time">{{ $post['updated_ago'] }}</span>
            </div>

            {{-- Title + state + priority badges --}}
            <div class="comm-post__title-row">
                <h3 class="comm-post__title-text">{{ $post['title'] }}</h3>
                <span class="comm-badge comm-badge--state comm-badge--{{ $post['state_tone'] }}">{{ $post['state_label'] }}</span>
                <span class="comm-badge comm-badge--priority comm-badge--priority-{{ $post['priority_tone'] }}">{{ $post['priority_label'] }}</span>
            </div>

            {{-- Description summary --}}
            @if ($post['summary'] !== '')
                <p class="comm-post__summary">{{ $post['summary'] }}</p>
            @endif

            {{-- Location --}}
            @if ($post['location'] !== null)
                <div class="comm-post__loc-row">
                    <x-lucide-map-pin class="comm-post__loc-icon" width="13" height="13" stroke-width="2" />
                    <span class="comm-post__loc-label">{{ $post['location']['room_code'] }}</span>
                    <span class="comm-post__loc-sep" aria-hidden="true">·</span>
                    <span class="comm-post__loc-label">{{ $post['location']['building'] }}</span>
                    @if ($post['location']['floor'] !== '')
                        <span class="comm-post__loc-sep" aria-hidden="true">·</span>
                        <span class="comm-post__loc-label">Piso {{ $post['location']['floor'] }}</span>
                    @endif
                </div>
            @endif

            {{-- Contextual tags --}}
            <div class="comm-post__tags">
                @if ($post['is_recent'])
                    <span class="comm-tag comm-tag--recent">
                        <x-lucide-clock width="11" height="11" stroke-width="2.5" />
                        Reciente
                    </span>
                @endif
                @if ($post['has_media'])
                    <span class="comm-tag comm-tag--media">
                        <x-lucide-paperclip width="11" height="11" stroke-width="2.5" />
                        Con evidencia
                    </span>
                @endif
                @if ($post['is_resolved'])
                    <span class="comm-tag comm-tag--resolved">
                        <x-lucide-circle-check width="11" height="11" stroke-width="2.5" />
                        Resuelto
                    </span>
                @endif
            </div>

        </div>

        {{-- Thumbnail --}}
        <div class="comm-post__media">
            @if ($post['thumbnail_url'] !== null && str_starts_with((string) $post['thumbnail_type'], 'image'))
                <img
                    src="{{ $post['thumbnail_url'] }}"
                    alt="Evidencia del reporte"
                    class="comm-post__thumb-img"
                    loading="lazy"
                    width="144"
                    height="112"
                >
            @else
                <div class="comm-post__thumb-placeholder" aria-hidden="true">
                    <x-lucide-image width="28" height="28" stroke-width="1.5" />
                </div>
            @endif
            @if ($post['media_count'] > 1)
                <span class="comm-post__photo-count">+{{ $post['media_count'] - 1 }}</span>
            @endif
        </div>

    </div>

    {{-- Action bar — social actions placeholder until v2 --}}
    <div class="comm-post__actions">
        <button type="button" class="comm-action-btn comm-action-btn--disabled" disabled aria-label="Me interesa — Próximamente" title="Próximamente">
            <x-lucide-heart width="15" height="15" stroke-width="2" />
            <span class="comm-action-btn__label">Me interesa</span>
        </button>
        <button type="button" class="comm-action-btn comm-action-btn--disabled" disabled aria-label="Comentar — Próximamente" title="Próximamente">
            <x-lucide-message-circle width="15" height="15" stroke-width="2" />
            <span class="comm-action-btn__label">Comentar</span>
        </button>
        <button type="button" class="comm-action-btn comm-action-btn--disabled" disabled aria-label="Guardar — Próximamente" title="Próximamente">
            <x-lucide-bookmark width="15" height="15" stroke-width="2" />
            <span class="comm-action-btn__label">Guardar</span>
        </button>
        <span class="comm-action-btn__ref">{{ $post['ref'] }}</span>
    </div>
</article>
