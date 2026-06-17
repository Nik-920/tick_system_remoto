{{-- users/partials/basic-fields.blade.php
    Variables:
      $user (optional) — modelo existente para old() fallback en edit
--}}
<div class="users-form-grid">
    <div class="users-form-group">
        <label for="name" class="users-field-label">Nombre *</label>
        <input id="name" name="name" type="text"
               value="{{ old('name', $user->name ?? '') }}" required
               placeholder="Ej: Juan" class="users-field">
        @error('name')<p class="users-field-error">{{ $message }}</p>@enderror
    </div>
    <div class="users-form-group">
        <label for="last_name" class="users-field-label">Apellido *</label>
        <input id="last_name" name="last_name" type="text"
               value="{{ old('last_name', $user->last_name ?? '') }}" required
               placeholder="Ej: Pérez" class="users-field">
        @error('last_name')<p class="users-field-error">{{ $message }}</p>@enderror
    </div>
</div>

<div class="users-form-group">
    <label for="email" class="users-field-label">Email *</label>
    <input id="email" name="email" type="email"
           value="{{ old('email', $user->email ?? '') }}" required
           placeholder="usuario@institución.pe" class="users-field">
    @error('email')<p class="users-field-error">{{ $message }}</p>@enderror
</div>

<div class="users-form-group">
    <label for="phone" class="users-field-label">Teléfono (opcional)</label>
    <input id="phone" name="phone" type="text"
           value="{{ old('phone', $user->phone ?? '') }}" maxlength="30"
           placeholder="+51 999 888 777" class="users-field">
    @error('phone')<p class="users-field-error">{{ $message }}</p>@enderror
</div>
