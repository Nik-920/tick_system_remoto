@extends('layouts.app')

@section('title', 'Editar ubicación')

@section('content')
    <div class="locs-edit-layout">

        <div class="locs-edit-left">

            <header class="locs-form-header">
                <div>
                    <h1 class="locs-form-title">Editar ubicación</h1>
                    <p class="locs-form-subtitle">Mantenimiento de datos y estado QR para <strong>{{ $location->room_code }}</strong></p>
                </div>
                <a href="{{ route('locations.index') }}" class="btn-secondary">← Volver</a>
            </header>

            @if (session('status'))
                <div class="alert-success">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="alert-error">{{ session('error') }}</div>
            @endif
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

            <form method="POST" action="{{ route('locations.update', $location) }}" class="locs-form-card">
                @csrf
                @method('PATCH')

                <div class="locs-edit-form-header">
                    <div class="locs-edit-form-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </div>
                    <div>
                        <h2 class="locs-edit-form-title">Datos de la ubicación</h2>
                        <p class="locs-edit-form-subtitle">Actualiza nombre, edificio, piso y código de aula</p>
                    </div>
                </div>

                <div class="locs-form-card-body">
                    <div class="locs-form-group">
                        <label for="name" class="locs-field-label">Nombre *</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $location->name) }}" required class="locs-field">
                        @error('name') <p class="locs-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="locs-form-grid">
                        <div class="locs-form-group">
                            <label for="building" class="locs-field-label">Edificio *</label>
                            <input id="building" name="building" type="text" value="{{ old('building', $location->building) }}" required class="locs-field">
                            @error('building') <p class="locs-field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="locs-form-group">
                            <label for="floor" class="locs-field-label">Piso</label>
                            <input id="floor" name="floor" type="text" value="{{ old('floor', $location->floor) }}" class="locs-field">
                            @error('floor') <p class="locs-field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="locs-form-group">
                        <label for="room_code" class="locs-field-label">Código de aula *</label>
                        <input id="room_code" name="room_code" type="text" value="{{ old('room_code', $location->room_code) }}" required class="locs-field">
                        @error('room_code') <p class="locs-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="locs-form-group">
                        <p class="locs-field-label">Estado de la ubicación</p>
                        <label class="locs-toggle-wrap">
                            <input type="hidden" name="is_active" value="0">
                            <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $location->is_active ? '1' : '0') === '1') class="locs-toggle-input">
                            <span class="locs-toggle-track"><span class="locs-toggle-thumb"></span></span>
                            <span class="locs-toggle-label">Ubicación activa</span>
                        </label>
                        <p class="locs-field-hint">Las ubicaciones activas aparecen disponibles al crear tickets.</p>
                    </div>
                    <div class="locs-form-actions">
                        <button type="submit" class="btn-primary">Guardar cambios</button>
                        <a href="{{ route('locations.index') }}" class="btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>

            @php
                $qrStatus = $location->qr_generation_status ?? 'pending';
                $qrClass = match($qrStatus) { 'ready' => 'locs-badge--qr-ready', 'processing' => 'locs-badge--qr-processing', 'failed' => 'locs-badge--qr-failed', default => 'locs-badge--qr-pending' };
                $qrLabels = ['pending' => 'Pendiente', 'processing' => 'Procesando', 'ready' => 'Listo', 'failed' => 'Fallido'];
            @endphp

            <div class="locs-qr-section">
                <div class="locs-qr-header">
                    <div class="locs-qr-header-left">
                        <div class="locs-qr-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM17 17h3v3h-3zM14 20h3"/></svg>
                        </div>
                        <div>
                            <h2 class="locs-qr-title">Estado QR</h2>
                            <p class="locs-qr-subtitle">Trazabilidad del código QR institucional</p>
                        </div>
                    </div>
                    <span class="locs-badge {{ $qrClass }} locs-badge--lg">{{ $qrLabels[$qrStatus] ?? $qrStatus }}</span>
                </div>

                <div class="locs-qr-grid">
                    <div class="locs-qr-stat">
                        <p class="locs-qr-stat-label">QR Token</p>
                        <p class="locs-qr-stat-val locs-qr-token">{{ $location->qr_token ?? 'No generado' }}</p>
                    </div>
                    <div class="locs-qr-stat">
                        <p class="locs-qr-stat-label">Generado en</p>
                        <p class="locs-qr-stat-val">{{ $location->qr_generated_at?->format('d/m/Y H:i') ?? '—' }}</p>
                    </div>
                    <div class="locs-qr-stat">
                        <p class="locs-qr-stat-label">Tickets asociados</p>
                        <p class="locs-qr-stat-val locs-qr-stat-val--blue">{{ $location->tickets_count }}</p>
                    </div>
                    <div class="locs-qr-stat">
                        <p class="locs-qr-stat-label">Historial incidencias</p>
                        <p class="locs-qr-stat-val locs-qr-stat-val--blue">{{ $location->incident_history_count }}</p>
                    </div>
                </div>

                @if ($location->qr_last_error)
                    <div class="locs-qr-error">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <p class="locs-qr-error-text">Último error: {{ $location->qr_last_error }}</p>
                    </div>
                @endif

                <div class="locs-qr-actions">
                    @if ($location->qr_image_url)
                        <a href="{{ $location->qr_image_url }}" target="_blank" rel="noopener noreferrer" class="locs-qr-btn-view">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            Ver imagen QR
                        </a>
                    @endif
                    <form method="POST" action="{{ route('locations.regenerate-qr', $location) }}" class="inline">
                        @csrf
                        <button type="submit" class="locs-qr-btn-regen">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                            Regenerar QR
                        </button>
                    </form>
                </div>
            </div>

            @can('delete', $location)
                <div class="locs-danger-zone">
                    <div class="locs-danger-inner">
                        <div>
                            <h2 class="locs-danger-title">Zona peligrosa</h2>
                            <p class="locs-danger-text">La eliminación es permanente y se bloqueará si la ubicación tiene tickets o historial de incidencias asociados.</p>
                        </div>
                        <form method="POST" action="{{ route('locations.destroy', $location) }}" onsubmit="return confirm('Esta acción es irreversible. ¿Confirmas la eliminación de la ubicación?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="locs-danger-btn">Eliminar ubicación</button>
                        </form>
                    </div>
                </div>
            @endcan

        </div>

        <aside class="locs-edit-sidebar">

            <div class="locs-sidebar-card locs-sidebar-preview">
                <div class="locs-sidebar-preview-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
                <h3 class="locs-sidebar-name">{{ $location->name }}</h3>
                <p class="locs-sidebar-code">{{ $location->room_code }}</p>
                <span class="locs-badge {{ $location->is_active ? 'locs-badge--active' : 'locs-badge--inactive' }}">
                    {{ $location->is_active ? 'Activa' : 'Inactiva' }}
                </span>
            </div>

            <div class="locs-sidebar-card">
                <p class="locs-sidebar-section-title">Métricas operativas</p>
                <div class="locs-sidebar-metrics">
                    <div class="locs-sidebar-metric">
                        <p class="locs-sidebar-metric-val">{{ $location->tickets_count }}</p>
                        <p class="locs-sidebar-metric-label">Tickets</p>
                    </div>
                    <div class="locs-sidebar-metric-divider"></div>
                    <div class="locs-sidebar-metric">
                        <p class="locs-sidebar-metric-val">{{ $location->incident_history_count }}</p>
                        <p class="locs-sidebar-metric-label">Incidencias</p>
                    </div>
                </div>
            </div>

            <div class="locs-sidebar-card locs-sidebar-qr">
                <p class="locs-sidebar-section-title">QR del espacio</p>
                <div class="locs-sidebar-qr-status">
                    <div class="locs-sidebar-qr-dot locs-sidebar-qr-dot--{{ $qrStatus }}"></div>
                    <div>
                        <p class="locs-sidebar-qr-label">{{ $qrLabels[$qrStatus] ?? $qrStatus }}</p>
                        <p class="locs-sidebar-item-text">{{ $location->qr_generated_at?->format('d/m/Y') ?? 'Aún no generado' }}</p>
                    </div>
                </div>
                @if ($location->qr_image_url)
                    <div class="locs-sidebar-qr-preview">
                        <img src="{{ $location->qr_image_url }}" alt="QR Code" class="locs-sidebar-qr-img">
                        <p style="margin-top:.4rem;text-align:center;font-size:.72rem;color:#64748b;">Escanea para reportar</p>
                    </div>
                @else
                    <div class="locs-sidebar-qr-empty">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:#cbd5e1;"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <p class="locs-sidebar-item-text">QR no disponible aún</p>
                    </div>
                @endif
            </div>

            <div class="locs-sidebar-card">
                <p class="locs-sidebar-section-title">Ubicación física</p>
                <ul class="locs-sidebar-info-list">
                    <li><span class="locs-sidebar-info-label">Edificio</span><span class="locs-sidebar-info-val">{{ $location->building }}</span></li>
                    <li><span class="locs-sidebar-info-label">Piso</span><span class="locs-sidebar-info-val">{{ $location->floor ?? '—' }}</span></li>
                    <li><span class="locs-sidebar-info-label">Código</span><span class="locs-sidebar-info-val">{{ $location->room_code }}</span></li>
                </ul>
            </div>

        </aside>
    </div>
@endsection