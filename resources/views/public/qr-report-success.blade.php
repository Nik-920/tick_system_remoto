@extends('layouts.auth')

@section('title', 'Reporte enviado')

@section('content')
<div class="auth-card">

    <header class="auth-card-header">
        <div class="auth-card-icon" aria-hidden="true">
            <x-lucide-check-circle width="20" height="20" stroke-width="2" />
        </div>
        <div>
            <h1 class="auth-card-title">¡Reporte enviado!</h1>
            <p class="auth-card-subtitle">El equipo de soporte fue notificado. Gracias por avisar.</p>
        </div>
    </header>

    <div class="auth-alert auth-alert--success" role="status" aria-live="polite">
        <x-lucide-ticket width="16" height="16" stroke-width="2" aria-hidden="true" />
        <span>
            Tu código de seguimiento es
            <strong style="font-family: 'DM Mono', monospace; font-size: 1.05em; letter-spacing: 0.05em;">{{ $trackingCode }}</strong>
        </span>
    </div>

    <p class="auth-card-subtitle" style="margin-top: 0.75rem;">
        Guárdalo (una foto a la pantalla basta). Con ese código puedes consultar el estado
        de tu reporte en cualquier momento, sin necesidad de cuenta.
    </p>

    <a href="{{ route('public.qr.track', ['code' => $trackingCode]) }}" class="auth-submit" style="display: block; text-align: center; text-decoration: none; margin-top: 1rem;">
        Ver estado de mi reporte
    </a>
</div>
@endsection
