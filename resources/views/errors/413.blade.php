@extends('layouts.app')

@section('title', 'Archivo demasiado grande — INCIDEX')

@section('content')
<div class="error-page">
    <div class="error-page__card">
        <div class="error-page__icon error-page__icon--warning" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none"
                 stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
        </div>

        <h1 class="error-page__title">Archivos demasiado grandes</h1>

        <p class="error-page__message">
            {{ $message ?? 'El archivo o conjunto de archivos supera el límite permitido. Sube archivos más pequeños o menos archivos a la vez.' }}
        </p>

        <ul class="error-page__hints">
            <li>Cada archivo puede pesar hasta <strong>10 MB</strong> al crear un ticket.</li>
            <li>Para edición o evidencias de mantenimiento el límite es <strong>5 MB</strong> por archivo.</li>
            <li>Puedes subir hasta <strong>5 archivos</strong> por vez.</li>
            <li>Usa JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX o MP4.</li>
        </ul>

        <div class="error-page__actions">
            <a href="{{ url()->previous(url('/')) }}" class="error-page__btn error-page__btn--primary">
                Volver e intentarlo de nuevo
            </a>
            <a href="{{ route('tickets.create') }}" class="error-page__btn error-page__btn--ghost">
                Ir a crear ticket
            </a>
        </div>
    </div>
</div>
@endsection
