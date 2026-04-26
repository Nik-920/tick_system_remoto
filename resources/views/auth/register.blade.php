@extends('layouts.auth')

@section('title', 'Registro')

@section('content')
<div class="auth-card">

    <header class="auth-card-header">
        <div class="auth-card-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="8.5" cy="7" r="4"/>
                <line x1="20" y1="8" x2="20" y2="14"/>
                <line x1="23" y1="11" x2="17" y2="11"/>
            </svg>
        </div>
        <div>
            <h1 class="auth-card-title">Crear cuenta</h1>
            <p class="auth-card-subtitle">Tu usuario se registrará con el rol <strong>reporter</strong> por defecto.</p>
        </div>
    </header>

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

    <form method="POST" action="{{ route('register.store') }}" class="auth-form" novalidate>
        @csrf

        <div class="auth-field">
            <label for="name" class="auth-label">Nombre completo</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </span>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Tu nombre completo" class="auth-input {{ $errors->has('name') ? 'auth-input--error' : '' }}">
            </div>
            @error('name')<p class="auth-field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="email" class="auth-label">Correo electrónico</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                    </svg>
                </span>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="usuario@empresa.com" class="auth-input {{ $errors->has('email') ? 'auth-input--error' : '' }}">
            </div>
            @error('email')<p class="auth-field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="password" class="auth-label">Contraseña</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </span>
                <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Mínimo 8 caracteres" class="auth-input {{ $errors->has('password') ? 'auth-input--error' : '' }}">
            </div>
            @error('password')<p class="auth-field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="password_confirmation" class="auth-label">Confirmar contraseña</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </span>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Repite la contraseña" class="auth-input">
            </div>
        </div>

        <button type="submit" class="auth-submit">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
            </svg>
            Crear mi cuenta
        </button>
    </form>

    <div class="auth-card-footer">
        <p class="auth-card-footer-text">
            ¿Ya tienes cuenta?
            <a href="{{ route('login') }}" class="auth-card-footer-link">Iniciar sesión</a>
        </p>
    </div>

</div>
@endsection