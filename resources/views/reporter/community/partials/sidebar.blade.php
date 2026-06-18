{{-- ── LEFT SIDEBAR (REAL DATA) ────────────────────────────────────
     Atajos: static shortcut links.
     Zonas con actividad: top locations from DB (no user data).
     Categorías frecuentes: top categories from DB.
──────────────────────────────────────────────────────────── --}}
<aside class="comm-sidebar" aria-label="Panel lateral">

    {{-- ── ATAJOS ────────────────────────────────────────────────── --}}
    <div class="comm-sidebar-card">
        <h3 class="comm-sidebar-card__title">Atajos</h3>

        <ul class="comm-shortcut-list">
            @foreach ($feed->shortcuts as $shortcut)
                <li class="comm-shortcut">
                    <a href="{{ $shortcut['url'] }}" class="comm-shortcut__link">
                        <span class="comm-shortcut__icon" aria-hidden="true">
                            <x-dynamic-component
                                :component="'lucide-'.($shortcut['icon'] ?: 'link')"
                                width="15"
                                height="15"
                                stroke-width="2"
                            />
                        </span>
                        <span class="comm-shortcut__label-text">{{ $shortcut['label'] }}</span>
                        <x-lucide-chevron-right class="comm-shortcut__arrow-icon" width="13" height="13" stroke-width="2" />
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- ── ZONAS CON ACTIVIDAD ────────────────────────────────────── --}}
    @if (count($feed->activeLocations) > 0)
        <div class="comm-sidebar-card">
            <div class="comm-locations-header">
                <h3 class="comm-locations-header__title-text">Zonas con actividad</h3>
            </div>

            <ul class="comm-location-list">
                @foreach ($feed->activeLocations as $location)
                    <li class="comm-location">
                        <a href="{{ $location['url'] }}" class="comm-location__link">
                            <span class="comm-location__icon" aria-hidden="true">
                                <x-lucide-map-pin width="14" height="14" stroke-width="2" />
                            </span>
                            <span class="comm-location__meta">
                                <span class="comm-location__name-text">{{ $location['room_code'] }}</span>
                                <span class="comm-location__sub-text">{{ $location['building'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── CATEGORÍAS FRECUENTES ──────────────────────────────────── --}}
    @if (count($feed->hotCategories) > 0)
        <div class="comm-sidebar-card">
            <h3 class="comm-sidebar-card__title">Categorías frecuentes</h3>
            <ul class="comm-shortcut-list">
                @foreach ($feed->hotCategories as $cat)
                    <li class="comm-shortcut">
                        <a href="{{ $cat['url'] }}" class="comm-shortcut__link">
                            <span class="comm-shortcut__icon" aria-hidden="true">
                                <x-dynamic-component
                                    :component="'lucide-'.($cat['icon'] ?: 'tag')"
                                    width="15"
                                    height="15"
                                    stroke-width="2"
                                />
                            </span>
                            <span class="comm-shortcut__label-text">{{ $cat['name'] }}</span>
                            <x-lucide-chevron-right class="comm-shortcut__arrow-icon" width="13" height="13" stroke-width="2" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

</aside>
