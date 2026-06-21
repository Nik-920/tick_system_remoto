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

{{-- ── Visibilidad en Comunidad ── --}}
<div class="cats-form-section">
    <h2 class="cats-form-section-title">Visibilidad en Comunidad</h2>
    <p class="cats-form-section-note">Estas opciones definen cómo se comportarán los tickets nuevos de esta categoría en la pestaña Comunidad. Los cambios aplican a nuevos tickets; los existentes conservan su visibilidad actual.</p>
</div>

<div class="cats-form-group">
    <div class="cats-check-row">
        <input type="hidden" name="community_default_visible" value="0">
        <input id="community_default_visible" type="checkbox" name="community_default_visible" value="1"
               class="cats-check"
               @checked((string) old('community_default_visible', ($category?->community_default_visible ?? true) ? '1' : '0') === '1')>
        <label for="community_default_visible" class="cats-check-label">Publicar tickets de esta categoría en Comunidad por defecto</label>
    </div>
    @error('community_default_visible')
        <p class="cats-field-error">{{ $message }}</p>
    @enderror
</div>

<div class="cats-form-group">
    <div class="cats-check-row">
        <input type="hidden" name="community_visibility_locked" value="0">
        <input id="community_visibility_locked" type="checkbox" name="community_visibility_locked" value="1"
               class="cats-community-locked-check cats-check"
               @checked((string) old('community_visibility_locked', ($category?->community_visibility_locked ?? false) ? '1' : '0') === '1')>
        <label for="community_visibility_locked" class="cats-check-label">Bloquear esta configuración para reporters</label>
    </div>
    <p class="cats-field-hint">Si se bloquea, el reporter no podrá cambiar la visibilidad de Comunidad al crear tickets de esta categoría.</p>
    @error('community_visibility_locked')
        <p class="cats-field-error">{{ $message }}</p>
    @enderror
</div>

<div class="cats-form-group">
    <label for="community_visibility_help" class="cats-field-label">Mensaje de ayuda para categorías sensibles (opcional)</label>
    <textarea id="community_visibility_help" name="community_visibility_help" class="cats-field" rows="2"
              placeholder="Ejemplo: Por privacidad, los reportes de esta categoría no aparecen en Comunidad.">{{ old('community_visibility_help', $category?->community_visibility_help ?? '') }}</textarea>
    <p class="cats-field-hint">Texto que verá el reporter al crear un ticket en esta categoría. Útil para categorías privadas o sensibles.</p>
    @error('community_visibility_help')
        <p class="cats-field-error">{{ $message }}</p>
    @enderror
</div>

<div class="cats-form-actions">
    <button type="submit" class="btn-primary">{{ $submitLabel }}</button>
    <a href="{{ route('categories.index') }}" class="btn-secondary">Cancelar</a>
</div>
