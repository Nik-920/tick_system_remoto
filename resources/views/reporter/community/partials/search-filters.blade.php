{{-- ── SEARCH BAR + FILTER CHIPS (REAL) ──────────────────────────
     GET form — all filters are surfaced as query string params.
     Buildings come from $feed->buildings (distinct from DB).
     Chips link to common filter combos.
──────────────────────────────────────────────────────────── --}}

{{-- Search form --}}
<form method="GET" action="{{ route('reporter.community') }}" class="comm-search" role="search">

    {{-- Preserve all other active filters when searching --}}
    @if ($feed->filters['category'] !== '')
        <input type="hidden" name="category" value="{{ $feed->filters['category'] }}">
    @endif
    @if ($feed->filters['building'] !== '')
        <input type="hidden" name="building" value="{{ $feed->filters['building'] }}">
    @endif
    @if ($feed->filters['state'] !== '')
        <input type="hidden" name="state" value="{{ $feed->filters['state'] }}">
    @endif
    @if ($feed->filters['has_media'] !== '')
        <input type="hidden" name="has_media" value="{{ $feed->filters['has_media'] }}">
    @endif
    @if ($feed->filters['period'] !== '')
        <input type="hidden" name="period" value="{{ $feed->filters['period'] }}">
    @endif

    <input
        type="search"
        name="q"
        class="comm-search__input"
        placeholder="Buscar reportes, aulas, laboratorios…"
        value="{{ $feed->filters['q'] }}"
        autocomplete="off"
        aria-label="Buscar en la comunidad"
    >
    <button type="submit" class="comm-search__submit" aria-label="Buscar">
        <x-lucide-search width="16" height="16" stroke-width="2" />
    </button>
</form>

{{-- Filter chips row --}}
<div class="comm-filters" aria-label="Filtros rápidos">

    {{-- Building dropdown --}}
    @if (count($feed->buildings) > 0)
        <div class="comm-filter-dropdown">
            <form method="GET" action="{{ route('reporter.community') }}" id="comm-building-form">
                @if ($feed->filters['q'] !== '')
                    <input type="hidden" name="q" value="{{ $feed->filters['q'] }}">
                @endif
                @if ($feed->filters['category'] !== '')
                    <input type="hidden" name="category" value="{{ $feed->filters['category'] }}">
                @endif
                @if ($feed->filters['state'] !== '')
                    <input type="hidden" name="state" value="{{ $feed->filters['state'] }}">
                @endif
                @if ($feed->filters['has_media'] !== '')
                    <input type="hidden" name="has_media" value="{{ $feed->filters['has_media'] }}">
                @endif
                @if ($feed->filters['period'] !== '')
                    <input type="hidden" name="period" value="{{ $feed->filters['period'] }}">
                @endif
                <select
                    name="building"
                    class="comm-chip-select {{ $feed->filters['building'] !== '' ? 'comm-chip-select--active' : '' }}"
                    onchange="this.form.submit()"
                    aria-label="Filtrar por edificio"
                >
                    <option value="">Edificio</option>
                    @foreach ($feed->buildings as $building)
                        <option
                            value="{{ $building }}"
                            {{ $feed->filters['building'] === $building ? 'selected' : '' }}
                        >{{ $building }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    @endif

    {{-- Chip: Últimas 24h --}}
    <a
        href="{{ $feed->filters['period'] === '24h' ? route('reporter.community') : route('reporter.community').'?period=24h' }}"
        class="comm-chip-link {{ $feed->filters['period'] === '24h' ? 'comm-chip-link--active' : '' }}"
    >Últimas 24h</a>

    {{-- Chip: Con evidencia --}}
    <a
        href="{{ $feed->filters['has_media'] === '1' ? route('reporter.community') : route('reporter.community').'?has_media=1' }}"
        class="comm-chip-link {{ $feed->filters['has_media'] === '1' ? 'comm-chip-link--active' : '' }}"
    >Con evidencia</a>

    {{-- Chip: En progreso --}}
    <a
        href="{{ $feed->filters['state'] === 'in_progress' ? route('reporter.community') : route('reporter.community').'?state=in_progress' }}"
        class="comm-chip-link {{ $feed->filters['state'] === 'in_progress' ? 'comm-chip-link--active' : '' }}"
    >En progreso</a>

    {{-- Chip: Resueltos --}}
    <a
        href="{{ $feed->filters['state'] === 'resolved' ? route('reporter.community') : route('reporter.community').'?state=resolved' }}"
        class="comm-chip-link {{ $feed->filters['state'] === 'resolved' ? 'comm-chip-link--active' : '' }}"
    >Resueltos</a>

    {{-- Category chips from DB --}}
    @foreach ($feed->categories as $cat)
        <a
            href="{{ $feed->filters['category'] === $cat['id'] ? route('reporter.community') : route('reporter.community').'?category='.urlencode($cat['id']) }}"
            class="comm-chip-link {{ $feed->filters['category'] === $cat['id'] ? 'comm-chip-link--active' : '' }}"
            title="{{ $cat['name'] }}"
        >{{ $cat['name'] }}</a>
    @endforeach

    {{-- Clear filters --}}
    @if ($feed->hasActiveFilters())
        <a href="{{ route('reporter.community') }}" class="comm-chip-link comm-chip-link--clear">
            <x-lucide-x width="12" height="12" stroke-width="2.5" />
            Limpiar
        </a>
    @endif

</div>
