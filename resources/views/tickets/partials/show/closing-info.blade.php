{{-- ⑧ Información final / cierre --}}
<section class="mt-6 ticket-show__card" aria-labelledby="closure-heading">
    <h2 id="closure-heading" class="ticket-show__title">
        <span class="ticket-show__section-number" aria-hidden="true">8</span>
        Información final / cierre
    </h2>

    @if (! $isClosed)
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
                <span class="{{ $stateBadge }}">
                    <span class="ts-badge__dot" aria-hidden="true"></span>
                    {{ $stateLabel }}
                </span>
            </div>
            <div>
                <p class="ticket-show__label">Fecha de cierre</p>
                <p class="ticket-show__value">
                    {{ $fmtDate($ticket->resolved_at) ?? $fmtDate($closureEntry?->created_at) ?? '—' }}
                </p>
            </div>
            <div>
                <p class="ticket-show__label">Cambiado por</p>
                @if ($closureEntry?->changedBy)
                    <div class="flex items-center gap-1.5">
                        <span class="ticket-show__avatar ticket-show__avatar--success w-6 h-6 text-[10px]" aria-hidden="true">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($closureEntry->changedBy->name ?? 'U', 0, 2)) }}
                        </span>
                        <span class="ticket-show__value">{{ $closureEntry->changedBy->name ?? $closureEntry->changedBy->email }}</span>
                    </div>
                @else
                    <p class="ticket-show__value">—</p>
                @endif
            </div>
            @if (trim((string) $closureEntry?->comment) !== '')
                <div class="sm:col-span-2 lg:col-span-3">
                    <p class="ticket-show__label mb-1">Comentario de cierre</p>
                    <div class="ticket-show__closure-comment {{ $ticket->state === 'resolved' ? 'ticket-show__closure-comment--resolved' : '' }}">
                        {{ $closureEntry->comment }}
                    </div>
                </div>
            @endif
        </div>
    @endif
</section>
