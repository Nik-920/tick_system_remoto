@extends('layouts.app')

@section('title', 'Comunidad')

@section('content')

{{--
    Comunidad del campus — reporter-only social feed, Real Data Feed v1.
    Read-only feed from live ticket data. No social actions yet.
    Social actions (reactions, saves, comments) are placeholders — v2/v3.

    Data contract: $feed (CommunityFeedViewModel) contains only pre-mapped
    safe arrays — no reporter_id, assigned_to, or user names are present.
--}}

<section class="comm-page" aria-labelledby="comm-page-title">

    <h1 id="comm-page-title" class="sr-only">Comunidad del campus</h1>

    @include('reporter.community.partials.hero')

    @include('reporter.community.partials.tabs')

    @include('reporter.community.partials.search-filters')

    <div class="comm-layout">

        @include('reporter.community.partials.sidebar')

        <main class="comm-feed" aria-label="Feed de reportes de la comunidad">

            @forelse ($feed->posts as $post)
                @include('reporter.community.partials.post-card', ['post' => $post])
            @empty
                @include('reporter.community.partials.feed-empty')
            @endforelse

            {{ $feed->paginator->links('vendor.pagination.default') }}

        </main>

    </div>

</section>

@endsection
