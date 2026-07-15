@extends('layouts.auth')

@section('title', 'Reportar incidencia')

@section('content')
<div class="auth-card">

    {{-- Header --}}
    <header class="auth-card-header">
        <div class="auth-card-icon" aria-hidden="true">
            <x-lucide-qr-code width="20" height="20" stroke-width="2" />
        </div>
        <div>
            <h1 class="auth-card-title">Reportar incidencia</h1>
            <p class="auth-card-subtitle">
                {{ $location->getDisplayLabel() }} · Sin registro, toma menos de un minuto.
            </p>
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

    {{-- Form --}}
    <form method="POST"
          action="{{ route('public.qr.store', ['token' => $token]) }}"
          class="auth-form"
          enctype="multipart/form-data"
          novalidate>
        @csrf

        {{-- Honeypot anti-bots: oculto para humanos, los bots lo llenan. --}}
        <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
            <label for="website">No llenar este campo</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="auth-field">
            <label for="category_id" class="auth-label">¿Qué tipo de problema es? *</label>
            <select id="category_id" name="category_id" required
                    class="auth-input {{ $errors->has('category_id') ? 'auth-input--error' : '' }}">
                <option value="">Selecciona una categoría</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id') === (string) $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            @error('category_id')
                <p class="auth-field-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="description" class="auth-label">¿Qué ocurrió? *</label>
            <textarea id="description" name="description" rows="4" required
                      minlength="10" maxlength="1000"
                      placeholder="Ej: El proyector no enciende desde esta mañana."
                      class="auth-input {{ $errors->has('description') ? 'auth-input--error' : '' }}">{{ old('description') }}</textarea>
            @error('description')
                <p class="auth-field-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="photo" class="auth-label">Foto (opcional)</label>
            <input id="photo" name="photo" type="file" accept="image/*"
                   class="auth-input {{ $errors->has('photo') ? 'auth-input--error' : '' }}">
            @error('photo')
                <p class="auth-field-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="contact_email" class="auth-label">Tu correo (opcional, para avisarte)</label>
            <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email') }}"
                   placeholder="tucorreo@ejemplo.com" autocomplete="email"
                   class="auth-input {{ $errors->has('contact_email') ? 'auth-input--error' : '' }}">
            @error('contact_email')
                <p class="auth-field-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="auth-submit">
            Enviar reporte
        </button>
    </form>

    <p class="auth-card-subtitle" style="margin-top: 1rem; text-align: center;">
        ¿Ya reportaste antes? <a href="{{ route('public.qr.track') }}" class="auth-label-link">Consulta el estado con tu código</a>.<br>
        ¿Tienes cuenta? <a href="{{ route('login') }}" class="auth-label-link">Inicia sesión</a> para reportar con seguimiento completo.
    </p>
</div>
@endsection
