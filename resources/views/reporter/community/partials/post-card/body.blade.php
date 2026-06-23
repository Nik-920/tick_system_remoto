{{-- post-card/body: 2-column grid wrapper (content left + evidence right) --}}
<div class="comm-post-v2__body">

    <div class="comm-post-v2__main">
        <h3 class="comm-post-v2__title">{{ $post['title'] }}</h3>
        @include('reporter.community.partials.post-card.meta-chips', ['post' => $post])
        @include('reporter.community.partials.post-card.description', ['post' => $post])
    </div>

    @include('reporter.community.partials.post-card.media', ['post' => $post])

</div>
