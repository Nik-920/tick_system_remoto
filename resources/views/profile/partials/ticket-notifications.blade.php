@if (count($ticketPreferences) > 0)
<form
    method="POST"
    action="{{ route('profile.ticket-notifications.update') }}"
    class="profile-card"
    id="ticketNotifForm"
>
    @csrf
    @method('PATCH')

    <div class="profile-card-header">
        <div class="profile-card-icon">
            <x-lucide-bell-ring width="18" height="18" stroke-width="2" />
        </div>
        <div style="flex:1; min-width:0;">
            <p class="profile-card-title">Preferencias de notificaciones de tickets</p>
            <p class="profile-card-subtitle">Controla qué notificaciones recibes y en qué canales. No afecta notificaciones críticas de seguridad.</p>
        </div>
        {{-- Live badge: active count --}}
        <span
            id="prefActiveBadge"
            title="Notificaciones activas"
            style="
                display: inline-flex;
                align-items: center;
                gap: 0.3rem;
                font-size: 0.7rem;
                font-weight: 700;
                color: rgba(255,255,255,0.9);
                background: rgba(255,255,255,0.15);
                border: 1px solid rgba(255,255,255,0.25);
                border-radius: 999px;
                padding: 0.2rem 0.6rem;
                white-space: nowrap;
                flex-shrink: 0;
                transition: background 0.2s;
            "
        >
            <span id="prefActiveCount">0</span> activas
        </span>
    </div>

    <div class="profile-card-body" style="padding: 0;">

        <div class="profile-pref-list">
            @foreach ($ticketPreferences as $type => $pref)
                <div class="profile-pref-item profile-pref-item--group">

                    {{-- Label del tipo de notificación --}}
                    <span class="profile-pref-text">{{ $pref['label'] }}</span>

                    {{-- Canales con toggles pill --}}
                    <div class="profile-pref-channels">
                        @foreach ($pref['channels'] as $channel => $enabled)
                            @php
                                $isInApp = $channel === 'in_app';
                                $channelLabel = $isInApp ? 'En la app' : 'Push móvil';
                                $channelIcon  = $isInApp ? '🖥️' : '📱';
                                $inputId = 'toggle_' . $type . '_' . $channel;
                            @endphp

                            {{-- Hidden field to ensure unchecked = 0 --}}
                            <input
                                type="hidden"
                                name="preferences[{{ $type }}][{{ $channel }}]"
                                value="0"
                            >

                            <label
                                class="profile-pref-channel-label"
                                for="{{ $inputId }}"
                                title="{{ $channelLabel }}"
                            >
                                {{-- Icon badge --}}
                                <span class="profile-pref-channel-icon">{{ $channelIcon }}</span>

                                {{-- Channel name --}}
                                <span class="profile-pref-channel-name">{{ $channelLabel }}</span>

                                {{-- Toggle pill --}}
                                <span class="profile-pref-toggle-wrap">
                                    <input
                                        type="checkbox"
                                        id="{{ $inputId }}"
                                        name="preferences[{{ $type }}][{{ $channel }}]"
                                        value="1"
                                        class="profile-pref-toggle js-pref-toggle"
                                        @checked($enabled)
                                        aria-label="{{ $channelLabel }}"
                                    >
                                    <span class="profile-pref-toggle-track"></span>
                                </span>
                            </label>

                        @endforeach
                    </div>

                </div>
            @endforeach
        </div>

        {{-- Actions bar --}}
        <div
            class="profile-form-actions"
            style="padding: 0.85rem 1.25rem; margin-top: 0;"
        >
            <button
                type="submit"
                class="btn-primary"
                id="prefSaveBtn"
                style="display: inline-flex; align-items: center; gap: 0.45rem;"
            >
                <span id="prefSaveIcon" style="font-size:1rem; line-height:1;">💾</span>
                <span id="prefSaveText">Guardar preferencias</span>
            </button>

            <span
                id="prefSavedMsg"
                style="
                    display: none;
                    font-size: 0.8rem;
                    font-weight: 600;
                    color: var(--color-success);
                    align-items: center;
                    gap: 0.3rem;
                "
            >
                ✅ ¡Guardado!
            </span>
        </div>

    </div>
</form>

<script>
(function () {
    'use strict';

    const form       = document.getElementById('ticketNotifForm');
    const toggles    = form ? form.querySelectorAll('.js-pref-toggle') : [];
    const countEl    = document.getElementById('prefActiveCount');
    const badgeEl    = document.getElementById('prefActiveBadge');
    const saveBtn    = document.getElementById('prefSaveBtn');
    const savedMsg   = document.getElementById('prefSavedMsg');

    /* ── Live active count ── */
    function updateCount() {
        const active = Array.from(toggles).filter(t => t.checked).length;
        if (countEl) countEl.textContent = active;
        if (badgeEl) {
            badgeEl.style.background = active > 0
                ? 'rgba(255,255,255,0.22)'
                : 'rgba(255,255,255,0.08)';
        }
    }

    toggles.forEach(t => {
        t.addEventListener('change', updateCount);

        /* Ripple on the track when toggled */
        t.addEventListener('change', function () {
            const track = this.nextElementSibling;
            if (!track) return;
            track.style.transition = 'none';
            track.style.transform  = 'scale(0.92)';
            requestAnimationFrame(() => {
                track.style.transition = '';
                track.style.transform  = '';
            });
        });
    });

    /* ── Initial count on load ── */
    updateCount();

    /* ── Submit feedback ── */
    if (form && saveBtn) {
        form.addEventListener('submit', function () {
            saveBtn.disabled = true;
            saveBtn.style.opacity = '0.7';

            const icon = document.getElementById('prefSaveIcon');
            const text = document.getElementById('prefSaveText');
            if (icon) icon.textContent = '⏳';
            if (text) text.textContent = 'Guardando…';

            /* Flash the card border on success (Laravel will reload the page) */
            const card = form.querySelector('.profile-pref-list');
            if (card) card.classList.add('profile-pref-saved-flash');
        });
    }

}());
</script>
@endif
