@extends('layouts.app')

@section('title', 'Detalle Ticket')

@section('content')
<div class="ticket-show ticket-show-page mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

    {{-- ── Link volver superior ── --}}
    <div class="mb-5">
        <a href="{{ $vm->backUrl() }}"
           class="ticket-show__back-link focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            {{ $vm->backLabel() }}
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
    @if ($vm->isMaintenance && $vm->isAvailableForClaim)
        <div class="ticket-show__notice ticket-show__notice--warning">
            <p class="ticket-show__notice-title">📋 Ticket disponible para tomar</p>
            <p class="ticket-show__notice-text">Para iniciar atención debes tomar el ticket primero. Una vez tomado, podrás actualizar su estado.</p>
            @can('claim', $ticket)
                <form method="POST" action="{{ route('tickets.claim', $ticket) }}" class="mt-3 tickets-once-form">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="idempotency_key" value="{{ $vm->generateIdempotencyKey() }}">
                    <button type="submit" class="ticket-show__btn ticket-show__btn--primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Tomar este ticket
                    </button>
                </form>
            @endcan
        </div>
    @elseif ($vm->isMaintenance && $vm->isAssignedToMe())
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
        <a href="{{ $vm->backUrl() }}"
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
