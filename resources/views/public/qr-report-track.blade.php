@extends('layouts.auth')

@section('title', 'Seguimiento de reporte')

@section('content')
<div class="auth-card">

    <header class="auth-card-header">
        <div class="auth-card-icon" aria-hidden="true">
            <x-lucide-search width="20" height="20" stroke-width="2" />
        </div>
        <div>
            <h1 class="auth-card-title">Seguimiento de reporte</h1>
            <p class="auth-card-subtitle">Ingresa el código que recibiste al enviar tu reporte.</p>
        </div>
    </header>

    <form method="GET" action="{{ route('public.qr.track') }}" class="auth-form" novalidate>
        <div class="auth-field">
            <label for="code" class="auth-label">Código de seguimiento</label>
            <input id="code" name="code" type="text" value="{{ $code }}"
                   placeholder="Ej: QR-X7K2M9" autocomplete="off"
                   class="auth-input" style="font-family: 'DM Mono', monospace; text-transform: uppercase;">
        </div>
        <button type="submit" class="auth-submit">Consultar</button>
    </form>

    @if ($searched)
        @if ($contact === null || $contact->ticket === null)
            <div class="auth-alert auth-alert--error" role="alert" style="margin-top: 1rem;">
                <x-lucide-alert-circle width="16" height="16" stroke-width="2" aria-hidden="true" />
                <span>No encontramos un reporte con ese código. Verifica que esté bien escrito.</span>
            </div>
        @else
            @php($ticket = $contact->ticket)
            <div class="auth-alert auth-alert--success" role="status" style="margin-top: 1rem;">
                <x-lucide-info width="16" height="16" stroke-width="2" aria-hidden="true" />
                <span>
                    <strong>Estado:</strong> {{ $stateLabels[$ticket->state] ?? $ticket->state }}<br>
                    <strong>Ubicación:</strong> {{ $ticket->location?->getDisplayLabel() ?? '—' }}<br>
                    <strong>Categoría:</strong> {{ $ticket->category?->name ?? '—' }}<br>
                    <strong>Reportado:</strong> {{ $ticket->created_at?->format('d/m/Y H:i') ?? '—' }}
                </span>
            </div>
        @endif
    @endif
</div>
@endsection
