@extends('layouts.app')

@section('title', 'Editar categoría')

@section('content')
    <div class="cats-edit-layout">

        {{-- ===== COLUMNA IZQUIERDA ===== --}}
        <div class="cats-edit-left">

            {{-- Header --}}
            <header class="cats-form-header">
                <div>
                    <h1 class="cats-form-title">Editar categoría</h1>
                    <p class="cats-form-subtitle">Actualizando: <strong>{{ $category->name }}</strong></p>
                </div>
                <a href="{{ route('categories.index') }}" class="btn-secondary">← Volver</a>
            </header>

            {{-- Alerts --}}
            @if (session('status'))
                <div class="alert-success">{{ session('status') }}</div>
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

            {{-- Stats rápidos --}}
            <div class="cats-edit-stats">
                <article class="cats-edit-stat">
                    <p class="cats-edit-stat-label">Tickets asociados</p>
                    <p class="cats-edit-stat-value">{{ $category->tickets_count }}</p>
                </article>
                <article class="cats-edit-stat">
                    <p class="cats-edit-stat-label">Incidencias registradas</p>
                    <p class="cats-edit-stat-value">{{ $category->incident_history_count }}</p>
                </article>
            </div>

            {{-- Form --}}
            <form method="POST" action="{{ route('categories.update', $category) }}"
                  enctype="multipart/form-data" class="cats-form-card">
                @csrf
                @method('PATCH')

                <div class="cats-form-card-body">

                    <div class="cats-form-group">
                        <label for="name" class="cats-field-label">Nombre *</label>
                        <input id="name" name="name" type="text"
                               value="{{ old('name', $category->name) }}" required
                               class="cats-field">
                        @error('name')
                            <p class="cats-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="cats-form-group">
                        <label for="icon" class="cats-field-label">Icono (texto o URL)</label>
                        <input id="icon" name="icon" type="text"
                               value="{{ old('icon', $category->icon) }}"
                               placeholder="wrench, alert, tools o URL de imagen"
                               class="cats-field">
                        @error('icon')
                            <p class="cats-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    @php $resolvedIcon = old('icon', $category->icon); @endphp
                    @if (is_string($resolvedIcon) && filter_var($resolvedIcon, FILTER_VALIDATE_URL))
                        <div class="cats-form-group">
                            <p class="cats-field-label">Vista previa del icono actual</p>
                            <img src="{{ $resolvedIcon }}" alt="Icono actual" class="cats-icon-preview-img">
                        </div>
                    @endif

                    <div class="cats-form-group">
                        <label for="icon_file" class="cats-field-label">Reemplazar icono (archivo)</label>
                        <input id="icon_file" name="icon_file" type="file" accept="image/*" class="cats-field">
                        <p class="cats-field-hint">Si subes un archivo, reemplaza el valor del campo icono.</p>
                        @error('icon_file')
                            <p class="cats-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="cats-form-group">
                        <label for="description" class="cats-field-label">Descripción</label>
                        <textarea id="description" name="description" class="cats-field" rows="4">{{ old('description', $category->description) }}</textarea>
                        @error('description')
                            <p class="cats-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="cats-form-actions">
                        <button type="submit" class="btn-primary">Guardar cambios</button>
                        <a href="{{ route('categories.index') }}" class="btn-secondary">Cancelar</a>
                    </div>

                </div>
            </form>

            {{-- Danger Zone --}}
            @can('delete', $category)
                <div class="cats-danger-zone">
                    <div class="cats-danger-inner">
                        <div>
                            <h2 class="cats-danger-title">Zona peligrosa</h2>
                            <p class="cats-danger-text">Esta acción eliminará la categoría y todos sus registros asociados de forma permanente.</p>
                        </div>
                        <form method="POST" action="{{ route('categories.destroy', $category) }}"
                              onsubmit="return confirm('¿Seguro que deseas eliminar esta categoría? Esta acción no se puede deshacer.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="cats-danger-btn">Eliminar categoría</button>
                        </form>
                    </div>
                </div>
            @endcan

        </div>

        {{-- ===== COLUMNA DERECHA (solo desktop) ===== --}}
        <aside class="cats-edit-sidebar">

            {{-- Info card --}}
            <div class="cats-sidebar-card cats-sidebar-info">
                <div class="cats-sidebar-icon-wrap">
                    @php $resolvedIcon = old('icon', $category->icon); @endphp
                    @if (is_string($resolvedIcon) && filter_var($resolvedIcon, FILTER_VALIDATE_URL))
                        <img src="{{ $resolvedIcon }}" alt="Icono" class="cats-sidebar-icon-img">
                    @else
                        <div class="cats-sidebar-icon-placeholder">
                            <x-lucide-image width="28" height="28" stroke-width="1.5" />
                        </div>
                    @endif
                </div>
                <h3 class="cats-sidebar-name">{{ $category->name }}</h3>
                <p class="cats-sidebar-label">Categoría activa</p>
            </div>

            {{-- Métricas --}}
            <div class="cats-sidebar-card">
                <p class="cats-sidebar-section-title">Métricas de uso</p>
                <div class="cats-sidebar-metrics">
                    <div class="cats-sidebar-metric">
                        <p class="cats-sidebar-metric-val">{{ $category->tickets_count }}</p>
                        <p class="cats-sidebar-metric-label">Tickets</p>
                    </div>
                    <div class="cats-sidebar-metric-divider"></div>
                    <div class="cats-sidebar-metric">
                        <p class="cats-sidebar-metric-val">{{ $category->incident_history_count }}</p>
                        <p class="cats-sidebar-metric-label">Incidencias</p>
                    </div>
                </div>
            </div>

            {{-- Guía --}}
            <div class="cats-sidebar-card cats-sidebar-guide">
                <p class="cats-sidebar-section-title">Guía de campos</p>
                <ul class="cats-sidebar-guide-list">
                    <li>
                        <span class="cats-sidebar-guide-dot cats-sidebar-guide-dot--blue"></span>
                        <div>
                            <p class="cats-sidebar-guide-item-title">Nombre</p>
                            <p class="cats-sidebar-guide-item-text">Identifica la categoría en tickets y reportes.</p>
                        </div>
                    </li>
                    <li>
                        <span class="cats-sidebar-guide-dot cats-sidebar-guide-dot--blue"></span>
                        <div>
                            <p class="cats-sidebar-guide-item-title">Icono</p>
                            <p class="cats-sidebar-guide-item-text">URL de imagen o texto libre. El archivo sube un icono nuevo.</p>
                        </div>
                    </li>
                    <li>
                        <span class="cats-sidebar-guide-dot cats-sidebar-guide-dot--blue"></span>
                        <div>
                            <p class="cats-sidebar-guide-item-title">Descripción</p>
                            <p class="cats-sidebar-guide-item-text">Explica qué tipo de incidencias agrupa esta categoría.</p>
                        </div>
                    </li>
                </ul>
            </div>

            {{-- Aviso danger --}}
            @can('delete', $category)
                <div class="cats-sidebar-card cats-sidebar-danger-hint">
                    <p class="cats-sidebar-section-title cats-sidebar-section-title--red">Zona peligrosa</p>
                    <p class="cats-sidebar-guide-item-text" style="margin-top:.4rem;">Eliminar esta categoría afectará <strong>{{ $category->tickets_count }} ticket(s)</strong> y <strong>{{ $category->incident_history_count }} incidencia(s)</strong> asociadas.</p>
                </div>
            @endcan

        </aside>

    </div>
@endsection