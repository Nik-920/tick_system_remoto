{{-- post-card/meta-chips: location pills + contextual tags (Reciente, Con evidencia, Resuelto) --}}
<div class="comm-post-v2__chips">
    @if ($post['location'] !== null)
        <span class="comm-post-v2__pill">
            <x-lucide-map-pin class="comm-post-v2__pill-icon" width="12" height="12" stroke-width="2" />
            <span class="comm-post-v2__pill-label">{{ $post['location']['room_code'] }}</span>
        </span>
        @if ($post['location']['building'] !== '')
            <span class="comm-post-v2__pill">
                <x-lucide-building-2 class="comm-post-v2__pill-icon" width="12" height="12" stroke-width="2" />
                <span class="comm-post-v2__pill-label">{{ $post['location']['building'] }}</span>
            </span>
        @endif
        @if ($post['location']['floor'] !== '')
            <span class="comm-post-v2__pill">
                <x-lucide-layers class="comm-post-v2__pill-icon" width="12" height="12" stroke-width="2" />
                <span class="comm-post-v2__pill-label">Piso {{ $post['location']['floor'] }}</span>
            </span>
        @endif
    @endif

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
