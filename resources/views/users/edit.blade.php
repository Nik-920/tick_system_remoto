@extends('layouts.app')

@section('title', 'Editar usuario')

@section('content')
    <div class="users-edit-layout">

        {{-- COLUMNA IZQUIERDA --}}
        <div class="users-edit-left">

            <header class="users-form-header">
                <div>
                    <h1 class="users-form-title">Editar usuario</h1>
                    <p class="users-form-subtitle">Mantenimiento de <strong>{{ $managedUser->name }}</strong></p>
                </div>
                <a href="{{ route('users.index') }}" class="btn-secondary">← Volver</a>
            </header>

            @if (session('status'))
                <div class="alert-success">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert-error">
                    <p class="font-semibold mb-2">Corrige los siguientes errores:</p>
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)<li class="text-sm">{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- AVATAR --}}
            <form method="POST" action="{{ route('users.update-avatar', $managedUser) }}"
                  enctype="multipart/form-data" class="users-form-card">
                @csrf
                <div class="users-form-card-header users-form-card-header--teal">
                    <div class="users-form-card-icon users-form-card-icon--teal">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    </div>
                    <div>
                        <p class="users-form-card-title">Avatar</p>
                        <p class="users-form-card-subtitle">Imagen de perfil del usuario</p>
                    </div>
                </div>
                <div class="users-form-body">
                    <div class="users-avatar-row">
                        <div class="users-avatar-preview">
                            @if (is_string($managedUser->avatar_url) && trim($managedUser->avatar_url) !== '')
                                <img src="{{ $managedUser->avatar_url }}" alt="Avatar" class="users-avatar-img">
                            @else
                                <div class="users-avatar-placeholder">
                                    {{ strtoupper(substr($managedUser->name, 0, 1)) }}
                                </div>
                            @endif
                        </div>
                        <div class="users-avatar-upload">
                            <label for="avatar_file" class="users-field-label">Nuevo avatar</label>
                            <input id="avatar_file" name="avatar_file" type="file" accept="image/*" required class="users-field">
                            <p class="users-field-hint">Imagen hasta 2 MB.</p>
                            @error('avatar_file')<p class="users-field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="users-form-actions">
                        <button type="submit" class="btn-primary">Actualizar avatar</button>
                    </div>
                </div>
            </form>

            {{-- DATOS BÁSICOS --}}
            <form method="POST" action="{{ route('users.update', $managedUser) }}" class="users-form-card">
                @csrf @method('PATCH')
                <div class="users-form-card-header">
                    <div class="users-form-card-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </div>
                    <div>
                        <p class="users-form-card-title">Datos básicos</p>
                        <p class="users-form-card-subtitle">Identidad y datos de contacto</p>
                    </div>
                </div>
                <div class="users-form-body">
                    <div class="users-form-grid">
                        <div class="users-form-group">
                            <label for="name" class="users-field-label">Nombre *</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $managedUser->name) }}" required class="users-field">
                            @error('name')<p class="users-field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="users-form-group">
                            <label for="last_name" class="users-field-label">Apellido *</label>
                            <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $managedUser->last_name) }}" required class="users-field">
                            @error('last_name')<p class="users-field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="users-form-group">
                        <label for="email" class="users-field-label">Email *</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $managedUser->email) }}" required class="users-field">
                        @error('email')<p class="users-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="users-form-group">
                        <label for="phone" class="users-field-label">Teléfono (opcional)</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $managedUser->phone) }}" maxlength="30" placeholder="+51 999 888 777" class="users-field">
                        @error('phone')<p class="users-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="users-form-actions">
                        <button type="submit" class="btn-primary">Guardar datos</button>
                    </div>
                </div>
            </form>

            {{-- ROL --}}
            <form method="POST" action="{{ route('users.update-role', $managedUser) }}" class="users-form-card">
                @csrf @method('PATCH')
                <div class="users-form-card-header users-form-card-header--amber">
                    <div class="users-form-card-icon users-form-card-icon--amber">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <p class="users-form-card-title">Rol del usuario</p>
                        <p class="users-form-card-subtitle">Rol actual: <span class="users-role-badge users-role-badge--{{ $currentRole }}">{{ $currentRole }}</span></p>
                    </div>
                </div>
                <div class="users-form-body">
                    <div class="users-form-group">
                        <label for="role" class="users-field-label">Nuevo rol *</label>
                        <select id="role" name="role" required class="users-field">
                            @foreach ($availableRoles as $role)
                                <option value="{{ $role }}" @selected(old('role', $currentRole) === $role)>{{ str_replace('_', ' ', $role) }}</option>
                            @endforeach
                        </select>
                        <p class="users-field-hint">El cambio aplica de inmediato sobre permisos web y API.</p>
                        @error('role')<p class="users-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="users-form-actions">
                        <button type="submit" class="btn-primary">Actualizar rol</button>
                    </div>
                </div>
            </form>

            {{-- DANGER --}}
            @if (auth()->id() !== $managedUser->id)
                <div class="users-danger-zone">
                    <div class="users-danger-inner">
                        <div>
                            <h2 class="users-danger-title">Zona peligrosa</h2>
                            <p class="users-danger-text">Eliminar usuario de forma permanente. Esta acción no se puede deshacer.</p>
                        </div>
                        <form method="POST" action="{{ route('users.destroy', $managedUser) }}"
                              onsubmit="return confirm('¿Eliminar usuario? Esta acción no se puede deshacer.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="users-danger-btn">Eliminar usuario</button>
                        </form>
                    </div>
                </div>
            @endif

        </div>

        {{-- SIDEBAR --}}
        <aside class="users-edit-sidebar">

            <div class="users-sidebar-card users-sidebar-profile">
                <div class="users-sidebar-avatar-lg">
                    @if (is_string($managedUser->avatar_url) && trim($managedUser->avatar_url) !== '')
                        <img src="{{ $managedUser->avatar_url }}" alt="{{ $managedUser->name }}" class="users-sidebar-avatar-img">
                    @else
                        {{ strtoupper(substr($managedUser->name, 0, 1)) }}
                    @endif
                </div>
                <h3 class="users-sidebar-title">{{ $managedUser->name }}</h3>
                <p class="users-sidebar-subtitle">{{ $managedUser->email }}</p>
                <span class="users-role-badge users-role-badge--{{ $currentRole }}">{{ str_replace('_', ' ', $currentRole) }}</span>
            </div>

            <div class="users-sidebar-card">
                <p class="users-sidebar-section-title">Información de cuenta</p>
                <ul class="users-sidebar-info-list">
                    <li>
                        <span class="users-sidebar-info-label">Registrado</span>
                        <span class="users-sidebar-info-val">{{ optional($managedUser->created_at)->format('d/m/Y') }}</span>
                    </li>
                    <li>
                        <span class="users-sidebar-info-label">Teléfono</span>
                        <span class="users-sidebar-info-val">{{ $managedUser->phone ?? '—' }}</span>
                    </li>
                    <li>
                        <span class="users-sidebar-info-label">Rol activo</span>
                        <span class="users-sidebar-info-val">{{ $currentRole }}</span>
                    </li>
                </ul>
            </div>

            @if (auth()->id() !== $managedUser->id)
                <div class="users-sidebar-card users-sidebar-danger-hint">
                    <p class="users-sidebar-section-title users-sidebar-section-title--red">Zona peligrosa</p>
                    <p class="users-sidebar-item-text" style="margin-top:.4rem;">Eliminar este usuario revocará su acceso inmediatamente.</p>
                </div>
            @else
                <div class="users-sidebar-card users-sidebar-self">
                    <p class="users-sidebar-section-title users-sidebar-section-title--blue">Tu cuenta</p>
                    <p class="users-sidebar-item-text" style="margin-top:.4rem;">Estás editando tu propio perfil. No puedes eliminarte a ti mismo.</p>
                </div>
            @endif

        </aside>
    </div>
@endsection