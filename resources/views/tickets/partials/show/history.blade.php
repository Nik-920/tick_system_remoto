{{-- ⑦ Historial de estados --}}
<section id="history" class="mt-6 ticket-show__card" aria-labelledby="history-heading">
    <h2 id="history-heading" class="ticket-show__title">
        <span class="ticket-show__section-number" aria-hidden="true">7</span>
        Historial de estados
    </h2>

    @if ($stateHistory->isNotEmpty())
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
                    @foreach ($stateHistory as $entry)
                        @php
                            $fromBadge = $stateBadgeClasses[$entry->from_state] ?? 'ts-badge ts-badge--neutral';
                            $toBadge   = $stateBadgeClasses[$entry->to_state] ?? 'ts-badge ts-badge--neutral';
                        @endphp
                        <tr>
                            <td>
                                @if ($entry->from_state)
                                    <span class="{{ $fromBadge }}">
                                        {{ $stateLabels[$entry->from_state] ?? ucfirst(str_replace('_', ' ', $entry->from_state)) }}
                                    </span>
                                @else
                                    <span class="ticket-show__faint">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($entry->to_state)
                                    <span class="{{ $toBadge }}">
                                        {{ $stateLabels[$entry->to_state] ?? ucfirst(str_replace('_', ' ', $entry->to_state)) }}
                                    </span>
                                @else
                                    <span class="ticket-show__faint">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center gap-1.5">
                                    <span class="ticket-show__avatar ticket-show__avatar--primary w-5 h-5 text-[10px]" aria-hidden="true">
                                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($entry->changedBy?->name ?? 'U', 0, 2)) }}
                                    </span>
                                    <span class="is-strong whitespace-nowrap">{{ $entry->changedBy?->name ?? $entry->changedBy?->email ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">{{ $actionFor($entry->from_state, $entry->to_state) }}</td>
                            <td class="max-w-[220px]">
                                <p class="truncate" title="{{ $entry->comment ?? '' }}">{{ $entry->comment ?? '—' }}</p>
                            </td>
                            <td class="whitespace-nowrap">{{ $fmtDate($entry->created_at) ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="ticket-show__empty">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
            <p>Aún no hay cambios de estado registrados</p>
        </div>
    @endif
</section>
