{{-- Community Report Card v2 — orchestrator.
     Receives $post (array from CommunityFeedQuery::toPost, no PII).
     Computed vars ($carImgs, $carCount, $commCatColor, $commStateIcon, $commPrioIcon)
     are defined here and available in all included partials via Blade's
     inherited scope — no need to pass them explicitly. --}}
@php
    $carImgs  = array_values(array_filter($post['media_images'] ?? [], fn ($u) => $u !== ''));
    $carCount = count($carImgs);

    $commCatName     = $post['category']['name'] ?? 'General';
    $commCatPalettes = ['blue', 'red', 'green', 'amber', 'purple', 'teal', 'indigo', 'rose'];
    $commCatColor    = $commCatPalettes[crc32($commCatName) % count($commCatPalettes)];

    $commStateIcons = ['open' => 'circle-dot', 'progress' => 'loader', 'resolved' => 'circle-check', 'neutral' => 'circle'];
    $commPrioIcons  = ['high' => 'arrow-up', 'medium' => 'minus', 'low' => 'arrow-down'];
    $commStateIcon  = $commStateIcons[$post['state_tone']] ?? 'circle';
    $commPrioIcon   = $commPrioIcons[$post['priority_tone']] ?? 'minus';
@endphp

<article class="comm-post-v2 comm-post-v2--{{ $post['state_tone'] }} comm-post-v2--priority-{{ $post['priority_tone'] ?? 'low' }}"
         id="ticket-{{ $post['id'] }}"
         aria-label="Reporte público: {{ $post['title'] }}">

    @include('reporter.community.partials.post-card.topbar', ['post' => $post])

    @include('reporter.community.partials.post-card.body', ['post' => $post])

    @include('reporter.community.partials.post-card.actions', ['post' => $post])

    @include('reporter.community.partials.post-card.comments-toggle', ['post' => $post])

    @include('reporter.community.partials.post-card.footer', ['post' => $post])

</article>
