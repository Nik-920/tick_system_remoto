{{-- Filters partial for the community moderation queue --}}
<section class="comm-mod-filters">
    <form method="GET"
          action="{{ route('admin.community.moderation') }}"
          class="comm-mod-filter-form">

        <div class="comm-mod-filter-grid">

            <div>
                <label for="cmq_q" class="comm-mod-field-label">Búsqueda</label>
                <input id="cmq_q"
                       type="text"
                       name="q"
                       value="{{ $vm->filters['q'] }}"
                       placeholder="Título, ubicación o categoría"
                       class="comm-mod-field">
            </div>

            <div>
                <label for="cmq_visibility" class="comm-mod-field-label">Visibilidad</label>
                <select id="cmq_visibility" name="visibility" class="comm-mod-field">
                    @foreach($vm->visibilityOptions as $opt)
                    <option value="{{ $opt['value'] }}"
                            @selected($vm->filters['visibility'] === $opt['value'])>
                        {{ $opt['label'] }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="cmq_state" class="comm-mod-field-label">Estado</label>
                <select id="cmq_state" name="state" class="comm-mod-field">
                    <option value="">Todos los estados</option>
                    @foreach($vm->states as $s)
                    <option value="{{ $s['value'] }}"
                            @selected($vm->filters['state'] === $s['value'])>
                        {{ $s['label'] }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="cmq_category" class="comm-mod-field-label">Categoría</label>
                <select id="cmq_category" name="category" class="comm-mod-field">
                    <option value="">Todas las categorías</option>
                    @foreach($vm->categories as $cat)
                    <option value="{{ $cat['id'] }}"
                            @selected($vm->filters['category'] === $cat['id'])>
                        {{ $cat['name'] }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="cmq_building" class="comm-mod-field-label">Edificio</label>
                <select id="cmq_building" name="building" class="comm-mod-field">
                    <option value="">Todos los edificios</option>
                    @foreach($vm->buildings as $building)
                    <option value="{{ $building }}"
                            @selected($vm->filters['building'] === $building)>
                        {{ $building }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="cmq_period" class="comm-mod-field-label">Período</label>
                <select id="cmq_period" name="period" class="comm-mod-field">
                    <option value="" @selected($vm->filters['period'] === '')>Todo el tiempo</option>
                    <option value="24h" @selected($vm->filters['period'] === '24h')>Últimas 24h</option>
                    <option value="7d" @selected($vm->filters['period'] === '7d')>Últimos 7 días</option>
                    <option value="30d" @selected($vm->filters['period'] === '30d')>Últimos 30 días</option>
                </select>
            </div>

        </div>

        <div class="comm-mod-filter-actions">
            <button type="submit" class="btn-primary">Filtrar</button>
            <a href="{{ route('admin.community.moderation') }}" class="btn-secondary">Limpiar</a>
        </div>

    </form>
</section>
