@extends('layouts.auth')

@section('title', 'Registro')

@section('content')
<div class="auth-card">

    <header class="auth-card-header">
        <div class="auth-card-icon" aria-hidden="true">
            <x-lucide-user-plus width="20" height="20" stroke-width="2" />
        </div>
        <div>
            <h1 class="auth-card-title">Crear cuenta</h1>
            <p class="auth-card-subtitle">Tu usuario se registrará con el rol <strong>reporter</strong> por defecto.</p>
        </div>
    </header>

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

    <form method="POST" action="{{ route('register.store') }}" class="auth-form" novalidate>
        @csrf

        <div class="auth-field">
            <label for="name" class="auth-label">Nombre completo</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <x-lucide-user width="16" height="16" stroke-width="2" />
                </span>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Tu nombre completo" class="auth-input {{ $errors->has('name') ? 'auth-input--error' : '' }}">
            </div>
            @error('name')<p class="auth-field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="email" class="auth-label">Correo electrónico</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <x-lucide-mail width="16" height="16" stroke-width="2" />
                </span>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="usuario@empresa.com" class="auth-input {{ $errors->has('email') ? 'auth-input--error' : '' }}">
            </div>
            @error('email')<p class="auth-field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="password" class="auth-label">Contraseña</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <x-lucide-lock width="16" height="16" stroke-width="2" />
                </span>
                <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Mínimo 8 caracteres" class="auth-input {{ $errors->has('password') ? 'auth-input--error' : '' }}">
            </div>
            @error('password')<p class="auth-field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="password_confirmation" class="auth-label">Confirmar contraseña</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <x-lucide-check-circle width="16" height="16" stroke-width="2" />
                </span>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Repite la contraseña" class="auth-input">
            </div>
        </div>

        <button type="submit" class="auth-submit">
            <x-lucide-user-plus width="17" height="17" stroke-width="2.2" aria-hidden="true" />
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