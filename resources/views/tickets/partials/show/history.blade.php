{{-- ⑥ Historial de estados --}}
<section id="history" class="mt-6 ticket-show__card ticket-show__card--sectioned" aria-labelledby="history-heading">

    <div class="ticket-show__section-head">
        <span class="ticket-show__section-badge" aria-hidden="true">6</span>
        <h2 id="history-heading" class="ticket-show__section-head-title">Historial de estados</h2>
    </div>

    <div class="ticket-show__card-body">
        @if ($vm->stateHistory()->isNotEmpty())
            <div class="ticket-show__table-wrap">
                <table class="ticket-show__table">
                    <thead>
                        <tr>
                            <th scope="col">Estado anterior</th>
                            <th scope="col">Estado nuevo</th>
                            <th scope="col">Cambiado por</th>
                            <th scope="col">Tipo de acción</th>
                            <th scope="col">Comentario</th>
                            <th scope="col">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vm->stateHistory() as $entry)
                            <tr>
                                <td>
                                    @if ($entry->from_state)
                                        <span class="{{ $vm->stateBadgeClasses()[$entry->from_state] ?? 'ts-badge ts-badge--neutral' }}">
                                            {{ $vm->stateLabels()[$entry->from_state] ?? ucfirst(str_replace('_', ' ', $entry->from_state)) }}
                                        </span>
                                    @else
                                        <span class="ticket-show__faint">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($entry->to_state)
                                        <span class="{{ $vm->stateBadgeClasses()[$entry->to_state] ?? 'ts-badge ts-badge--neutral' }}">
                                            {{ $vm->stateLabels()[$entry->to_state] ?? ucfirst(str_replace('_', ' ', $entry->to_state)) }}
                                        </span>
                                    @else
                                        <span class="ticket-show__faint">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center gap-1.5">
                                        <x-avatar :initials="$vm->initials($entry->changedBy?->name, 2, 'U')" tone="primary" class="w-5 h-5 text-[10px]" aria-hidden="true" />
                                        <span class="is-strong whitespace-nowrap">{{ $entry->changedBy?->name ?? $entry->changedBy?->email ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">{{ $vm->actionFor($entry->from_state, $entry->to_state) }}</td>
                                <td class="max-w-[220px]">
                                    <p class="truncate" title="{{ $entry->comment ?? '' }}">{{ $entry->comment ?? '—' }}</p>
                                </td>
                                <td class="whitespace-nowrap">{{ $vm->fmtDate($entry->created_at) ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state message="Aún no hay cambios de estado registrados">
                <x-slot:icon>
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
                </x-slot:icon>
            </x-empty-state>
        @endif
    </div>
</section>
