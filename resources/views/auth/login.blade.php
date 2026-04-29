@extends('layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
<div class="auth-card">

    {{-- Header --}}
    <header class="auth-card-header">
        <div class="auth-card-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                <polyline points="10 17 15 12 10 7"/>
                <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
        </div>
        <div>
            <h1 class="auth-card-title">Iniciar sesión</h1>
            <p class="auth-card-subtitle">Accede con tu correo institucional o cuenta del sistema.</p>
        </div>
    </header>

    {{-- Alerts --}}
    @if ($errors->any())
        <div class="auth-alert auth-alert--error" role="alert" aria-live="polite">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <ul class="auth-alert-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('status'))
        <div class="auth-alert auth-alert--success" role="status" aria-live="polite">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
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
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                    </svg>
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
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
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

        <button type="submit" class="auth-submit">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                <polyline points="10 17 15 12 10 7"/>
                <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            Entrar al sistema
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
@endsection