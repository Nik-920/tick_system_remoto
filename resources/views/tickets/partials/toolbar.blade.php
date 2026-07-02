{{-- ── CONTROLS: Búsqueda · Trigger de filtros · Resumen de activos ───────────
     El botón "Filtros" abre el modal avanzado (filter-modal.blade.php) vía JS.
     Los hidden inputs preservan los filtros del panel cuando el usuario busca
     por texto, igual que en /reporter/community.
──────────────────────────────────────────────────────────────────────────── --}}

<div class="tickets-controls">

    <div class="tickets-controls__row">

        <form method="GET" action="{{ route('tickets.index') }}" class="tickets-search" role="search">

            @if ($stateValue !== '')
                <input type="hidden" name="state" value="{{ $stateValue }}">
            @endif
            @if ($priorityValue !== '')
                <input type="hidden" name="priority" value="{{ $priorityValue }}">
            @endif
            @if ($locationValue !== '')
                <input type="hidden" name="location_id" value="{{ $locationValue }}">
            @endif
            @if ($categoryValue !== '')
                <input type="hidden" name="category_id" value="{{ $categoryValue }}">
            @endif
            @if ($assignmentValue !== '' && $assignmentValue !== 'all')
                <input type="hidden" name="assignment" value="{{ $assignmentValue }}">
            @endif
            @if ($perPageValue !== '')
                <input type="hidden" name="per_page" value="{{ $perPageValue }}">
            @endif
            @if ($fromValue !== '')
                <input type="hidden" name="from" value="{{ $fromValue }}">
            @endif
            @if ($toValue !== '')
                <input type="hidden" name="to" value="{{ $toValue }}">
            @endif
            @if ($duplicatesOn)
                <input type="hidden" name="duplicates" value="1">
            @endif

            <input
                type="search"
                name="search"
                class="tickets-search__input"
                placeholder="Título o descripción"
                value="{{ $searchValue }}"
                autocomplete="off"
                aria-label="Buscar tickets"
            >
            <button type="submit" class="tickets-search__submit" aria-label="Buscar">
                <x-lucide-search width="16" height="16" stroke-width="2" />
            </button>
        </form>

        {{-- Trigger de filtros avanzados — abre el modal vía JS --}}
        <button
            type="button"
            class="tickets-filter-trigger {{ $activeFilterCount > 0 ? 'tickets-filter-trigger--active' : '' }}"
            id="tickets-filter-btn"
            aria-expanded="false"
            aria-controls="tickets-filter-modal"
            aria-label="Abrir filtros avanzados"
        >
            <x-lucide-sliders-horizontal width="15" height="15" stroke-width="2" />
            <span class="tickets-filter-trigger__label">Filtros</span>
            @if ($activeFilterCount > 0)
                <span class="tickets-filter-badge-count" aria-label="{{ $activeFilterCount }} filtros activos">{{ $activeFilterCount }}</span>
            @endif
        </button>
    </div>

    {{-- Resumen de filtros activos --}}
    @if ($activeFilterCount > 0)
        <div class="tickets-active-filters" aria-label="Filtros activos">

            @if ($searchValue !== '')
                <span class="tickets-active-chip">
                    <x-lucide-search width="11" height="11" stroke-width="2.5" />
                    "{{ mb_strlen($searchValue) > 22 ? mb_substr($searchValue, 0, 22).'…' : $searchValue }}"
                </span>
            @endif

            @if ($stateValue !== '')
                <span class="tickets-active-chip">{{ $stateLabels[$stateValue] ?? $stateValue }}</span>
            @endif

            @if ($priorityValue !== '')
                <span class="tickets-active-chip">{{ $priorityLabels[$priorityValue] ?? $priorityValue }}</span>
            @endif

            @if ($locationValue !== '')
                @php $activeLocation = $locations->firstWhere('id', $locationValue); @endphp
                @if ($activeLocation)
                    <span class="tickets-active-chip">{{ $activeLocation->name }}</span>
                @endif
            @endif

            @if ($categoryValue !== '')
                @php $activeCategory = $categories->firstWhere('id', $categoryValue); @endphp
                @if ($activeCategory)
                    <span class="tickets-active-chip">{{ $activeCategory->name }}</span>
                @endif
            @endif

            @unless ($isReporterOnly)
                @if ($assignmentValue !== '' && $assignmentValue !== 'all')
                    <span class="tickets-active-chip">
                        {{ match ($assignmentValue) {
                            'unassigned' => 'Sin asignar',
                            'mine' => 'Mis tickets',
                            'assigned' => 'Asignados',
                            default => $assignmentValue,
                        } }}
                    </span>
                @endif

                @if ($duplicatesOn)
                    <span class="tickets-active-chip">⚠️ Posibles duplicados</span>
                @endif
            @endunless

            @if ($fromValue !== '')
                <span class="tickets-active-chip">Desde {{ $fromValue }}</span>
            @endif

            @if ($toValue !== '')
                <span class="tickets-active-chip">Hasta {{ $toValue }}</span>
            @endif

            <a
                href="{{ route('tickets.index') }}"
                class="tickets-active-chip tickets-active-chip--clear"
                aria-label="Limpiar todos los filtros"
            >
                <x-lucide-x width="11" height="11" stroke-width="2.5" />
                Limpiar
            </a>
        </div>
    @endif

</div>
