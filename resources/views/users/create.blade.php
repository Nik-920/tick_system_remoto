@extends('layouts.app')

@section('title', 'Nuevo usuario')

@section('content')
    <div class="users-form-layout">

        {{-- COLUMNA IZQUIERDA --}}
        <div class="users-form-left">

            <header class="users-form-header">
                <div>
                    <h1 class="users-form-title">Nuevo usuario</h1>
                    <p class="users-form-subtitle">Crea una cuenta y asigna su rol inicial en la plataforma.</p>
                </div>
                <a href="{{ route('users.index') }}" class="btn-secondary">← Volver</a>
            </header>

            @if ($errors->any())
                <div class="alert-error">
                    <p class="font-semibold mb-2">Corrige los siguientes errores:</p>
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)<li class="text-sm">{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data" class="users-form-card">
                @csrf
                <div class="users-form-card-header">
                    <div class="users-form-card-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <div>
                        <p class="users-form-card-title">Datos del usuario</p>
                        <p class="users-form-card-subtitle">Información de identidad y acceso</p>
                    </div>
                </div>

                <div class="users-form-body">

                    <div class="users-form-grid">
                        <div class="users-form-group">
                            <label for="name" class="users-field-label">Nombre *</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" required
                                   placeholder="Ej: Juan" class="users-field">
                            @error('name')<p class="users-field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="users-form-group">
                            <label for="last_name" class="users-field-label">Apellido *</label>
                            <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required
                                   placeholder="Ej: Pérez" class="users-field">
                            @error('last_name')<p class="users-field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="users-form-group">
                        <label for="email" class="users-field-label">Email *</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required
                               placeholder="usuario@institución.pe" class="users-field">
                        @error('email')<p class="users-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="users-form-group">
                        <label for="phone" class="users-field-label">Teléfono (opcional)</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone') }}" maxlength="30"
                               placeholder="+51 999 888 777" class="users-field">
                        @error('phone')<p class="users-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="users-form-group">
                        <label for="avatar_file" class="users-field-label">Avatar (opcional)</label>
                        <input id="avatar_file" name="avatar_file" type="file" accept="image/*" class="users-field">
                        <p class="users-field-hint">Imagen hasta 2 MB. Se mostrará en el perfil.</p>
                        @error('avatar_file')<p class="users-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="users-form-divider"></div>

                    <div class="users-form-grid">
                        <div class="users-form-group">
                            <label for="password" class="users-field-label">Contraseña *</label>
                            <input id="password" name="password" type="password" required class="users-field">
                            @error('password')<p class="users-field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="users-form-group">
                            <label for="password_confirmation" class="users-field-label">Confirmar contraseña *</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" required class="users-field">
                        </div>
                    </div>

                    <div class="users-form-group">
                        <label for="role" class="users-field-label">Rol *</label>
                        <select id="role" name="role" required class="users-field">
                            @foreach ($availableRoles as $role)
                                <option value="{{ $role }}" @selected(old('role') === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                        <p class="users-field-hint">Define los permisos de acceso del usuario en el sistema.</p>
                        @error('role')<p class="users-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="users-form-actions">
                        <button type="submit" class="btn-primary">Crear usuario</button>
                        <a href="{{ route('users.index') }}" class="btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>

        {{-- SIDEBAR --}}
        <aside class="users-form-sidebar">
            <div class="users-sidebar-card users-sidebar-hero">
                <div class="users-sidebar-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <h3 class="users-sidebar-title">Nuevo usuario</h3>
                <p class="users-sidebar-subtitle">El usuario recibirá acceso inmediato según el rol asignado.</p>
            </div>

            <div class="users-sidebar-card">
                <p class="users-sidebar-section-title">Roles disponibles</p>
                <ul class="users-sidebar-roles">
                    @foreach ($availableRoles as $role)
                        <li class="users-sidebar-role-item">
                            <span class="users-role-badge users-role-badge--{{ $role }}">{{ str_replace('_', ' ', $role) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="users-sidebar-card users-sidebar-tips">
                <p class="users-sidebar-section-title">Buenas prácticas</p>
                <ul class="users-sidebar-guide">
                    <li>
                        <span class="users-sidebar-dot"></span>
                        <div>
                            <p class="users-sidebar-item-title">Contraseña segura</p>
                            <p class="users-sidebar-item-text">Mínimo 8 caracteres con letras y números.</p>
                        </div>
                    </li>
                    <li>
                        <span class="users-sidebar-dot"></span>
                        <div>
                            <p class="users-sidebar-item-title">Email institucional</p>
                            <p class="users-sidebar-item-text">Usa el correo oficial para trazabilidad.</p>
                        </div>
                    </li>
                    <li>
                        <span class="users-sidebar-dot"></span>
                        <div>
                            <p class="users-sidebar-item-title">Rol mínimo necesario</p>
                            <p class="users-sidebar-item-text">Asigna solo los permisos que el usuario necesita.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
@endsection