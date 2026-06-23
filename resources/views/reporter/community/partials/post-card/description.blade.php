{{-- post-card/description: collapsible summary panel (plain $post['summary'], 140-char truncated) --}}
@if ($post['summary'] !== '')
    <details class="comm-post-v2__desc" open>
        <summary class="comm-post-v2__desc-summary">
            <span class="comm-post-v2__desc-title">Descripción</span>
            <x-lucide-chevron-down class="comm-post-v2__desc-chevron" width="14" height="14" stroke-width="2.5" aria-hidden="true" />
        </summary>
        <div class="comm-post-v2__desc-body">
            <p class="comm-post-v2__desc-text">{{ $post['summary'] }}</p>
        </div>
    </details>
@endif
