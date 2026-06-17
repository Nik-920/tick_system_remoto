{{-- ② Estado operativo del ticket --}}
<section class="ticket-show__card" aria-labelledby="operative-heading">
    <h2 id="operative-heading" class="ticket-show__title">
        <span class="ticket-show__section-number" aria-hidden="true">2</span>
        Estado operativo del ticket
    </h2>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-5 text-sm">
        <div>
            <p class="ticket-show__label">Estado actual de atención</p>
            <span class="{{ $vm->stateBadge() }}">
                <span class="ts-badge__dot" aria-hidden="true"></span>
                {{ $vm->stateLabel() }}
            </span>
        </div>
        <div>
            <p class="ticket-show__label">Último cambio de estado</p>
            <p class="ticket-show__value">{{ $vm->fmtDate($vm->lastHistory()?->created_at) ?? '—' }}</p>
        </div>
        <div>
            <p class="ticket-show__label">Tiempo desde asignación</p>
            <div class="flex items-center gap-1 ticket-show__value">
                <svg class="w-3.5 h-3.5 ticket-show__faint flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                {{ $ticket->assigned_at?->diffForHumans(null, true, true, 2) ?? '—' }}
            </div>
        </div>
        <div>
            <p class="ticket-show__label">Tiempo en progreso</p>
            <div class="flex items-center gap-1 ticket-show__value">
                <svg class="w-3.5 h-3.5 ticket-show__faint flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                {{ $vm->timeInProgress() ?? '—' }}
            </div>
        </div>
    </div>

    <div class="mb-5">
        <p class="ticket-show__label mb-1.5">Último comentario</p>
        <div class="ticket-show__box">
            {{ $vm->lastComment() ?? 'Sin comentarios registrados.' }}
        </div>
    </div>

    <div class="mb-5">
        <p class="ticket-show__label mb-2">Acción recomendada</p>
        <p class="text-sm ticket-show__value--soft">{{ $vm->recommendedAction() }}</p>
    </div>

    {{-- Botones operativos --}}
    <div class="flex flex-wrap gap-2 pt-3 ticket-show__divider">
        @if ($vm->isAvailableForClaim)
            @can('claim', $ticket)
                <form method="POST" action="{{ route('tickets.claim', $ticket) }}" class="tickets-once-form">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="idempotency_key" value="{{ $vm->generateIdempotencyKey() }}">
                    <button type="submit" class="ticket-show__btn ticket-show__btn--primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Tomar este ticket
                    </button>
                </form>
            @endcan
        @endif

    @can('updateState', $ticket)
        @if ($vm->canStart())
            <form method="POST" action="{{ route('tickets.update-state', $ticket) }}" class="tickets-once-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="idempotency_key" value="{{ $vm->generateIdempotencyKey() }}">
                <input type="hidden" name="to_state" value="in_progress">
                <button type="submit" class="ticket-show__btn ticket-show__btn--primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Iniciar atención
                </button>
            </form>
        @endif

        @if ($vm->canContinue())
            <a href="#update-state" class="ticket-show__btn ticket-show__btn--primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Continuar atención
            </a>
        @endif

        @if ($vm->canResolve())
            <a href="#update-state" class="ticket-show__btn ticket-show__btn--success focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Resolver ticket
            </a>
        @endif
    @endcan

    @can('release', $ticket)
        <form method="POST" action="{{ route('tickets.release', $ticket) }}" class="tickets-once-form" data-confirm="¿Liberar este ticket?">
            @csrf
            @method('PATCH')
            <input type="hidden" name="idempotency_key" value="{{ $vm->generateIdempotencyKey() }}">
            <button type="submit" class="ticket-show__btn ticket-show__btn--ghost focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Liberar ticket
            </button>
        </form>
    @endcan

        <a href="#history" class="ticket-show__btn ticket-show__btn--ghost focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
            Ver historial
        </a>
    </div>
</section>
