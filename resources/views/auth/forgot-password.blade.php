@extends('layouts.auth')

@section('title', 'Recuperar contraseña')

@section('content')
<div class="auth-card">

    <header class="auth-card-header">
        <div class="auth-card-icon" aria-hidden="true">
            <x-lucide-unlock width="20" height="20" stroke-width="2" />
        </div>
        <div>
            <h1 class="auth-card-title">Recuperar contraseña</h1>
            <p class="auth-card-subtitle">Ingresa tu correo y te enviaremos un enlace para restablecer el acceso.</p>
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

    @if (session('status'))
        <div class="auth-alert auth-alert--success" role="status" aria-live="polite">
            <x-lucide-check-circle width="16" height="16" stroke-width="2" aria-hidden="true" />
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form" novalidate>
        @csrf

        <div class="auth-field">
            <label for="email" class="auth-label">Correo electrónico</label>
            <div class="auth-input-wrap">
                <span class="auth-input-icon" aria-hidden="true">
                    <x-lucide-mail width="16" height="16" stroke-width="2" />
                </span>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="usuario@empresa.com" class="auth-input {{ $errors->has('email') ? 'auth-input--error' : '' }}">
            </div>
            @error('email')<p class="auth-field-error" role="alert">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="auth-submit">
            <x-lucide-send width="17" height="17" stroke-width="2.2" aria-hidden="true" />
            Enviar enlace de recuperación
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