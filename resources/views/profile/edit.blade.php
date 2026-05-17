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
                        <x-lucide-image width="18" height="18" stroke-width="2" />
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
                            @php
                                $userName = $profileUser?->name ?? 'Usuario';
                                $nameParts = explode(' ', $userName);
                                $initials = strtoupper(substr($userName, 0, 1)) . strtoupper(substr($nameParts[1] ?? '', 0, 1));
                                $hasAvatar = is_string($profileUser?->avatar_url) && trim($profileUser?->avatar_url ?? '') !== '';
                            @endphp
                            
                            @if ($hasAvatar)
                                <img src="{{ $profileUser?->avatar_url }}" alt="Avatar" class="profile-avatar-img" id="avatarPreview">
                            @else
                                <div class="profile-avatar-initials" id="avatarPreview">
                                    {{ $initials }}
                                </div>
                            @endif
                            <div class="profile-avatar-badge">
                                <x-lucide-camera width="12" height="12" stroke-width="2.5" />
                            </div>
                        </div>

                        {{-- Upload --}}
                        <div class="profile-avatar-upload">
                            <label for="avatar_file" class="profile-upload-label">
                                <div class="profile-upload-icon">
                                    <x-lucide-upload width="22" height="22" stroke-width="1.5" />
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
                        @if (is_string($profileUser?->avatar_url) && trim($profileUser?->avatar_url ?? '') !== '')
                            <button type="button" class="btn-danger" onclick="openDeleteAvatarModal()">
                                Eliminar foto
                            </button>
                        @endif
                    </div>
                </div>
            </form>

            @if (is_string($profileUser?->avatar_url) && trim($profileUser?->avatar_url ?? '') !== '')
                <form id="delete-avatar-form" action="{{ route('profile.delete-avatar') }}" method="POST" style="display: none;">
                    @csrf
                    @method('DELETE')
                </form>
            @endif

            {{-- DATOS PERSONALES --}}
            <form method="POST" action="{{ route('profile.update') }}" class="profile-card">
                @csrf
                @method('PATCH')

                <div class="profile-card-header">
                    <div class="profile-card-icon">
                        <x-lucide-edit width="18" height="18" stroke-width="2" />
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
                                   value="{{ old('name', $profileUser?->name) }}" required
                                   class="profile-field">
                            @error('name')<p class="profile-field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="profile-form-group">
                            <label for="last_name" class="profile-field-label">Apellido *</label>
                            <input id="last_name" name="last_name" type="text"
                                   value="{{ old('last_name', $profileUser?->last_name) }}" required
                                   class="profile-field">
                            @error('last_name')<p class="profile-field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="profile-form-group">
                        <label for="email" class="profile-field-label">Email *</label>
                        <input id="email" name="email" type="email"
                               value="{{ old('email', $profileUser?->email) }}" required
                               class="profile-field">
                        @error('email')<p class="profile-field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="profile-form-group">
                        <label for="phone" class="profile-field-label">Teléfono</label>
                        <input id="phone" name="phone" type="text"
                               value="{{ old('phone', $profileUser?->phone) }}" maxlength="30"
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
                    @if ($hasAvatar)
                        <img src="{{ $profileUser->avatar_url }}" alt="{{ $profileUser?->name ?? 'Avatar' }}" class="profile-identity-img">
                    @else
                        <span class="profile-identity-initials">
                            {{ $initials ?? 'US' }}
                        </span>
                    @endif
                </div>
                <h3 class="profile-identity-name">{{ $profileUser?->name ?? 'Usuario' }}</h3>
                <p class="profile-identity-email">{{ $profileUser?->email ?? 'Sin correo' }}</p>
                @php $role = optional($profileUser?->roles)->pluck('name')->first() ?? 'reporter'; @endphp
                <span class="users-role-badge users-role-badge--{{ $role }}">{{ str_replace('_', ' ', $role) }}</span>
            </div>

            {{-- Info de cuenta --}}
            <div class="profile-sidebar-card">
                <p class="profile-sidebar-section-title">Información de cuenta</p>
                <ul class="profile-sidebar-info-list">
                    <li>
                        <span class="profile-sidebar-info-label">Miembro desde</span>
                        <span class="profile-sidebar-info-val">{{ $profileUser?->created_at?->format('d/m/Y') ?? '—' }}</span>
                    </li>
                    <li>
                        <span class="profile-sidebar-info-label">Teléfono</span>
                        <span class="profile-sidebar-info-val">{{ $profileUser?->phone ?? '—' }}</span>
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

// Lógica para el modal premium
function openDeleteAvatarModal() {
    const modal = document.getElementById('deleteAvatarModal');
    const modalContent = modal.querySelector('.relative');
    
    // Mostrar contenedor
    modal.classList.remove('hidden');
    
    // Forzar reflow para aplicar la transición
    void modal.offsetWidth;
    
    // Animar entrada
    modal.classList.remove('opacity-0');
    modal.classList.add('opacity-100');
    modalContent.classList.remove('scale-95', 'translate-y-4');
    modalContent.classList.add('scale-100', 'translate-y-0');
}

function closeDeleteAvatarModal() {
    const modal = document.getElementById('deleteAvatarModal');
    const modalContent = modal.querySelector('.relative');
    
    // Animar salida
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    modalContent.classList.remove('scale-100', 'translate-y-0');
    modalContent.classList.add('scale-95', 'translate-y-4');
    
    // Ocultar completamente después de la transición
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}
</script>

{{-- Custom Delete Modal Overlay --}}
<div id="deleteAvatarModal" class="fixed inset-0 z-50 flex items-center justify-center hidden opacity-0 transition-opacity duration-300" style="backdrop-filter: blur(5px);">
    <!-- Backdrop oscuro -->
    <button type="button" class="absolute inset-0 w-full h-full border-0 p-0 m-0 cursor-default" style="background-color: rgba(15, 23, 42, 0.55);" onclick="closeDeleteAvatarModal()" aria-label="Cerrar modal" tabindex="-1"></button>

    <!-- Contenido del Modal -->
    <div class="relative w-full max-w-sm rounded-2xl p-6 transform scale-95 translate-y-4 transition-all duration-300 shadow-2xl" style="background-color: var(--bg-surface); border: 1px solid var(--border-default);">
        
        <!-- Icono centrado -->
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full mb-4" style="background-color: rgba(225, 29, 72, 0.12);">
            <x-lucide-alert-triangle width="28" height="28" style="color: #e11d48;" stroke-width="2.5" />
        </div>
        
        <!-- Título y descripción -->
        <div class="text-center mb-6">
            <h3 class="text-lg font-bold mb-2" style="color: var(--text-primary); letter-spacing: -0.01em;">¿Eliminar foto de perfil?</h3>
            <p class="text-sm" style="color: var(--text-muted); line-height: 1.5;">Esta acción no se puede deshacer. Se removerá tu imagen y volverás a usar tus iniciales por defecto.</p>
        </div>
        
        <!-- Botones de Acción -->
        <div class="flex gap-3 justify-center mt-2">
            <button type="button" class="btn-secondary flex-1 text-center justify-center" onclick="closeDeleteAvatarModal()">
                Cancelar
            </button>
            <button type="button" class="btn-danger flex-1 text-center justify-center" onclick="document.getElementById('delete-avatar-form').submit();">
                Sí, eliminar
            </button>
        </div>
    </div>
</div>
@endsection