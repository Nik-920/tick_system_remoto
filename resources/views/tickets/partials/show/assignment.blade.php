{{-- ④ Asignación --}}
<section class="ticket-show__card ticket-show__card--sectioned" aria-labelledby="assignment-heading">

    <div class="ticket-show__section-head">
        <span class="ticket-show__section-badge" aria-hidden="true">4</span>
        <h2 id="assignment-heading" class="ticket-show__section-head-title">Asignación</h2>
    </div>

    <div class="ticket-show__card-body">
        <div class="space-y-4 text-sm">
            <div>
                <p class="ticket-show__label mb-1.5">Asignado a</p>
                @if ($vm->assignee())
                    <div class="flex items-center gap-2.5">
                        <x-avatar :initials="$vm->initials($vm->assignee()->name, 2, 'T')" tone="primary" class="w-8 h-8 text-xs" aria-hidden="true" />
                        <div class="min-w-0">
                            <p class="ticket-show__value truncate">{{ $vm->assignee()->name ?? $vm->assignee()->email }}</p>
                            <p class="text-xs ticket-show__muted truncate">{{ $vm->assignee()->email ?? '—' }}</p>
                        </div>
                        @if ($vm->isAssignedToMe())
                            <span class="ts-badge ts-badge--resolved flex-shrink-0 text-[10px]">
                                Técnico actual
                            </span>
                        @endif
                    </div>
                @else
                    <p class="ticket-show__muted">Sin asignar</p>
                @endif
            </div>

            <div>
                <p class="ticket-show__label mb-1.5">Asignado por</p>
                @if ($vm->assignedBy())
                    <div class="flex items-center gap-2.5">
                        <x-avatar :initials="$vm->initials($vm->assignedBy()->name, 2, 'A')" tone="violet" class="w-8 h-8 text-xs" aria-hidden="true" />
                        <div class="min-w-0">
                            <p class="ticket-show__value truncate">{{ $vm->assignedBy()->name ?? $vm->assignedBy()->email }}</p>
                            <p class="text-xs ticket-show__muted truncate">{{ $vm->assignedBy()->email ?? '—' }}</p>
                        </div>
                    </div>
                @else
                    <p class="ticket-show__muted">—</p>
                @endif
            </div>

            <div>
                <p class="ticket-show__label">Fecha de asignación</p>
                <div class="flex items-center gap-1.5 ticket-show__value">
                    <svg class="w-3.5 h-3.5 ticket-show__faint flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    {{ $vm->fmtDate($ticket->assigned_at) ?? '—' }}
                </div>
            </div>

            <div>
                <p class="ticket-show__label">Tipo de asignación</p>
                <p class="ticket-show__value">{{ $ticket->assigned_to !== null || $ticket->assignment_source ? $vm->assignmentTypeLabel() : 'Sin asignación' }}</p>
            </div>

            <div>
                <p class="ticket-show__label mb-1">Estado de bloqueo</p>
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 {{ $vm->assignmentLocked() ? 'text-amber-500' : 'text-emerald-500' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        @if ($vm->assignmentLocked())
                            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        @else
                            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                        @endif
                    </svg>
                    <span class="{{ $vm->assignmentLocked() ? 'ts-badge ts-badge--prio-medium' : 'ts-badge ts-badge--resolved' }}">
                        {{ $vm->assignmentLocked() ? 'Fija' : ($ticket->assigned_to ? 'Flexible' : 'Sin asignación') }}
                    </span>
                </div>
                <p class="text-xs ticket-show__muted mt-1">
                    {{ $vm->assignmentLocked() ? 'No puede ser reasignado' : 'Puede ser reasignado' }}
                </p>
                @if ($vm->assignmentLocked() && $vm->isAssignedToMe())
                    <p class="text-xs ticket-show__muted mt-1">Asignación fija por administración. Solo Admin o SuperAdmin puede cambiarla.</p>
                @endif
            </div>
        </div>

        {{-- Gestión de asignación (solo admin/super_admin) --}}
        @can('assign', $ticket)
            <div class="mt-5 pt-4 ticket-show__divider space-y-3">
                <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="tickets-once-form space-y-2">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="idempotency_key" value="{{ $vm->generateIdempotencyKey() }}">
                    <label for="assigned_to" class="ticket-show__field-label">Asignar a</label>
                    <select id="assigned_to" name="assigned_to" required class="ticket-show__control">
                        <option value="">Selecciona maintenance</option>
                        @foreach (($maintenanceUsers ?? collect()) as $maintenanceUser)
                            <option value="{{ $maintenanceUser->id }}" @selected($ticket->assigned_to === $maintenanceUser->id)>
                                {{ $maintenanceUser->name ?? $maintenanceUser->email ?? $maintenanceUser->id }}
                            </option>
                        @endforeach
                    </select>
                    @error('assigned_to')
                        <p class="ticket-show__error">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="ticket-show__btn ticket-show__btn--primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-700">
                        {{ $ticket->assigned_to ? 'Reasignar' : 'Asignar' }}
                    </button>
                </form>

                @if ($ticket->assigned_to)
                    @can('unassign', $ticket)
                        <form method="POST" action="{{ route('tickets.unassign', $ticket) }}" class="tickets-once-form" data-confirm="¿Quitar la asignación de este ticket?">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="idempotency_key" value="{{ $vm->generateIdempotencyKey() }}">
                            <button type="submit" class="ticket-show__btn ticket-show__btn--ghost focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">
                                Desasignar
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
        @endcan

        @error('assignment')
            <p class="ticket-show__error mt-2">{{ $message }}</p>
        @enderror
    </div>
</section>
