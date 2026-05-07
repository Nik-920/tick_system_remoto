@extends('layouts.app')

@section('title', 'Nuevo Ticket')

@section('content')
    <div class="tickets-create-page">

        {{-- ===== HEADER ===== --}}
        <header class="tickets-create-header">
            <div>
                <h1 class="tickets-create-title">Crear Ticket</h1>
                <p class="tickets-create-subtitle">Registra una nueva incidencia para su seguimiento operativo.</p>
            </div>
            <a href="{{ route('tickets.index') }}" class="btn-secondary">Volver al listado</a>
        </header>

        {{-- ===== ALERTS ===== --}}
        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert-error">
                <p class="font-semibold mb-2">Por favor corrige los siguientes errores:</p>
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li class="text-sm">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ===== FORM ===== --}}
        <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="tickets-create-form">
            @csrf

            {{-- Título --}}
            <div class="tickets-form-group">
                <label for="title" class="tickets-field-label">Título *</label>
                <input id="title" type="text" name="title" value="{{ old('title') }}" required 
                       placeholder="Ej: Proyector sala A-201 no enciende"
                       class="tickets-field">
                @error('title')
                    <p class="tickets-field-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Descripción --}}
            <div class="tickets-form-group">
                <label for="description" class="tickets-field-label">Descripción *</label>
                <textarea id="description" name="description" rows="5" required 
                          placeholder="Describe la incidencia con detalle. Incluye contexto, pasos para reproducir y comportamiento esperado."
                          class="tickets-field">{{ old('description') }}</textarea>
                @error('description')
                    <p class="tickets-field-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Grid: Ubicación + Categoría --}}
            <div class="tickets-form-grid">
                <div class="tickets-form-group">
                    <label for="location_id" class="tickets-field-label">Ubicación *</label>
                    <select id="location_id" name="location_id" required class="tickets-field">
                        <option value="">Selecciona una ubicación</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location_id', $selectedLocationId) === $location->id)>
                                {{ $location->name }} ({{ $location->room_code }})
                            </option>
                        @endforeach
                    </select>
                    @error('location_id')
                        <p class="tickets-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="tickets-form-group">
                    <label for="category_id" class="tickets-field-label">Categoría *</label>
                    <select id="category_id" name="category_id" required class="tickets-field">
                        <option value="">Selecciona una categoría</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') === $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <p class="tickets-field-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Prioridad --}}
            <div class="tickets-form-group">
                <label for="priority" class="tickets-field-label">Prioridad</label>
                <select id="priority" name="priority" class="tickets-field">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>
                            {{ ucfirst($priority) }}
                        </option>
                    @endforeach
                </select>
                @error('priority')
                    <p class="tickets-field-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Adjuntos --}}
            <div class="tickets-form-group">
                <label for="media_files" class="tickets-field-label">Adjuntos (opcional)</label>
                <input id="media_files" type="file" name="media_files[]" multiple 
                       accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.mp4,.avi,.mov"
                       class="tickets-field">
                <p class="tickets-field-hint">Máximo 5 archivos de 10 MB cada uno. Formatos: imágenes, PDF, documentos, video.</p>
                @error('media_files')
                    <p class="tickets-field-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Botones --}}
            <div class="tickets-form-actions">
                <button type="submit" class="btn-primary">Guardar ticket</button>
                <a href="{{ route('tickets.index') }}" class="btn-secondary">Cancelar</a>
            </div>
        </form>

    </div>
@endsection