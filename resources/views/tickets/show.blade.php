@extends('layouts.app')

@section('title', 'Detalle Ticket')

@section('content')
@php
    $stateLabels = [
        'open'        => 'Abierto',
        'in_progress' => 'En progreso',
        'resolved'    => 'Resuelto',
        'rejected'    => 'Rechazado',
        'cancelled'   => 'Cancelado',
    ];
    $priorityLabels = [
        'low'      => 'Baja',
        'medium'   => 'Media',
        'high'     => 'Alta',
        'critical' => 'Crítica',
    ];
    $stateBadgeClasses = [
        'open'        => 'ts-badge ts-badge--open',
        'in_progress' => 'ts-badge ts-badge--in_progress',
        'resolved'    => 'ts-badge ts-badge--resolved',
        'rejected'    => 'ts-badge ts-badge--rejected',
        'cancelled'   => 'ts-badge ts-badge--cancelled',
    ];
    $priorityBadgeClasses = [
        'low'      => 'ts-badge ts-badge--prio-low',
        'medium'   => 'ts-badge ts-badge--prio-medium',
        'high'     => 'ts-badge ts-badge--prio-high',
        'critical' => 'ts-badge ts-badge--prio-critical',
    ];

    $stateBadge    = $stateBadgeClasses[$ticket->state] ?? 'ts-badge ts-badge--neutral';
    $priorityBadge = $priorityBadgeClasses[$ticket->priority] ?? 'ts-badge ts-badge--neutral';
    $stateLabel    = $stateLabels[$ticket->state] ?? ucfirst(str_replace('_', ' ', (string) $ticket->state));
    $priorityLabel = $priorityLabels[$ticket->priority] ?? ucfirst((string) $ticket->priority);

    // Presentation dates in Perú time (America/Lima) — storage stays untouched.
    $fmtDate = fn ($value, string $format = 'd/m/Y H:i') => \App\Support\LocalTime::format($value, $format);

    // Visual code derived from the UUID (tickets has no `code` column).
    $ticketCode = 'INC-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $ticket->id, 0, 8));

    $stateHistory = $ticket->stateHistory; // loaded oldest → newest
    $lastHistory  = $stateHistory->last();
    $lastComment  = $stateHistory->reverse()->first(
        fn ($entry) => trim((string) $entry->comment) !== ''
    )?->comment;

    // ticket_media has no author-role column: split by uploaded_by vs reporter_id.
    $reporterEvidence = $ticket->media->filter(
        fn ($media) => $media->uploaded_by !== null && (string) $media->uploaded_by === (string) $ticket->reporter_id
    )->values();
    $maintenanceEvidence = $ticket->media->reject(
        fn ($media) => $media->uploaded_by !== null && (string) $media->uploaded_by === (string) $ticket->reporter_id
    )->values();

    $fileNameFor = fn ($media) => basename(parse_url((string) $media->file_url, PHP_URL_PATH) ?: (string) $media->file_url);

    // state_history has no action_type column: derive it from the transition.
    $actionFor = function (?string $from, ?string $to): string {
        if ($from === null || $from === '') {
            return 'Creación';
        }

        if ($from === $to) {
            return 'Actualización técnica';
        }

        return match ($to) {
            'in_progress' => 'Inicio de atención',
            'resolved'    => 'Resolución',
            'rejected'    => 'Rechazo',
            'cancelled'   => 'Cancelación',
            'open'        => 'Reapertura / actualización',
            default       => 'Cambio de estado',
        };
    };

    $authId         = auth()->id();
    $isAssignedToMe = $authId !== null && $ticket->assigned_to !== null && (string) $ticket->assigned_to === (string) $authId;
    $isUnassigned   = $ticket->assigned_to === null;
    $isClosed       = in_array($ticket->state, ['resolved', 'rejected', 'cancelled'], true);
    $isMaintenance  = $isMaintenance ?? false;
    $isAvailableForClaim   = $isAvailableForClaim ?? false;
    $availableTransitions  = $availableTransitions ?? [];
    $canEditOperational    = $canEditOperational ?? false;
    $categories            = $categories ?? collect();
    $priorities            = $priorities ?? ['low', 'medium', 'high', 'critical'];

    $assignee   = $ticket->assignee;
    $assignedBy = $ticket->assignedBy;
    $assignmentLocked = (bool) $ticket->assignment_locked;

    if ($ticket->assignment_source === \App\Models\Ticket::ASSIGNMENT_SOURCE_SELF) {
        $assignmentTypeLabel = 'Tomado por mantenimiento';
    } elseif ($ticket->assignment_source === \App\Models\Ticket::ASSIGNMENT_SOURCE_ADMIN) {
        $assignmentTypeLabel = 'Asignación fija por administración';
    } else {
        $assignmentTypeLabel = 'Sin tipo registrado';
    }

    $recommendedAction = match (true) {
        $ticket->state === 'open' && $isUnassigned && $isMaintenance => 'Toma el ticket para iniciar atención.',
        $ticket->state === 'open' && $isAssignedToMe                 => 'Inicia la atención del ticket.',
        $ticket->state === 'in_progress' && $isAssignedToMe          => 'Continúa la atención o resuelve el ticket si corresponde.',
        $ticket->state === 'resolved'                                => 'Ticket resuelto. Revisa la información de cierre.',
        $ticket->state === 'rejected'                                => 'Ticket rechazado. Revisa el motivo en el historial.',
        $ticket->state === 'cancelled'                               => 'Ticket cancelado. Solo lectura.',
        default                                                      => 'Revisa el estado actual y actúa según corresponda.',
    };

    // Operational timers
    $inProgressSince = $stateHistory->first(fn ($entry) => $entry->to_state === 'in_progress')?->created_at;
    $closureEntry    = $stateHistory->reverse()->first(
        fn ($entry) => in_array($entry->to_state, ['resolved', 'rejected', 'cancelled'], true) && $entry->from_state !== $entry->to_state
    );
    $progressEnd     = $ticket->resolved_at ?? $closureEntry?->created_at;
    $timeInProgress  = null;
    if ($inProgressSince !== null) {
        $timeInProgress = $isClosed && $progressEnd !== null
            ? $inProgressSince->diffForHumans($progressEnd, true, true, 2)
            : ($ticket->state === 'in_progress' ? $inProgressSince->diffForHumans(null, true, true, 2) : null);
    }

    $canStart    = in_array('in_progress', $availableTransitions, true) && $ticket->state === 'open';
    $canContinue = $ticket->state === 'in_progress' && $isAssignedToMe;
    $canResolve  = in_array('resolved', $availableTransitions, true);

    $backUrl   = $isMaintenance ? route('tickets.assignments') : route('tickets.index');
    $backLabel = $isMaintenance ? 'Volver a mis asignaciones' : 'Volver a tickets';
