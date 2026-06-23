{{-- post-card/topbar: category · público · time · ref · state · priority --}}
<div class="comm-post-v2__topbar">
    <div class="comm-post-v2__topbar-start">
        <span class="comm-post-v2__chip comm-post-v2__chip--cat comm-post-v2__chip--{{ $commCatColor }}">
            @if ($post['category'] !== null)
                @if (($post['category']['icon_type'] ?? 'lucide') === 'image' && filled($post['category']['icon_url'] ?? null))
                    <img
                        src="{{ $post['category']['icon_url'] }}"
                        alt=""
                        class="comm-post-v2__cat-icon-img"
                        loading="lazy"
                        decoding="async"
                    >
                @else
                    <x-dynamic-component
                        :component="'lucide-'.($post['category']['icon_name'] ?? 'tag')"
                        class="comm-post-v2__chip-icon" width="13" height="13" stroke-width="2" />
                @endif
                <span>{{ $post['category']['name'] }}</span>
            @else
                <x-lucide-wrench class="comm-post-v2__chip-icon" width="13" height="13" stroke-width="2" />
                <span>General</span>
            @endif
        </span>
        <span class="comm-post-v2__chip comm-post-v2__chip--public">
            <x-lucide-eye class="comm-post-v2__chip-icon" width="13" height="13" stroke-width="2" />
            <span>Reporte público</span>
        </span>
    </div>

    <div class="comm-post-v2__topbar-end">
        <span class="comm-post-v2__meta">
            <x-lucide-clock width="12" height="12" stroke-width="2" aria-hidden="true" />
            {{ $post['updated_ago'] }}
        </span>
        <span class="comm-post-v2__meta-dot" aria-hidden="true">·</span>
        <span class="comm-post-v2__meta-ref">{{ $post['ref'] }}</span>
        <span class="comm-badge comm-badge--state comm-badge--{{ $post['state_tone'] }}">
            <x-dynamic-component :component="'lucide-'.$commStateIcon" class="comm-badge__icon" width="11" height="11" stroke-width="2.5" />
            {{ $post['state_label'] }}
        </span>
        <span class="comm-badge comm-badge--priority comm-badge--priority-{{ $post['priority_tone'] }}">
            <x-dynamic-component :component="'lucide-'.$commPrioIcon" class="comm-badge__icon" width="11" height="11" stroke-width="2.5" />
            {{ $post['priority_label'] }}
        </span>
    </div>
</div>
