@extends('layouts.auth')

@section('title', 'Restablecer contraseña')

@section('content')
<div class="auth-card">

    <header class="auth-card-header">
        <div class="auth-card-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
            </svg>
        </div>
        <div>
            <h1 class="auth-card-title">Restablecer contraseña</h1>
            <p class="auth-card-subtitle">Define una nueva contraseña segura para tu cuenta.</p>
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

    <form method="POST" action="{{ route('password.update') }}" class="auth-form" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="auth-field">
            <label for="email" class="auth-label">Correo electrónico</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                    </svg>
                </span>
                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="email" placeholder="usuario@empresa.com" class="auth-input {{ $errors->has('email') ? 'auth-input--error' : '' }}">
            </div>
            @error('email')<p class="auth-field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="password" class="auth-label">Nueva contraseña</label>
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
            <label for="password_confirmation" class="auth-label">Confirmar nueva contraseña</label>
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
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            Restablecer contraseña
        </button>
    </form>

    <div class="auth-card-footer">
        <p class="auth-card-footer-text">
            ¿Recordaste tu contraseña?
            <a href="{{ route('login') }}" class="auth-card-footer-link">Volver a iniciar sesión</a>
        </p>
    </div>

</div>
@endsection