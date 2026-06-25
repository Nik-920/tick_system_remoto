@if (count($communityPreferences) > 0)
<form
    method="POST"
    action="{{ route('profile.community-notifications.update') }}"
    class="profile-card"
    id="commNotifForm"
>
    @csrf
    @method('PATCH')

    <div class="profile-card-header">
        <div class="profile-card-icon">
            <x-lucide-bell width="18" height="18" stroke-width="2" />
        </div>
        <div style="flex:1; min-width:0;">
            <p class="profile-card-title">Preferencias de notificaciones de Comunidad</p>
            <p class="profile-card-subtitle">Estas preferencias controlan notificaciones internas de Comunidad. No afectan notificaciones críticas del sistema.</p>
        </div>
        <span
            id="commActiveBadge"
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
            <span id="commActiveCount">0</span> activas
        </span>
    </div>

    <div class="profile-card-body" style="padding: 0;">

        <div class="profile-pref-list">
            @foreach ($communityPreferences as $type => $pref)
                <div class="profile-pref-item profile-pref-item--group">

                    <span class="profile-pref-text">{{ $pref['label'] }}</span>

                    <div class="profile-pref-channels">
                        <input
                            type="hidden"
                            name="preferences[{{ $type }}]"
                            value="0"
                        >
                        <label
                            class="profile-pref-channel-label"
                            for="comm_toggle_{{ $type }}"
                            title="En la app"
                        >
                            <span class="profile-pref-channel-icon">🖥️</span>
                            <span class="profile-pref-channel-name">En la app</span>
                            <span class="profile-pref-toggle-wrap">
                                <input
                                    type="checkbox"
                                    id="comm_toggle_{{ $type }}"
                                    name="preferences[{{ $type }}]"
                                    value="1"
                                    class="profile-pref-toggle js-comm-pref-toggle"
                                    @checked($pref['enabled'])
                                    aria-label="En la app"
                                >
                                <span class="profile-pref-toggle-track"></span>
                            </span>
                        </label>
                    </div>

                </div>
            @endforeach
        </div>

        <div
            class="profile-form-actions"
            style="padding: 0.85rem 1.25rem; margin-top: 0;"
        >
            <button
                type="submit"
                class="btn-primary"
                id="commSaveBtn"
                style="display: inline-flex; align-items: center; gap: 0.45rem;"
            >
                <span id="commSaveIcon" style="font-size:1rem; line-height:1;">💾</span>
                <span id="commSaveText">Guardar preferencias</span>
            </button>

            <span
                id="commSavedMsg"
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

    const form    = document.getElementById('commNotifForm');
    const toggles = form ? form.querySelectorAll('.js-comm-pref-toggle') : [];
    const countEl = document.getElementById('commActiveCount');
    const badgeEl = document.getElementById('commActiveBadge');
    const saveBtn = document.getElementById('commSaveBtn');

    function updateCount() {
        const active = Array.from(toggles).filter(function (t) { return t.checked; }).length;
        if (countEl) { countEl.textContent = active; }
        if (badgeEl) {
            badgeEl.style.background = active > 0
                ? 'rgba(255,255,255,0.22)'
                : 'rgba(255,255,255,0.08)';
        }
    }

    toggles.forEach(function (t) {
        t.addEventListener('change', updateCount);
        t.addEventListener('change', function () {
            const track = this.nextElementSibling;
            if (!track) { return; }
            track.style.transition = 'none';
            track.style.transform  = 'scale(0.92)';
            requestAnimationFrame(function () {
                track.style.transition = '';
                track.style.transform  = '';
            });
        });
    });

    updateCount();

    if (form && saveBtn) {
        form.addEventListener('submit', function () {
            saveBtn.disabled = true;
            saveBtn.style.opacity = '0.7';
            const icon = document.getElementById('commSaveIcon');
            const text = document.getElementById('commSaveText');
            if (icon) { icon.textContent = '⏳'; }
            if (text) { text.textContent = 'Guardando…'; }
            const list = form.querySelector('.profile-pref-list');
            if (list) { list.classList.add('profile-pref-saved-flash'); }
        });
    }
}());
</script>
@endif
