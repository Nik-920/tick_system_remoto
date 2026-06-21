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

    @include('reporter.community.partials.filter-panel')

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

<script>
/* ── Filter panel toggle ── */
(function () {
    var btn      = document.getElementById('comm-filter-btn');
    var panel    = document.getElementById('comm-filter-panel');
    var backdrop = document.getElementById('comm-filter-backdrop');
    var closeBtn = document.getElementById('comm-filter-close');

    function openPanel() {
        if (!panel) return;
        panel.classList.add('comm-filter-panel--open');
        panel.setAttribute('aria-hidden', 'false');
        if (btn) btn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closePanel() {
        if (!panel) return;
        panel.classList.remove('comm-filter-panel--open');
        panel.setAttribute('aria-hidden', 'true');
        if (btn) btn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    if (btn)      btn.addEventListener('click', openPanel);
    if (backdrop) backdrop.addEventListener('click', closePanel);
    if (closeBtn) closeBtn.addEventListener('click', closePanel);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePanel();
    });
}());

/* ── Image carousel ── */
(function () {
    function initCarousels() {
        document.querySelectorAll('[data-comm-car]').forEach(function (car) {
            if (car.dataset.carInit) return;
            car.dataset.carInit = '1';

            var slides   = Array.from(car.querySelectorAll('[data-car-slide]'));
            var dots     = Array.from(car.querySelectorAll('[data-car-dot]'));
            var prevBtn  = car.querySelector('[data-car-prev]');
            var nextBtn  = car.querySelector('[data-car-next]');
            var current  = 0;
            var total    = slides.length;

            function show(idx) {
                idx = ((idx % total) + total) % total;
                slides.forEach(function (s, i) {
                    s.classList.toggle('comm-thumb-car__slide--visible', i === idx);
                });
                dots.forEach(function (d, i) {
                    d.classList.toggle('comm-thumb-car__dot--on', i === idx);
                });
                current = idx;
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    show(current - 1);
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    show(current + 1);
                });
            }
            dots.forEach(function (d, i) {
                d.addEventListener('click', function () { show(i); });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCarousels);
    } else {
        initCarousels();
    }
}());
</script>

@endsection
