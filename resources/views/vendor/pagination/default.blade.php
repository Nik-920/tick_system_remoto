@if ($paginator->hasPages())
<nav class="comm-pag" role="navigation" aria-label="Paginación">
    <div class="comm-pag__inner">

        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="comm-pag__btn comm-pag__btn--disabled" aria-disabled="true" aria-label="Anterior">
                <x-lucide-chevron-left width="14" height="14" stroke-width="2.5" />
                <span class="comm-pag__btn-label">Anterior</span>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="comm-pag__btn" rel="prev" aria-label="Anterior">
                <x-lucide-chevron-left width="14" height="14" stroke-width="2.5" />
                <span class="comm-pag__btn-label">Anterior</span>
            </a>
        @endif

        {{-- Page numbers --}}
        <div class="comm-pag__pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="comm-pag__ellipsis">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="comm-pag__page comm-pag__page--active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="comm-pag__page">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="comm-pag__btn" rel="next" aria-label="Siguiente">
                <span class="comm-pag__btn-label">Siguiente</span>
                <x-lucide-chevron-right width="14" height="14" stroke-width="2.5" />
            </a>
        @else
            <span class="comm-pag__btn comm-pag__btn--disabled" aria-disabled="true" aria-label="Siguiente">
                <span class="comm-pag__btn-label">Siguiente</span>
                <x-lucide-chevron-right width="14" height="14" stroke-width="2.5" />
            </span>
        @endif

    </div>
</nav>
@endif
