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
                    @include('categories.partials.form', [
                        'category'    => $category,
                        'submitLabel' => 'Guardar cambios',
                    ])
                </div>
            </form>

            {{-- Danger Zone --}}
            @can('delete', $category)
                @php
                    $hasRelations = ((int) $category->tickets_count) > 0 || ((int) $category->incident_history_count) > 0;
                @endphp
                <div class="cats-danger-zone">
                    <div class="cats-danger-inner">
                        <div>
                            <h2 class="cats-danger-title">Zona peligrosa</h2>
                            @if ($hasRelations)
                                <p class="cats-danger-text">No se puede eliminar esta categoría porque tiene
                                    <strong>{{ $category->tickets_count }} ticket(s)</strong> y
                                    <strong>{{ $category->incident_history_count }} incidencia(s)</strong> asociadas.
                                    Reasigna o cierra esos registros antes de eliminarla.</p>
                            @else
                                <p class="cats-danger-text">Esta categoría no tiene registros asociados y puede eliminarse de forma permanente. Esta acción no se puede deshacer.</p>
                            @endif
                        </div>
                        @if ($hasRelations)
                            <button type="button" class="cats-danger-btn" disabled aria-disabled="true"
                                    title="Elimina o reasigna los tickets e incidencias asociadas para poder borrar la categoría.">
                                Eliminar categoría
                            </button>
                        @else
                            <form method="POST" action="{{ route('categories.destroy', $category) }}"
                                  onsubmit="return confirm('¿Seguro que deseas eliminar la categoría «{{ $category->name }}»? Esta acción no se puede deshacer.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="cats-danger-btn">Eliminar categoría</button>
                            </form>
                        @endif
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
                    <p class="cats-sidebar-guide-item-text" style="margin-top:.4rem;">Esta categoría tiene <strong>{{ $category->tickets_count }} ticket(s)</strong> y <strong>{{ $category->incident_history_count }} incidencia(s)</strong>. No podrá eliminarse mientras existan registros asociados.</p>
                </div>
            @endcan

        </aside>

    </div>
@endsection