{{-- Filters partial for the community moderation queue.
     Same toolbar language as modules/tickets-assignments.css: a search bar,
     a collapsible "Filtros" panel, and a submit button. Compact selects
     auto-submit on change; the search box waits for "Filtrar". --}}
<form method="GET" action="{{ route('admin.community.moderation') }}" class="comm-mod-toolbar-form">

    <div class="comm-mod-toolbar">
        <div class="comm-mod-search">
            <span class="comm-mod-search__icon" aria-hidden="true">
                <x-lucide-search width="18" height="18" stroke-width="2" />
            </span>
            <label for="cmq_q" class="sr-only">Buscar en moderación</label>
            <input id="cmq_q" type="search" name="q" value="{{ $vm->filters['q'] }}"
                   class="comm-mod-search__input"
                   placeholder="Título, ubicación o categoría">
        </div>

        <button type="button"
                class="comm-mod-filters-toggle"
                aria-expanded="{{ $vm->hasActiveFilters() ? 'true' : 'false' }}"
                aria-controls="comm-mod-filters"
                data-cmq-filters-toggle>
            <x-lucide-sliders-horizontal width="16" height="16" stroke-width="2" />
            Filtros
            <x-lucide-chevron-down class="comm-mod-filters-toggle__chevron" width="15" height="15" stroke-width="2.5" />
        </button>

        <button type="submit" class="btn-primary comm-mod-toolbar-submit">Filtrar</button>
    </div>

    {{-- Compact filter row — small inline selects, never a giant panel. --}}
    <div class="comm-mod-filters" id="comm-mod-filters" @unless($vm->hasActiveFilters()) hidden @endunless aria-label="Filtros de moderación">
        <div class="comm-mod-filter">
            <label for="cmq_visibility" class="comm-mod-filter__label">Visibilidad</label>
            <select id="cmq_visibility" name="visibility" class="comm-mod-filter__select" data-cmq-autosubmit>
                @foreach($vm->visibilityOptions as $opt)
                <option value="{{ $opt['value'] }}" @selected($vm->filters['visibility'] === $opt['value'])>
                    {{ $opt['label'] }}
                </option>
                @endforeach
            </select>
        </div>

        <div class="comm-mod-filter">
            <label for="cmq_state" class="comm-mod-filter__label">Estado</label>
            <select id="cmq_state" name="state" class="comm-mod-filter__select" data-cmq-autosubmit>
                <option value="">Todos</option>
                @foreach($vm->states as $s)
                <option value="{{ $s['value'] }}" @selected($vm->filters['state'] === $s['value'])>
                    {{ $s['label'] }}
                </option>
                @endforeach
            </select>
        </div>

        <div class="comm-mod-filter">
            <label for="cmq_category" class="comm-mod-filter__label">Categoría</label>
            <select id="cmq_category" name="category" class="comm-mod-filter__select" data-cmq-autosubmit>
                <option value="">Todas</option>
                @foreach($vm->categories as $cat)
                <option value="{{ $cat['id'] }}" @selected($vm->filters['category'] === $cat['id'])>
                    {{ $cat['name'] }}
                </option>
                @endforeach
            </select>
        </div>

        <div class="comm-mod-filter">
            <label for="cmq_building" class="comm-mod-filter__label">Edificio</label>
            <select id="cmq_building" name="building" class="comm-mod-filter__select" data-cmq-autosubmit>
                <option value="">Todos</option>
                @foreach($vm->buildings as $building)
                <option value="{{ $building }}" @selected($vm->filters['building'] === $building)>
                    {{ $building }}
                </option>
                @endforeach
            </select>
        </div>

        <div class="comm-mod-filter">
            <label for="cmq_period" class="comm-mod-filter__label">Período</label>
            <select id="cmq_period" name="period" class="comm-mod-filter__select" data-cmq-autosubmit>
                <option value="" @selected($vm->filters['period'] === '')>Todo el tiempo</option>
                <option value="24h" @selected($vm->filters['period'] === '24h')>Últimas 24h</option>
                <option value="7d" @selected($vm->filters['period'] === '7d')>Últimos 7 días</option>
                <option value="30d" @selected($vm->filters['period'] === '30d')>Últimos 30 días</option>
            </select>
        </div>

        <a href="{{ route('admin.community.moderation') }}" class="comm-mod-filters__clear">
            <x-lucide-x width="14" height="14" stroke-width="2.5" />
            Limpiar filtros
        </a>
    </div>
</form>
