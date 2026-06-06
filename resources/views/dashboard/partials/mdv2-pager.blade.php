@php
    /**
     * Compact, page-1 preview pager for a Dashboard V2 list. It carries REAL
     * totals; deeper pages live on the full board (`more_url`), which already
     * has real pagination — so "next" honestly takes you there.
     *
     * @var array{from:int,to:int,total:int,current:int,last:int,noun:string,more_url:string} $pager
     * @var string $label
     */
@endphp
<nav class="mdv2-pager" aria-label="{{ $label }}">
    <p class="mdv2-pager__status">Mostrando <strong>{{ $pager['from'] }}-{{ $pager['to'] }}</strong> de <strong>{{ $pager['total'] }}</strong> {{ $pager['noun'] }}</p>
    <div class="mdv2-pager__controls">
        <button type="button" class="mdv2-pager__btn" aria-label="Página anterior" disabled>
            <x-lucide-chevron-left width="15" height="15" stroke-width="2.5" />
        </button>
        <span class="mdv2-pager__num is-active" aria-current="page">1</span>
        @if ($pager['last'] > 1)
            <a href="{{ $pager['more_url'] }}" class="mdv2-pager__btn" aria-label="Ver más en el tablero completo">
                <x-lucide-chevron-right width="15" height="15" stroke-width="2.5" />
            </a>
        @else
            <button type="button" class="mdv2-pager__btn" aria-label="Página siguiente" disabled>
                <x-lucide-chevron-right width="15" height="15" stroke-width="2.5" />
            </button>
        @endif
    </div>
</nav>
