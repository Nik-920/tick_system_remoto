<div class="cat-card cat-card--tone-{{ $toneIndex }}" role="article" aria-label="{{ $category->name }}">

    {{-- Header: icon box + community badge --}}
    <div class="cat-card__head">
        <div class="cat-card__icon-box">
            @if (is_string($category->icon) && filter_var($category->icon, FILTER_VALIDATE_URL))
                <img
                    src="{{ $category->icon }}"
                    alt=""
                    class="cat-card__icon-img"
                    loading="lazy"
                    decoding="async"
                >
            @elseif (is_string($category->icon) && preg_match('/^[a-z0-9-]+$/', $category->icon) === 1 && $category->icon !== '')
                <x-dynamic-component
                    :component="'lucide-'.$category->icon"
                    class="cat-card__icon-lucide"
                    width="24"
                    height="24"
                    stroke-width="1.75"
                    aria-hidden="true"
                />
            @else
                <x-lucide-tag
                    class="cat-card__icon-lucide"
                    width="24"
                    height="24"
                    stroke-width="1.75"
                    aria-hidden="true"
                />
            @endif
        </div>

        @if ($category->community_visibility_locked)
            <span class="cats-community-badge cats-community-badge--locked">
                <x-lucide-lock width="10" height="10" stroke-width="2.5" aria-hidden="true" />
                Bloqueado
            </span>
        @elseif ($category->community_default_visible)
            <span class="cats-community-badge cats-community-badge--public">
                <x-lucide-globe width="10" height="10" stroke-width="2.5" aria-hidden="true" />
                Público
            </span>
        @else
            <span class="cats-community-badge cats-community-badge--private">
                <x-lucide-lock width="10" height="10" stroke-width="2.5" aria-hidden="true" />
                Privado
            </span>
        @endif
    </div>

    {{-- Body: title + activity bar + stats --}}
    <div class="cat-card__body">
        <h3 class="cat-card__title">{{ $category->name }}</h3>

        <div class="cat-card__activity">
            <div class="cat-card__activity-track" aria-hidden="true">
                <div
                    class="cat-card__activity-bar"
                    style="width: {{ $maxActivity > 0 ? min(100, (($category->activity_total ?? 0) / $maxActivity) * 100) : 0 }}%"
                ></div>
            </div>
            <span class="cat-card__activity-label">
                {{ $category->activity_total ?? 0 }}
                {{ ($category->activity_total ?? 0) === 1 ? 'actividad' : 'actividades' }}
            </span>
        </div>

        <div class="cat-card__stats">
            <div class="cat-card__stat">
                <span class="cat-card__stat-value">{{ number_format((int) $category->incident_history_count) }}</span>
                <span class="cat-card__stat-label">INCIDENCIAS</span>
            </div>
            <div class="cat-card__stat-divider" aria-hidden="true"></div>
            <div class="cat-card__stat">
                <span class="cat-card__stat-value">{{ number_format((int) $category->tickets_count) }}</span>
                <span class="cat-card__stat-label">TICKETS</span>
            </div>
        </div>
    </div>

    {{-- Actions: edit + delete --}}
    <div class="cat-card__actions">
        <a href="{{ route('categories.edit', $category) }}" class="cat-card__btn cat-card__btn--edit">
            <x-lucide-pencil width="13" height="13" stroke-width="2.2" aria-hidden="true" />
            Editar
        </a>

        @can('delete', $category)
            @if (((int) $category->tickets_count) > 0 || ((int) $category->incident_history_count) > 0)
                <button
                    type="button"
                    class="cat-card__btn cat-card__btn--delete"
                    disabled
                    aria-disabled="true"
                    title="Tiene tickets o incidencias asociadas; no se puede eliminar."
                >
                    <x-lucide-trash-2 width="13" height="13" stroke-width="2.2" aria-hidden="true" />
                    Eliminar
                </button>
            @else
                <form
                    method="POST"
                    action="{{ route('categories.destroy', $category) }}"
                    class="cat-card__delete-form"
                    onsubmit="return confirm('¿Seguro que deseas eliminar la categoría «{{ $category->name }}»? Esta acción no se puede deshacer.');"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cat-card__btn cat-card__btn--delete">
                        <x-lucide-trash-2 width="13" height="13" stroke-width="2.2" aria-hidden="true" />
                        Eliminar
                    </button>
                </form>
            @endif
        @endcan
    </div>
</div>
