{{--
    categories/partials/form.blade.php
    Variables:
        $category     — Category|null  (null en create, modelo en edit)
        $submitLabel  — string          (texto del botón submit)
--}}

<div class="cats-form-group">
    <label for="name" class="cats-field-label">Nombre *</label>
    <input id="name" name="name" type="text"
           value="{{ old('name', $category->name ?? '') }}" required
           placeholder="Ej: Infraestructura, Electricidad, Plomería"
           class="cats-field">
    @error('name')
        <p class="cats-field-error">{{ $message }}</p>
    @enderror
</div>

<div class="cats-form-group">
    <label for="icon" class="cats-field-label">
        {{ $category ? 'Icono (texto o URL)' : 'Icono (opcional)' }}
    </label>
    <input id="icon" name="icon" type="text"
           value="{{ old('icon', $category->icon ?? '') }}"
           placeholder="wrench, alert, tools o URL de imagen"
           class="cats-field">
    @if (! $category)
        <p class="cats-field-hint">Texto libre o URL de imagen. Si subes un archivo abajo, este campo se ignora.</p>
    @endif
    @error('icon')
        <p class="cats-field-error">{{ $message }}</p>
    @enderror
</div>

@php $resolvedIcon = old('icon', $category->icon ?? null); @endphp
@if ($category && is_string($resolvedIcon) && filter_var($resolvedIcon, FILTER_VALIDATE_URL))
    <div class="cats-form-group">
        <p class="cats-field-label">Vista previa del icono actual</p>
        <img src="{{ $resolvedIcon }}" alt="Icono actual" class="cats-icon-preview-img">
    </div>
@endif

<div class="cats-form-group">
    <label for="icon_file" class="cats-field-label">
        {{ $category ? 'Reemplazar icono (archivo)' : 'Archivo de icono (opcional)' }}
    </label>
    <input id="icon_file" name="icon_file" type="file" accept="image/*" class="cats-field">
    <p class="cats-field-hint">Si subes un archivo, reemplaza el valor del campo icono.</p>
    @error('icon_file')
        <p class="cats-field-error">{{ $message }}</p>
    @enderror
</div>

<div class="cats-form-group">
    <label for="description" class="cats-field-label">Descripción</label>
    <textarea id="description" name="description" class="cats-field" rows="4"
              placeholder="Describe brevemente qué tipo de incidencias agrupa esta categoría">{{ old('description', $category->description ?? '') }}</textarea>
    @error('description')
        <p class="cats-field-error">{{ $message }}</p>
    @enderror
</div>

<div class="cats-form-actions">
    <button type="submit" class="btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('categories.index') }}" class="btn-secondary">Cancelar</a>
</div>
