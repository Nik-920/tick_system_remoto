{{-- ── LEFT SIDEBAR SKELETON ────────────────────────────────────
     Two cards: "Atajos" (5 shortcut rows) and "Ubicaciones seguidas" (2 rows).
──────────────────────────────────────────────────────────── --}}
<aside class="comm-sidebar" aria-hidden="true">

    {{-- Atajos card --}}
    <div class="comm-sidebar-card">
        <div class="comm-sk comm-sidebar-card__heading"></div>

        <ul class="comm-shortcut-list">
            <li class="comm-shortcut">
                <div class="comm-sk comm-shortcut__icon"></div>
                <div class="comm-sk comm-shortcut__label comm-shortcut__label--w-32"></div>
                <div class="comm-sk comm-shortcut__arrow"></div>
            </li>
            <li class="comm-shortcut">
                <div class="comm-sk comm-shortcut__icon"></div>
                <div class="comm-sk comm-shortcut__label comm-shortcut__label--w-30"></div>
                <div class="comm-sk comm-shortcut__arrow"></div>
            </li>
            <li class="comm-shortcut">
                <div class="comm-sk comm-shortcut__icon"></div>
                <div class="comm-sk comm-shortcut__label comm-shortcut__label--w-36"></div>
                <div class="comm-sk comm-shortcut__arrow"></div>
            </li>
            <li class="comm-shortcut">
                <div class="comm-sk comm-shortcut__icon"></div>
                <div class="comm-sk comm-shortcut__label comm-shortcut__label--w-28"></div>
                <div class="comm-sk comm-shortcut__arrow"></div>
            </li>
            <li class="comm-shortcut">
                <div class="comm-sk comm-shortcut__icon"></div>
                <div class="comm-sk comm-shortcut__label comm-shortcut__label--w-38"></div>
                <div class="comm-sk comm-shortcut__arrow"></div>
            </li>
        </ul>
    </div>

    {{-- Ubicaciones seguidas card --}}
    <div class="comm-sidebar-card">
        <div class="comm-locations-header">
            <div class="comm-sk comm-locations-header__title"></div>
            <div class="comm-sk comm-locations-header__link"></div>
        </div>

        <ul class="comm-location-list">
            {{-- Location 1 (with star) --}}
            <li class="comm-location">
                <div class="comm-sk comm-location__icon"></div>
                <div class="comm-location__meta">
                    <div class="comm-sk comm-location__name comm-location__name--w-16"></div>
                    <div class="comm-sk comm-location__sub comm-location__sub--w-28"></div>
                </div>
                <div class="comm-sk comm-location__star"></div>
            </li>
            {{-- Location 2 --}}
            <li class="comm-location">
                <div class="comm-sk comm-location__icon"></div>
                <div class="comm-location__meta">
                    <div class="comm-sk comm-location__name comm-location__name--w-20"></div>
                    <div class="comm-sk comm-location__sub comm-location__sub--w-24"></div>
                </div>
            </li>
        </ul>
    </div>

</aside>
