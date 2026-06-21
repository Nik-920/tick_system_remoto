{{-- ⑦ Información final / cierre --}}
<section class="mt-6 ticket-show__card ticket-show__card--sectioned" aria-labelledby="closure-heading">

    <div class="ticket-show__section-head">
        <span class="ticket-show__section-badge" aria-hidden="true">7</span>
        <h2 id="closure-heading" class="ticket-show__section-head-title">Información final / cierre</h2>
    </div>

    <div class="ticket-show__card-body">
        @if (! $vm->isClosed())
            <div class="flex items-center gap-4 ticket-show__box p-5">
                <div class="flex-shrink-0 ticket-show__faint">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 64 64" aria-hidden="true">
                        <rect x="8" y="8" width="48" height="48" rx="8" stroke-width="2"/>
                        <path d="M20 32h24M20 22h24M20 42h16" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="48" cy="48" r="10" fill="var(--bg-surface)" stroke-width="2"/>
                        <path d="M44 48l3 3 5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <p class="ticket-show__value mb-1">Este ticket aún no ha sido resuelto.</p>
                    <p class="text-sm ticket-show__muted">La información de cierre se mostrará una vez que el ticket sea resuelto.</p>
                </div>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                <div>
                    <p class="ticket-show__label">Estado final</p>
                    <span class="{{ $vm->stateBadge() }}">
                        <span class="ts-badge__dot" aria-hidden="true"></span>
                        {{ $vm->stateLabel() }}
                    </span>
                </div>
                <div>
                    <p class="ticket-show__label">Fecha de cierre</p>
                    <p class="ticket-show__value">
                        {{ $vm->fmtDate($ticket->resolved_at) ?? $vm->fmtDate($vm->closureEntry()?->created_at) ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="ticket-show__label">Cambiado por</p>
                    @if ($vm->closureEntry()?->changedBy)
                        <div class="flex items-center gap-1.5">
                            <x-avatar :initials="$vm->initials($vm->closureEntry()->changedBy->name, 2, 'U')" tone="success" class="w-6 h-6 text-[10px]" aria-hidden="true" />
                            <span class="ticket-show__value">{{ $vm->closureEntry()->changedBy->name ?? $vm->closureEntry()->changedBy->email }}</span>
                        </div>
                    @else
                        <p class="ticket-show__value">—</p>
                    @endif
                </div>
                @if (trim((string) $vm->closureEntry()?->comment) !== '')
                    <div class="sm:col-span-2 lg:col-span-3">
                        <p class="ticket-show__label mb-1">Comentario de cierre</p>
                        <div class="ticket-show__closure-comment {{ $ticket->state === 'resolved' ? 'ticket-show__closure-comment--resolved' : '' }}">
                            {{ $vm->closureEntry()->comment }}
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
