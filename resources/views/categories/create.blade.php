@extends('layouts.app')

@section('title', 'Nueva categoría')

@section('content')
    <div class="cats-form-page">

        {{-- ===== HEADER ===== --}}
        <header class="cats-form-header">
            <div>
                <h1 class="cats-form-title">Nueva categoría</h1>
                <p class="cats-form-subtitle">Crea una categoría para clasificar tickets y analítica operativa.</p>
            </div>
            <a href="{{ route('categories.index') }}" class="btn-secondary">← Volver</a>
        </header>

        {{-- ===== ALERTS ===== --}}
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

        {{-- ===== FORM ===== --}}
        <form method="POST" action="{{ route('categories.store') }}" enctype="multipart/form-data" class="cats-form-card">
            @csrf

            <div class="cats-form-card-body">

                <div class="cats-form-group">
                    <label for="name" class="cats-field-label">Nombre *</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required
                           placeholder="Ej: Infraestructura, Electricidad, Plomería"
                           class="cats-field">
                    @error('name')
                        <p class="cats-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="cats-form-group">
                    <label for="icon" class="cats-field-label">Icono (opcional)</label>
                    <input id="icon" name="icon" type="text" value="{{ old('icon') }}"
                           placeholder="wrench, alert, tools o URL de imagen"
                           class="cats-field">
                    <p class="cats-field-hint">Texto libre o URL de imagen. Si subes un archivo abajo, este campo se ignora.</p>
                    @error('icon')
                        <p class="cats-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="cats-form-group">
                    <label for="icon_file" class="cats-field-label">Archivo de icono (opcional)</label>
                    <input id="icon_file" name="icon_file" type="file" accept="image/*" class="cats-field">
                    <p class="cats-field-hint">Si subes un archivo, reemplaza el valor del campo icono.</p>
                    @error('icon_file')
                        <p class="cats-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="cats-form-group">
                    <label for="description" class="cats-field-label">Descripción</label>
                    <textarea id="description" name="description" class="cats-field" rows="4"
                              placeholder="Describe brevemente qué tipo de incidencias agrupa esta categoría">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="cats-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="cats-form-actions">
                    <button type="submit" class="btn-primary">Guardar categoría</button>
                    <a href="{{ route('categories.index') }}" class="btn-secondary">Cancelar</a>
                </div>

            </div>
        </form>

    </div>
@endsection