@endphp

<div class="ticket-show ticket-show-page mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

    {{-- ── Link volver superior ── --}}
    <div class="mb-5">
        <a href="{{ $backUrl }}"
           class="ticket-show__back-link focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            {{ $backLabel }}
        </a>
    </div>

    {{-- ── Alerts ── --}}
    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    @if (isset($errors) && $errors->any())
        <div class="alert-error">
            <p class="font-semibold mb-2">Errores en la actualización:</p>
            <ul class="space-y-1">
                @foreach ($errors->all() as $error)
                    <li class="text-sm">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Banner de duplicado (IA) ── --}}
    @include('tickets.partials.show.duplicate-banner')

    {{-- ── Header del ticket + métricas ── --}}
    @include('tickets.partials.show.header')

    {{-- ── Avisos operativos para maintenance ── --}}
    @if ($isMaintenance && $isAvailableForClaim)
        <div class="ticket-show__notice ticket-show__notice--warning">
            <p class="ticket-show__notice-title">📋 Ticket disponible para tomar</p>
            <p class="ticket-show__notice-text">Para iniciar atención debes tomar el ticket primero. Una vez tomado, podrás actualizar su estado.</p>
            @can('claim', $ticket)
                <form method="POST" action="{{ route('tickets.claim', $ticket) }}" class="mt-3 tickets-once-form">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <button type="submit" class="ticket-show__btn ticket-show__btn--primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Tomar este ticket
                    </button>
                </form>
            @endcan
        </div>
    @elseif ($isMaintenance && $isAssignedToMe)
        <div class="ticket-show__notice ticket-show__notice--success">
            <p class="ticket-show__notice-title">✅ Este ticket está asignado a ti</p>
            <p class="ticket-show__notice-text">Puedes actualizar su estado según el avance de atención.</p>
        </div>
    @endif

    {{-- ── Grid principal ── --}}
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">

        {{-- ── Columna izquierda ── --}}
        <div class="flex flex-col gap-6">
            @include('tickets.partials.show.main-info')
            @include('tickets.partials.show.operational-state')
            @include('tickets.partials.show.update-state')
        </div>

        {{-- ── Columna derecha ── --}}
        <div class="flex flex-col gap-6">
            @include('tickets.partials.show.assignment')
            @include('tickets.partials.show.quick-actions')
        </div>

    </div>{{-- fin grid principal --}}

    @include('tickets.partials.show.evidence')
    @include('tickets.partials.show.history')
    @include('tickets.partials.show.closing-info')

    {{-- ── Botón volver inferior ── --}}
    <div class="mt-6 pb-2">
        <a href="{{ $backUrl }}"
           class="ticket-show__back-link focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver
        </a>
    </div>

</div>

@include('tickets.partials.show.delete-modal')
@endsection
