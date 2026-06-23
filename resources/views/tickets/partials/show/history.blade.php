{{-- ⑥ Historial de estados --}}
<section id="history" class="mt-6 ticket-show__card ticket-show__card--sectioned" aria-labelledby="history-heading">

    <div class="ticket-show__section-head">
        <span class="ticket-show__section-badge" aria-hidden="true">6</span>
        <h2 id="history-heading" class="ticket-show__section-head-title">Historial de estados</h2>
    </div>

    <div class="ticket-show__card-body">
        @if ($vm->stateHistory()->isNotEmpty())
            <ol class="ticket-show__history-timeline" aria-label="Historial de cambios de estado">
                @foreach ($vm->stateHistory() as $entry)
                    @php
                        $hDotClass = match ($entry->to_state) {
                            'open'        => 'ticket-show__history-dot--open',
                            'in_progress' => 'ticket-show__history-dot--progress',
                            'resolved'    => 'ticket-show__history-dot--resolved',
                            'rejected'    => 'ticket-show__history-dot--rejected',
                            'cancelled'   => 'ticket-show__history-dot--cancelled',
                            default       => '',
                        };
                        $hActionTone = match ($vm->actionFor($entry->from_state, $entry->to_state)) {
                            'Creación'                   => 'create',
                            'Actualización técnica'      => 'technical',
                            'Inicio de atención'         => 'start',
                            'Resolución'                 => 'resolve',
                            'Rechazo'                    => 'reject',
                            'Cancelación'                => 'cancel',
                            'Reapertura / actualización' => 'reopen',
                            default                      => 'neutral',
                        };
                    @endphp
                    <li class="ticket-show__history-event{{ $loop->last ? ' ticket-show__history-event--last' : '' }}">

                        <div class="ticket-show__history-marker" aria-hidden="true">
                            <div class="ticket-show__history-dot {{ $hDotClass }}" title="{{ $entry->changedBy?->name ?? '' }}">
                                {{ $vm->initials($entry->changedBy?->name, 2, 'U') }}
                            </div>
                            @unless ($loop->last)
                                <div class="ticket-show__history-line"></div>
                            @endunless
                        </div>

                        <div class="ticket-show__history-content">

                            <div class="ticket-show__history-event-head">
                                <div class="ticket-show__history-author-row">
                                    <span class="ticket-show__history-author">{{ $entry->changedBy?->name ?? $entry->changedBy?->email ?? '—' }}</span>
                                    <span class="ticket-show__history-action-badge ticket-show__history-action-badge--{{ $hActionTone }}">
                                        {{ $vm->actionFor($entry->from_state, $entry->to_state) }}
                                    </span>
                                </div>
                                <time class="ticket-show__history-time" datetime="{{ $entry->created_at?->toISOString() ?? '' }}">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M12 6v6l4 2"/></svg>
                                    {{ $vm->fmtDate($entry->created_at) ?? '—' }}
                                </time>
                            </div>

                            <div class="ticket-show__history-transition">
                                @if ($entry->from_state)
                                    <span class="{{ $vm->stateBadgeClasses()[$entry->from_state] ?? 'ts-badge ts-badge--neutral' }}">
                                        {{ $vm->stateLabels()[$entry->from_state] ?? ucfirst(str_replace('_', ' ', (string) $entry->from_state)) }}
                                    </span>
                                @else
                                    <span class="ts-badge ts-badge--neutral">Sin estado</span>
                                @endif
                                <span class="ticket-show__history-arrow" aria-hidden="true">→</span>
                                @if ($entry->to_state)
                                    <span class="{{ $vm->stateBadgeClasses()[$entry->to_state] ?? 'ts-badge ts-badge--neutral' }}">
                                        {{ $vm->stateLabels()[$entry->to_state] ?? ucfirst(str_replace('_', ' ', (string) $entry->to_state)) }}
                                    </span>
                                @else
                                    <span class="ts-badge ts-badge--neutral">—</span>
                                @endif
                            </div>

                            @if ($entry->comment !== null && trim((string) $entry->comment) !== '')
                                <div class="ticket-show__history-comment">{{ $entry->comment }}</div>
                            @else
                                <p class="ticket-show__history-empty-comment">Sin comentario</p>
                            @endif

                        </div>
                    </li>
                @endforeach
            </ol>
        @else
            <x-empty-state message="Sin cambios registrados.">
                <x-slot:icon>
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                </x-slot:icon>
            </x-empty-state>
        @endif
    </div>
</section>
