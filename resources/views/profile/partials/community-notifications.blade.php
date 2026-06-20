@if (count($communityPreferences) > 0)
<form method="POST" action="{{ route('profile.community-notifications.update') }}" class="profile-card">
    @csrf
    @method('PATCH')

    <div class="profile-card-header">
        <div class="profile-card-icon">
            <x-lucide-bell width="18" height="18" stroke-width="2" />
        </div>
        <div>
            <p class="profile-card-title">Preferencias de notificaciones de Comunidad</p>
            <p class="profile-card-subtitle">Estas preferencias controlan notificaciones internas de Comunidad. No afectan notificaciones críticas del sistema.</p>
        </div>
    </div>

    <div class="profile-card-body">
        <div class="profile-pref-list">
            @foreach ($communityPreferences as $type => $pref)
                <label class="profile-pref-item">
                    <input type="hidden" name="preferences[{{ $type }}]" value="0">
                    <input type="checkbox"
                           name="preferences[{{ $type }}]"
                           value="1"
                           class="profile-pref-checkbox"
                           @checked($pref['enabled'])>
                    <span class="profile-pref-text">{{ $pref['label'] }}</span>
                </label>
            @endforeach
        </div>

        <div class="profile-form-actions">
            <button type="submit" class="btn-primary">Guardar preferencias</button>
        </div>
    </div>
</form>
@endif
