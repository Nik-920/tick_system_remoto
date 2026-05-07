@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
<div class="auth-card">

    {{-- Header --}}
    <header class="auth-card-header">
        <div class="auth-card-icon" aria-hidden="true">
            <x-lucide-log-in width="20" height="20" stroke-width="2" />
        </div>
        <div>
            <h1 class="auth-card-title">Iniciar sesión</h1>
            <p class="auth-card-subtitle">Accede con tu correo institucional o cuenta del sistema.</p>
        </div>
    </header>

    {{-- Alerts --}}
    @if ($errors->any())
        <div class="auth-alert auth-alert--error" role="alert" aria-live="polite">
            <x-lucide-alert-circle width="16" height="16" stroke-width="2" aria-hidden="true" />
            <ul class="auth-alert-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('status'))
        <div class="auth-alert auth-alert--success" role="status" aria-live="polite">
            <x-lucide-check-circle width="16" height="16" stroke-width="2" aria-hidden="true" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- Form --}}
    <form method="POST" action="{{ route('login.store') }}" class="auth-form" novalidate>
        @csrf

        <div class="auth-field">
            <label for="email" class="auth-label">
                Correo electrónico
            </label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <x-lucide-mail width="16" height="16" stroke-width="2" />
                </span>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="usuario@empresa.com"
                    class="auth-input {{ $errors->has('email') ? 'auth-input--error' : '' }}"
                    aria-describedby="{{ $errors->has('email') ? 'email-error' : '' }}"
                >
            </div>
            @error('email')
                <p id="email-error" class="auth-field-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <div class="auth-label-row">
                <label for="password" class="auth-label">Contraseña</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="auth-label-link" tabindex="5">
                        ¿Olvidaste tu contraseña?
                    </a>
                @endif
            </div>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <x-lucide-lock width="16" height="16" stroke-width="2" />
                </span>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="auth-input"
                >
                <button type="button" id="toggle-password" class="auth-input-action" aria-label="Mostrar contraseña">
                    <x-lucide-eye id="eye-icon" width="16" height="16" stroke-width="2" />
                    <x-lucide-eye-off id="eye-off-icon" width="16" height="16" stroke-width="2" style="display: none;" />
                </button>
            </div>
        </div>

        <div class="auth-remember">
            <label class="auth-checkbox-label">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    @checked(old('remember'))
                    class="auth-checkbox"
                >
                <span class="auth-checkbox-text">Recordarme en este dispositivo</span>
            </label>
        </div>

        <button type="submit" class="auth-submit" id="submit-btn">
            <span class="auth-submit-text">
                <x-lucide-log-in width="17" height="17" stroke-width="2.2" aria-hidden="true" />
                Entrar al sistema
            </span>
            <span class="auth-submit-loading" style="display: none;">
                <svg class="animate-spin" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Accediendo...
            </span>
        </button>
    </form>

    {{-- Footer links --}}
    <div class="auth-card-footer">
        @if (Route::has('register'))
            <p class="auth-card-footer-text">
                ¿No tienes cuenta?
                <a href="{{ route('register') }}" class="auth-card-footer-link">Solicitar acceso</a>
            </p>
        @endif
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Password Visibility Toggle
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.getElementById('toggle-password');
    const eyeIcon = document.getElementById('eye-icon');
    const eyeOffIcon = document.getElementById('eye-off-icon');

    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            eyeIcon.style.display = isPassword ? 'none' : 'block';
            eyeOffIcon.style.display = isPassword ? 'block' : 'none';
        });
    }

    // 2. Loading State on Submit
    const loginForm = document.querySelector('.auth-form');
    const submitBtn = document.getElementById('submit-btn');
    const btnText = submitBtn?.querySelector('.auth-submit-text');
    const btnLoading = submitBtn?.querySelector('.auth-submit-loading');

    if (loginForm && submitBtn) {
        loginForm.addEventListener('submit', () => {
            submitBtn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnLoading) btnLoading.style.display = 'flex';
        });
    }

    // 3. Subtle scale in on load
    if (card) {
        card.style.opacity = '0';
        card.style.transform = 'scale(0.98) translateY(10px)';
        card.style.transition = 'all 0.6s cubic-bezier(0.22, 1, 0.36, 1)';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'scale(1) translateY(0)';
        }, 100);
    }
});
</script>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.animate-spin {
    animation: spin 1s linear infinite;
}
.auth-submit-loading {
    justify-content: center;
}
.auth-submit:disabled {
    opacity: 0.8;
    cursor: not-allowed;
}
</style>
@endpush
@endsection