@extends('layouts.app')

@section('title', 'Nueva ubicación')

@section('content')
    <div class="locs-create-layout">

        {{-- ===== COLUMNA IZQUIERDA — FORM ===== --}}
        <div class="locs-create-left">

            <header class="locs-form-header">
                <div>
                    <h1 class="locs-form-title">Nueva ubicación</h1>
                    <p class="locs-form-subtitle">Registra un espacio para operación y trazabilidad QR.</p>
                </div>
                <a href="{{ route('locations.index') }}" class="btn-secondary">← Volver</a>
            </header>

            @if ($errors->any())
                <div class="alert-error">
                    <p class="font-semibold mb-2">Corrige los siguientes errores:</p>
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li class="text-sm">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('locations.store') }}" class="locs-form-card">
                @csrf

                <div class="locs-form-card-body">

                    {{-- Nombre --}}
                    <div class="locs-form-group">
                        <label for="name" class="locs-field-label">Nombre *</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required
                               placeholder="Ej: Laboratorio de Redes, Aula Magna"
                               class="locs-field">
                        @error('name')
                            <p class="locs-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Edificio + Piso --}}
                    <div class="locs-form-grid">
                        <div class="locs-form-group">
                            <label for="building" class="locs-field-label">Edificio *</label>
                            <input id="building" name="building" type="text" value="{{ old('building') }}" required
                                   placeholder="Ej: Ingeniería, Administración"
                                   class="locs-field">
                            @error('building')
                                <p class="locs-field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="locs-form-group">
                            <label for="floor" class="locs-field-label">Piso</label>
                            <input id="floor" name="floor" type="text" value="{{ old('floor') }}"
                                   placeholder="Ej: 1, 2, 3"
                                   class="locs-field">
                            @error('floor')
                                <p class="locs-field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Código de aula --}}
                    <div class="locs-form-group">
                        <label for="room_code" class="locs-field-label">Código de aula *</label>
                        <input id="room_code" name="room_code" type="text" value="{{ old('room_code') }}" required
                               placeholder="Ej: ING-2-201, ADM-1-101"
                               class="locs-field">
                        @error('room_code')
                            <p class="locs-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Activa --}}
                    <div class="locs-form-group">
                        <p class="locs-field-label">Estado de la ubicación</p>
                        <label class="locs-toggle-wrap">
                            <input type="hidden" name="is_active" value="0">
                            <input id="is_active" name="is_active" type="checkbox" value="1"
                                   @checked(old('is_active', '1') === '1')
                                   class="locs-toggle-input">
                            <span class="locs-toggle-track">
                                <span class="locs-toggle-thumb"></span>
                            </span>
                            <span class="locs-toggle-label">Ubicación activa</span>
                        </label>
                        <p class="locs-field-hint">Las ubicaciones activas aparecen disponibles al crear tickets.</p>
                    </div>

                    <div class="locs-form-actions">
                        <button type="submit" class="btn-primary">Guardar ubicación</button>
                        <a href="{{ route('locations.index') }}" class="btn-secondary">Cancelar</a>
                    </div>

                </div>
            </form>
        </div>

        {{-- ===== COLUMNA DERECHA — INFO (solo desktop) ===== --}}
        <aside class="locs-create-sidebar">

            {{-- Info card --}}
            <div class="locs-sidebar-card locs-sidebar-hero-card">
                <div class="locs-sidebar-icon-wrap">
                    <x-lucide-home width="30" height="30" stroke-width="1.5" />
                </div>
                <h3 class="locs-sidebar-title">Nueva ubicación</h3>
                <p class="locs-sidebar-subtitle">El espacio quedará disponible para asignar tickets y generar su código QR de trazabilidad.</p>
            </div>

            {{-- QR info --}}
            <div class="locs-sidebar-card">
                <p class="locs-sidebar-section-title">¿Cómo funciona el QR?</p>
                <ul class="locs-sidebar-guide-list">
                    <li>
                        <span class="locs-sidebar-dot locs-sidebar-dot--pending"></span>
                        <div>
                            <p class="locs-sidebar-item-title">Pendiente</p>
                            <p class="locs-sidebar-item-text">El QR aún no se ha generado para este espacio.</p>
                        </div>
                    </li>
                    <li>
                        <span class="locs-sidebar-dot locs-sidebar-dot--processing"></span>
                        <div>
                            <p class="locs-sidebar-item-title">Procesando</p>
                            <p class="locs-sidebar-item-text">El sistema está generando el código QR.</p>
                        </div>
                    </li>
                    <li>
                        <span class="locs-sidebar-dot locs-sidebar-dot--ready"></span>
                        <div>
                            <p class="locs-sidebar-item-title">Listo</p>
                            <p class="locs-sidebar-item-text">QR generado y disponible para escanear.</p>
                        </div>
                    </li>
                    <li>
                        <span class="locs-sidebar-dot locs-sidebar-dot--failed"></span>
                        <div>
                            <p class="locs-sidebar-item-title">Fallido</p>
                            <p class="locs-sidebar-item-text">Hubo un error. Contacta al administrador.</p>
                        </div>
                    </li>
                </ul>
            </div>

            {{-- Tips --}}
            <div class="locs-sidebar-card locs-sidebar-tips">
                <p class="locs-sidebar-section-title">Buenas prácticas</p>
                <ul class="locs-sidebar-guide-list">
                    <li>
                        <span class="locs-sidebar-dot locs-sidebar-dot--blue"></span>
                        <div>
                            <p class="locs-sidebar-item-title">Código de aula único</p>
                            <p class="locs-sidebar-item-text">Usa un formato consistente, como <strong>ING-2-201</strong> para fácil identificación.</p>
                        </div>
                    </li>
                    <li>
                        <span class="locs-sidebar-dot locs-sidebar-dot--blue"></span>
                        <div>
                            <p class="locs-sidebar-item-title">Nombre descriptivo</p>
                            <p class="locs-sidebar-item-text">Incluye el tipo de espacio: "Laboratorio", "Aula", "Sala de reuniones".</p>
                        </div>
                    </li>
                    <li>
                        <span class="locs-sidebar-dot locs-sidebar-dot--blue"></span>
                        <div>
                            <p class="locs-sidebar-item-title">Activar al crear</p>
                            <p class="locs-sidebar-item-text">Marca como activa solo si el espacio ya está operativo.</p>
                        </div>
                    </li>
                </ul>
            </div>

        </aside>
    </div>
@endsection