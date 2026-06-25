@if (count($ticketPreferences) > 0)
<form method="POST" action="{{ route('profile.ticket-notifications.update') }}" class="profile-card">
    @csrf
    @method('PATCH')

    <div class="profile-card-header">
        <div class="profile-card-icon">
            <x-lucide-bell-ring width="18" height="18" stroke-width="2" />
        </div>
        <div>
            <p class="profile-card-title">Preferencias de notificaciones de tickets</p>
            <p class="profile-card-subtitle">Controla qué notificaciones recibes y en qué canales. No afecta notificaciones críticas de seguridad.</p>
        </div>
    </div>

    <div class="profile-card-body">
        <div class="profile-pref-list">
            @foreach ($ticketPreferences as $type => $pref)
                <div class="profile-pref-item profile-pref-item--group">
                    <span class="profile-pref-text">{{ $pref['label'] }}</span>
                    <div class="profile-pref-channels">
                        @foreach ($pref['channels'] as $channel => $enabled)
                            <label class="profile-pref-channel-label">
                                <input type="hidden" name="preferences[{{ $type }}][{{ $channel }}]" value="0">
                                <input type="checkbox"
                                       name="preferences[{{ $type }}][{{ $channel }}]"
                                       value="1"
                                       class="profile-pref-checkbox"
                                       @checked($enabled)>
                                <span class="profile-pref-channel-name">
                                    @if ($channel === 'in_app') En la app @else Push móvil @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="profile-form-actions">
            <button type="submit" class="btn-primary">Guardar preferencias</button>
        </div>
    </div>
</form>
@endif
