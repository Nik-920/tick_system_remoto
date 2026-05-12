@extends('layouts.auth')

@section('title', 'Restablecer contraseña')

@section('content')
<div class="auth-card">

    <header class="auth-card-header">
        <div class="auth-card-icon" aria-hidden="true">
            <x-lucide-key width="20" height="20" stroke-width="2" />
        </div>
        <div>
            <h1 class="auth-card-title">Restablecer contraseña</h1>
            <p class="auth-card-subtitle">Define una nueva contraseña segura para tu cuenta.</p>
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

    <form method="POST" action="{{ route('password.update') }}" class="auth-form" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="auth-field">
            <label for="email" class="auth-label">Correo electrónico</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <x-lucide-mail width="16" height="16" stroke-width="2" />
                </span>
                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="email" placeholder="usuario@empresa.com" class="auth-input {{ $errors->has('email') ? 'auth-input--error' : '' }}">
            </div>
            @error('email')<p class="auth-field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="password" class="auth-label">Nueva contraseña</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <x-lucide-lock width="16" height="16" stroke-width="2" />
                </span>
                <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Mínimo 8 caracteres" class="auth-input {{ $errors->has('password') ? 'auth-input--error' : '' }}">
            </div>
            @error('password')<p class="auth-field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <div class="auth-field">
            <label for="password_confirmation" class="auth-label">Confirmar nueva contraseña</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <x-lucide-check-circle width="16" height="16" stroke-width="2" />
                </span>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Repite la contraseña" class="auth-input">
            </div>
        </div>

        <button type="submit" class="auth-submit">
            <x-lucide-shield width="17" height="17" stroke-width="2.2" aria-hidden="true" />
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