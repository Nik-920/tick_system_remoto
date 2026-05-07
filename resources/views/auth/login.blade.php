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
            <x-lucide-log-in width="17" height="17" stroke-width="2.2" aria-hidden="true" />
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