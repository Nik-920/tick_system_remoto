{{-- Header del ticket: título, código, badges, eliminar y métricas rápidas --}}
<div class="ticket-show__card mb-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
            <div class="ticket-show__icon-tile">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="2" y="3" width="20" height="14" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 21h8M12 17v4"/>
                </svg>
            </div>
            <div>
                <h1 class="ticket-show__heading">
                    {{ $ticket->title ?? 'Sin título' }}
                </h1>
                <div class="mt-1 flex items-center gap-2">
                    <span class="ticket-show__code">#{{ $ticketCode }}</span>
                    <button type="button"
                        data-copy-ticket="{{ $ticketCode }}"
                        aria-label="Copiar código del ticket"
                        class="copy-btn ticket-show__copy-btn focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="9" y="9" width="13" height="13" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                        <span class="copy-label">Copiar</span>
                    </button>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="{{ $stateBadge }}">
                        <span class="ts-badge__dot" aria-hidden="true"></span>
                        {{ $stateLabel }}
                    </span>
                    <span class="{{ $priorityBadge }}">
                        {{ $priorityLabel }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex-shrink-0 flex flex-wrap gap-2">
            @can('delete', $ticket)
                <form id="delete-ticket-form" method="POST" action="{{ route('tickets.destroy', $ticket) }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <button type="button" class="tickets-btn-danger" data-open-delete-modal>Eliminar</button>
                </form>
            @endcan
        </div>
    </div>

    {{-- ── Métricas rápidas ── --}}
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 ticket-show__divider pt-4">
        <div class="flex items-start gap-2.5">
            <div class="ticket-show__metric-icon">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </div>
            <div>
                <p class="ticket-show__label">Fecha de creación</p>
                <p class="ticket-show__value mt-0.5">{{ $fmtDate($ticket->created_at) ?? '—' }}</p>
            </div>
        </div>

        <div class="flex items-start gap-2.5">
            <div class="ticket-show__metric-icon">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
            <div>
                <p class="ticket-show__label">Última actualización</p>
                <p class="ticket-show__value mt-0.5">{{ $fmtDate($ticket->updated_at) ?? '—' }}</p>
            </div>
        </div>

        <div class="flex items-start gap-2.5">
            <div class="ticket-show__metric-icon">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            </div>
            <div>
                <p class="ticket-show__label">Tiempo transcurrido</p>
                <p class="ticket-show__value mt-0.5">{{ $ticket->created_at?->diffForHumans(null, true, true, 2) ?? '—' }}</p>
            </div>
        </div>

        <div class="flex items-start gap-2.5">
            <div class="ticket-show__metric-icon">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <p class="ticket-show__label">Desde asignación</p>
                <p class="ticket-show__value mt-0.5">
                    {{ $ticket->assigned_at?->diffForHumans(null, true, true, 2) ?? '—' }}
                </p>
            </div>
        </div>
    </div>
</div>
