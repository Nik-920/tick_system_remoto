{{-- post-card/comments-toggle: collapsible comments section with count badge.
     The <details> wrapper + <summary> live here; comment list + form are in
     reporter.community.partials.comments (Community Comments v3). --}}
<div class="comm-post-v2__comments">
    <details class="comm-comments-details">
        <summary class="comm-comments-summary" aria-label="Ver comentarios">
            <x-lucide-message-circle width="14" height="14" stroke-width="2" />
            <span class="comm-comments-summary__label">
                Comentarios
            </span>
            @if ($post['comments']['count'] > 0)
                <span class="comm-comments-summary__count">{{ $post['comments']['count'] }}</span>
            @endif
            <x-lucide-chevron-down class="comm-comments-summary__chevron" width="15" height="15" stroke-width="2.5" aria-hidden="true" />
        </summary>
        <div class="comm-comments-details__body">
            @include('reporter.community.partials.comments', ['post' => $post])
        </div>
    </details>
</div>
