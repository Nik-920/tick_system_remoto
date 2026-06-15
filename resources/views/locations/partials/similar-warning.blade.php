{{--
    Partial: locations/partials/similar-warning.blade.php

    Muestra el bloque completo de alerta de ubicaciones similares
    y el checkbox de confirmación cuando `confirmation_required` está en sesión.

    Variables de sesión requeridas:
        - similar_locations_warning : array de arrays con keys: id, name, building, floor, room_code
        - confirmation_required     : boolean (para mostrar el bloque de confirmación)

    No modifica lógica backend. Solo UI/Blade.
--}}

@if (session('similar_locations_warning'))
    {{-- ===== ALERTA DE UBICACIONES SIMILARES ===== --}}
    <div
        class="sim-warning"
        role="alert"
        aria-live="assertive"
    >
        {{-- Cabecera de la alerta --}}
        <div class="sim-warning__header">
            <span class="sim-warning__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </span>
            <div>
                <p class="sim-warning__title">Nombre de ubicación ya registrado en esta área</p>
                <p class="sim-warning__subtitle">
                    Existe una ubicación con el mismo nombre en el mismo edificio y piso.
                    Verifica que el código de aula sea correcto antes de continuar.
                </p>
            </div>
        </div>

        {{-- Lista de ubicaciones similares --}}
        <ul class="sim-warning__list" role="list">
            @foreach (session('similar_locations_warning') as $similar)
                <li class="sim-warning__item">
                    <div class="sim-warning__item-body">
                        <p class="sim-warning__item-name">{{ $similar['name'] }}</p>
                        <div class="sim-warning__item-meta">
                            <span>
                                <span class="sim-warning__meta-label">Edificio:</span>
                                {{ $similar['building'] }}
                            </span>
                            @if (!empty($similar['floor']))
                                <span>
                                    <span class="sim-warning__meta-label">Piso:</span>
                                    {{ $similar['floor'] }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="sim-warning__item-actions">
                        <span class="sim-warning__badge">{{ $similar['room_code'] }}</span>
                        <a
                            href="{{ route('locations.edit', $similar['id']) }}"
                            class="sim-warning__link"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Ver ubicación {{ $similar['name'] }} (abre en nueva pestaña)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                <polyline points="15 3 21 3 21 9"/>
                                <line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                            Ver ubicación
                        </a>
                    </div>
                </li>
            @endforeach
        </ul>

        {{-- Nota de acción --}}
        <p class="sim-warning__footer-note">
            Si el código de aula es distinto y corresponde a un espacio físico diferente, marca la confirmación y vuelve a guardar.
        </p>
    </div>
@endif

@if (session('confirmation_required'))
    {{-- ===== BLOQUE DE CONFIRMACIÓN EXPLÍCITA ===== --}}
    <div class="sim-confirm">
        <div class="sim-confirm__inner">
            <input
                type="hidden"
                name="confirm_similar_location"
                value="0"
            >
            <div class="sim-confirm__checkbox-wrap">
                <input
                    id="confirm_similar_location"
                    name="confirm_similar_location"
                    type="checkbox"
                    value="1"
                    @checked(old('confirm_similar_location', '0') === '1')
                    class="sim-confirm__checkbox"
                    aria-describedby="confirm-similar-hint"
                >
            </div>
            <div class="sim-confirm__text">
                <label for="confirm_similar_location" class="sim-confirm__label">
                    Confirmo que esta es una ubicación diferente a las ubicaciones similares listadas.
                </label>
                <p id="confirm-similar-hint" class="sim-confirm__hint">
                    Marca esta opción solo si verificaste que el código de aula corresponde a un espacio físico distinto.
                </p>
                @error('confirm_similar_location')
                    <p class="locs-field-error">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
@endif
