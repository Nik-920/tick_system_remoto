{{-- ── CONTROLS: Search · Sort · Filter trigger · Active-filter summary ──────
     Filtros avanzados centralizados: el botón "Filtros" abre el panel/drawer
     (filter-panel.blade.php) vía JS. Sort conserva links directos GET.
     Los hidden inputs preservan los filtros del panel cuando el usuario busca.
──────────────────────────────────────────────────────────────────────────── --}}

@php
$panelFilterCount = collect(['category', 'building', 'state', 'has_media', 'period', 'priority', 'saved'])
    ->filter(fn ($k) => ($feed->filters[$k] ?? '') !== '')
    ->count();
@endphp

<div class="comm-controls">

    {{-- Row: search bar + filter trigger button --}}
    <div class="comm-controls__row">

        {{-- Search form — preserves active panel filters when submitting --}}
        <form method="GET" action="{{ route('reporter.community') }}" class="comm-search" role="search">

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
            @if ($feed->currentSort !== 'recent')
                <input type="hidden" name="sort" value="{{ $feed->currentSort }}">
            @endif
            @if (($feed->filters['priority'] ?? '') !== '')
                <input type="hidden" name="priority" value="{{ $feed->filters['priority'] }}">
            @endif
            @if (($feed->filters['saved'] ?? '') === '1')
                <input type="hidden" name="saved" value="1">
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

        {{-- Filter trigger — opens the advanced panel --}}
        <button
            type="button"
            class="comm-filter-trigger {{ $panelFilterCount > 0 ? 'comm-filter-trigger--active' : '' }}"
            id="comm-filter-btn"
            aria-expanded="false"
            aria-controls="comm-filter-panel"
            aria-label="Abrir filtros avanzados"
        >
            <x-lucide-sliders-horizontal width="15" height="15" stroke-width="2" />
            <span class="comm-filter-trigger__label">Filtros</span>
            @if ($panelFilterCount > 0)
                <span class="comm-filter-badge" aria-label="{{ $panelFilterCount }} filtros activos">{{ $panelFilterCount }}</span>
            @endif
        </button>
    </div>

    {{-- Active filters summary — shown when any filter is active --}}
    @if ($feed->hasActiveFilters())
        <div class="comm-active-filters" aria-label="Filtros activos">

            @if ($feed->filters['q'] !== '')
                <span class="comm-active-chip">
                    <x-lucide-search width="11" height="11" stroke-width="2.5" />
                    "{{ mb_strlen($feed->filters['q']) > 22 ? mb_substr($feed->filters['q'], 0, 22).'…' : $feed->filters['q'] }}"
                </span>
            @endif

            @if ($feed->filters['state'] !== '')
                <span class="comm-active-chip">
                    {{ match($feed->filters['state']) {
                        'open'        => 'Abierto',
                        'in_progress' => 'En progreso',
                        'resolved'    => 'Resuelto',
                        default       => $feed->filters['state'],
                    } }}
                </span>
            @endif

            @if ($feed->filters['period'] !== '')
                <span class="comm-active-chip">
                    {{ match($feed->filters['period']) {
                        '24h' => 'Últimas 24h',
                        '7d'  => 'Última semana',
                        '30d' => 'Último mes',
                        default => $feed->filters['period'],
                    } }}
                </span>
            @endif

            @if ($feed->filters['has_media'] === '1')
                <span class="comm-active-chip">Con evidencia</span>
            @endif

            @if (($feed->filters['saved'] ?? '') === '1')
                <span class="comm-active-chip">Guardados</span>
            @endif

            @if ($feed->filters['building'] !== '')
                <span class="comm-active-chip">{{ $feed->filters['building'] }}</span>
            @endif

            @if ($feed->filters['category'] !== '')
                @php $activeCat = collect($feed->categories)->firstWhere('id', $feed->filters['category']); @endphp
                @if ($activeCat)
                    <span class="comm-active-chip">{{ $activeCat['name'] }}</span>
                @endif
            @endif

            @if (($feed->filters['priority'] ?? '') !== '')
                <span class="comm-active-chip">
                    {{ match($feed->filters['priority']) {
                        'critical' => 'Crítico',
                        'high'     => 'Alta prioridad',
                        'medium'   => 'Media prioridad',
                        'low'      => 'Baja prioridad',
                        default    => $feed->filters['priority'],
                    } }}
                </span>
            @endif

            <a
                href="{{ route('reporter.community') }}"
                class="comm-active-chip comm-active-chip--clear"
                aria-label="Limpiar todos los filtros"
            >
                <x-lucide-x width="11" height="11" stroke-width="2.5" />
                Limpiar
            </a>
        </div>
    @endif

</div>
