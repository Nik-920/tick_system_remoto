@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
    <div class="profile-layout">

        {{-- ===== COLUMNA IZQUIERDA ===== --}}
        <div class="profile-left">

            <header class="profile-header">
                <div>
                    <h1 class="profile-title">Mi perfil</h1>
                    <p class="profile-subtitle">Gestiona tus datos personales e imagen de perfil.</p>
                </div>
                <a href="{{ route('dashboard.index') }}" class="btn-secondary">← Dashboard</a>
            </header>

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

            {{-- AVATAR --}}
            <form method="POST" action="{{ route('profile.update-avatar') }}"
                  enctype="multipart/form-data" class="profile-card">
                @csrf

                <div class="profile-card-header profile-card-header--teal">
                    <div class="profile-card-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                    <div>
                        <p class="profile-card-title">Foto de perfil</p>
                        <p class="profile-card-subtitle">Sube y reemplaza tu imagen de perfil</p>
                    </div>
                </div>

                <div class="profile-card-body">
                    <div class="profile-avatar-zone">
                        {{-- Avatar actual grande --}}
                        <div class="profile-avatar-current">
                            @if (is_string($profileUser->avatar_url) && trim($profileUser->avatar_url) !== '')
                                <img src="{{ $profileUser->avatar_url }}" alt="Avatar" class="profile-avatar-img" id="avatarPreview">
                            @else
                                <div class="profile-avatar-initials" id="avatarPreview">
                                    {{ strtoupper(substr($profileUser->name, 0, 1)) }}{{ strtoupper(substr(explode(' ', $profileUser->name)[1] ?? '', 0, 1)) }}
                                </div>
                            @endif
                            <div class="profile-avatar-badge">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                    <circle cx="12" cy="13" r="4"/>
                                </svg>
                            </div>
                        </div>

                        {{-- Upload --}}
                        <div class="profile-avatar-upload">
                            <label for="avatar_file" class="profile-upload-label">
                                <div class="profile-upload-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/>
                                        <line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                </div>
                                <p class="profile-upload-text">Arrastra o haz clic para subir</p>
                                <p class="profile-upload-hint">PNG, JPG, WEBP — máx. 2 MB</p>
                                <input id="avatar_file" name="avatar_file" type="file" accept="image/*"
                                       required class="profile-upload-input" onchange="previewAvatar(this)">
                            </label>
                        </div>
                    </div>

                    <div class="profile-form-actions">
                        <button type="submit" class="btn-primary">Actualizar foto</button>
                    </div>
                </div>
            </form>

            {{-- DATOS PERSONALES --}}
            <form method="POST" action="{{ route('profile.update') }}" class="profile-card">
                @csrf
                @method('PATCH')

                <div class="profile-card-header">
                    <div class="profile-card-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="profile-card-title">Datos personales</p>
                        <p class="profile-card-subtitle">Actualiza tu información de acceso y contacto</p>
                    </div>
                </div>

                <div class="profile-card-body">
                    <div class="profile-form-grid">
                        <div class="profile-form-group">
                            <label for="name" class="profile-field-label">Nombre *</label>
                            <input id="name" name="name" type="text"
                                   value="{{ old('name', $profileUser->name) }}" required
                                   class="profile-field">
                            @error('name')<p class="profile-field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="profile-form-group">
                            <label for="last_name" class="profile-field-label">Apellido *</label>
                            <input id="last_name" name="last_name" type="text"
                                   value="{{ old('last_name', $profileUser->last_name) }}" required
                                   class="profile-field">
                            @error('last_name')<p class="profile-field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="profile-form-group">
                        <label for="email" class="profile-field-label">Email *</label>
                        <input id="email" name="email" type="email"
                               value="{{ old('email', $profileUser->email) }}" required
                               class="profile-field">
                        @error('email')<p class="profile-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="profile-form-group">
                        <label for="phone" class="profile-field-label">Teléfono</label>
                        <input id="phone" name="phone" type="text"
                               value="{{ old('phone', $profileUser->phone) }}" maxlength="30"
                               placeholder="+51 999 888 777"
                               class="profile-field">
                        @error('phone')<p class="profile-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="profile-form-actions">
                        <button type="submit" class="btn-primary">Guardar perfil</button>
                        <a href="{{ route('dashboard.index') }}" class="btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>

        </div>

        {{-- ===== SIDEBAR ===== --}}
        <aside class="profile-sidebar">

            {{-- Tarjeta de perfil visual --}}
            <div class="profile-sidebar-card profile-sidebar-identity">
                <div class="profile-identity-avatar">
                    @if (is_string($profileUser->avatar_url) && trim($profileUser->avatar_url) !== '')
                        <img src="{{ $profileUser->avatar_url }}" alt="{{ $profileUser->name }}" class="profile-identity-img">
                    @else
                        <span class="profile-identity-initials">
                            {{ strtoupper(substr($profileUser->name, 0, 1)) }}{{ strtoupper(substr(explode(' ', $profileUser->name)[1] ?? '', 0, 1)) }}
                        </span>
                    @endif
                </div>
                <h3 class="profile-identity-name">{{ $profileUser->name }}</h3>
                <p class="profile-identity-email">{{ $profileUser->email }}</p>
                @php $role = $profileUser->roles->pluck('name')->first() ?? 'reporter'; @endphp
                <span class="users-role-badge users-role-badge--{{ $role }}">{{ str_replace('_', ' ', $role) }}</span>
            </div>

            {{-- Info de cuenta --}}
            <div class="profile-sidebar-card">
                <p class="profile-sidebar-section-title">Información de cuenta</p>
                <ul class="profile-sidebar-info-list">
                    <li>
                        <span class="profile-sidebar-info-label">Miembro desde</span>
                        <span class="profile-sidebar-info-val">{{ optional($profileUser->created_at)->format('d/m/Y') }}</span>
                    </li>
                    <li>
                        <span class="profile-sidebar-info-label">Teléfono</span>
                        <span class="profile-sidebar-info-val">{{ $profileUser->phone ?? '—' }}</span>
                    </li>
                    <li>
                        <span class="profile-sidebar-info-label">Rol</span>
                        <span class="profile-sidebar-info-val">{{ $role }}</span>
                    </li>
                </ul>
            </div>

            {{-- Tips --}}
            <div class="profile-sidebar-card profile-sidebar-tips">
                <p class="profile-sidebar-section-title">Consejos de perfil</p>
                <ul class="profile-sidebar-guide">
                    <li>
                        <span class="profile-sidebar-dot profile-sidebar-dot--blue"></span>
                        <div>
                            <p class="profile-sidebar-item-title">Foto clara</p>
                            <p class="profile-sidebar-item-text">Una foto de rostro facilita la identificación en el sistema.</p>
                        </div>
                    </li>
                    <li>
                        <span class="profile-sidebar-dot profile-sidebar-dot--green"></span>
                        <div>
                            <p class="profile-sidebar-item-title">Email institucional</p>
                            <p class="profile-sidebar-item-text">Mantén tu correo actualizado para recibir notificaciones.</p>
                        </div>
                    </li>
                    <li>
                        <span class="profile-sidebar-dot profile-sidebar-dot--amber"></span>
                        <div>
                            <p class="profile-sidebar-item-title">Teléfono de contacto</p>
                            <p class="profile-sidebar-item-text">Útil para coordinación en incidencias urgentes.</p>
                        </div>
                    </li>
                </ul>
            </div>

        </aside>
    </div>

<script>
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('avatarPreview');
        if (!preview) return;
        // Si es img la reemplazamos src, si es div la convertimos en img
        if (preview.tagName === 'IMG') {
            preview.src = e.target.result;
        } else {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'profile-avatar-img';
            img.id = 'avatarPreview';
            preview.replaceWith(img);
        }
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
@endsection