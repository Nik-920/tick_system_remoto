{{-- ── NAVIGATION TABS (REAL) ──────────────────────────────────────
     GET links — filter via route params.
     "Siguiendo" is a placeholder for v2 follows feature.
──────────────────────────────────────────────────────────── --}}
<nav class="comm-tabs" aria-label="Filtros de comunidad">

    <a href="{{ route('reporter.community') }}"
       class="comm-tab {{ ($feed->filters['q'] === '' && $feed->filters['category'] === '' && $feed->filters['state'] === '' && $feed->filters['building'] === '' && $feed->filters['has_media'] === '' && $feed->filters['period'] === '') ? 'comm-tab--active' : '' }}"
       aria-label="Para ti">
        <span class="comm-tab__label-text">Para ti</span>
        @if ($feed->filters['q'] === '' && $feed->filters['category'] === '' && $feed->filters['state'] === '' && $feed->filters['building'] === '' && $feed->filters['has_media'] === '' && $feed->filters['period'] === '')
            <span class="comm-tab__indicator" aria-hidden="true"></span>
        @endif
    </a>

    <a href="{{ route('reporter.community') }}?q=objeto+encontrado"
       class="comm-tab {{ $feed->filters['q'] === 'objeto encontrado' ? 'comm-tab--active' : '' }}"
       aria-label="Objetos encontrados">
        <span class="comm-tab__label-text">Objetos encontrados</span>
        @if ($feed->filters['q'] === 'objeto encontrado')
            <span class="comm-tab__indicator" aria-hidden="true"></span>
        @endif
    </a>

    <a href="{{ route('reporter.community') }}?q=laboratorio"
       class="comm-tab {{ $feed->filters['q'] === 'laboratorio' ? 'comm-tab--active' : '' }}"
       aria-label="Laboratorios">
        <span class="comm-tab__label-text">Laboratorios</span>
        @if ($feed->filters['q'] === 'laboratorio')
            <span class="comm-tab__indicator" aria-hidden="true"></span>
        @endif
    </a>

    <a href="{{ route('reporter.community') }}?q=aula"
       class="comm-tab {{ $feed->filters['q'] === 'aula' ? 'comm-tab--active' : '' }}"
       aria-label="Aulas">
        <span class="comm-tab__label-text">Aulas</span>
        @if ($feed->filters['q'] === 'aula')
            <span class="comm-tab__indicator" aria-hidden="true"></span>
        @endif
    </a>

    <a href="{{ route('reporter.community') }}?priority=critical"
       class="comm-tab {{ $feed->filters['priority'] === 'critical' ? 'comm-tab--active' : '' }}"
       aria-label="Alta prioridad">
        <span class="comm-tab__label-text">Alta prioridad</span>
        @if ($feed->filters['priority'] === 'critical')
            <span class="comm-tab__indicator" aria-hidden="true"></span>
        @endif
    </a>

    <a href="{{ route('reporter.community') }}?state=resolved"
       class="comm-tab {{ $feed->filters['state'] === 'resolved' ? 'comm-tab--active' : '' }}"
       aria-label="Resueltos">
        <span class="comm-tab__label-text">Resueltos</span>
        @if ($feed->filters['state'] === 'resolved')
            <span class="comm-tab__indicator" aria-hidden="true"></span>
        @endif
    </a>

    {{-- Próximamente: Siguiendo (v2 follows) --}}
    <span class="comm-tab comm-tab--with-badge comm-tab--disabled" aria-disabled="true" title="Disponible próximamente">
        <span class="comm-tab__label-text">Siguiendo</span>
        <span class="comm-tab__badge-real">Próximamente</span>
    </span>

</nav>
