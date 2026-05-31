<?php

namespace App\Services\Tickets;

use App\Events\TicketAssigned;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TicketAssignmentService
{
    public function __construct(private TicketQrLogger $logger) {}

    public function claimByMaintenance(Ticket $ticket, User $actor): Ticket
    {
        $correlationId = $this->resolveCorrelationId('');

        $updatedTicket = DB::transaction(function () use ($ticket, $actor): Ticket {
            $lockedTicket = Ticket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertActorIsMaintenance($actor);
            $this->assertClaimable($lockedTicket);

            $this->updateAssignmentFields(
                $lockedTicket,
                $actor,
                $actor,
                false,
                Ticket::ASSIGNMENT_SOURCE_SELF
            );

            $this->recordAssignmentHistory(
                $lockedTicket,
                $actor,
                null,
                $actor,
                'claimed'
            );

            return $lockedTicket;
        });

        $this->dispatchAssignmentEvent('claimed', $updatedTicket, $actor, null, $actor, $correlationId);

        $this->logger->info('ticket.assignment.claimed', [
            'ticket_id' => $updatedTicket->id,
            'location_id' => $updatedTicket->location_id,
            'category_id' => $updatedTicket->category_id,
            'actor_id' => $actor->id,
            'assigned_to' => $actor->id,
            'correlation_id' => $correlationId,
            'state' => $updatedTicket->state,
        ]);

        return $this->refreshTicket($updatedTicket);
    }

    public function releaseByMaintenance(Ticket $ticket, User $actor): Ticket
    {
        $correlationId = $this->resolveCorrelationId('');

        $previousAssignee = null;

        $updatedTicket = DB::transaction(function () use ($ticket, $actor, &$previousAssignee): Ticket {
            $lockedTicket = Ticket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertReleasableByMaintenance($lockedTicket, $actor);

            $previousAssignee = $lockedTicket->assignee;

            $this->updateAssignmentFields(
                $lockedTicket,
                null,
                $actor,
                false,
                null
            );

            $this->recordAssignmentHistory(
                $lockedTicket,
                $actor,
                $previousAssignee,
                null,
                'released'
            );

            return $lockedTicket;
        });

        $this->dispatchAssignmentEvent('released', $updatedTicket, $actor, $previousAssignee, null, $correlationId);

        $this->logger->info('ticket.assignment.released', [
            'ticket_id' => $updatedTicket->id,
            'location_id' => $updatedTicket->location_id,
            'category_id' => $updatedTicket->category_id,
            'actor_id' => $actor->id,
            'correlation_id' => $correlationId,
            'state' => $updatedTicket->state,
        ]);

        return $this->refreshTicket($updatedTicket);
    }

    public function assignByAdmin(Ticket $ticket, User $actor, User $target): Ticket
    {
        return $this->applyAdminAssignment($ticket, $actor, $target, 'assigned');
    }

    public function reassignByAdmin(Ticket $ticket, User $actor, User $target): Ticket
    {
        return $this->applyAdminAssignment($ticket, $actor, $target, 'reassigned');
    }

    public function unassignByAdmin(Ticket $ticket, User $actor): Ticket
    {
        $correlationId = $this->resolveCorrelationId('');

        $previousAssignee = null;

        $updatedTicket = DB::transaction(function () use ($ticket, $actor, &$previousAssignee): Ticket {
            $lockedTicket = Ticket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertActorCanManageAssignment($actor);
            $this->assertAssignable($lockedTicket);

            if ($lockedTicket->assigned_to === null) {
                throw new InvalidArgumentException('El ticket no tiene asignacion para desasignar.');
            }

            $previousAssignee = $lockedTicket->assignee;

            $this->updateAssignmentFields(
                $lockedTicket,
                null,
                $actor,
                false,
                null
            );

            $this->recordAssignmentHistory(
                $lockedTicket,
                $actor,
                $previousAssignee,
                null,
                'unassigned'
            );

            return $lockedTicket;
        });

        $this->dispatchAssignmentEvent('unassigned', $updatedTicket, $actor, $previousAssignee, null, $correlationId);

        $this->logger->info('ticket.assignment.unassigned', [
            'ticket_id' => $updatedTicket->id,
            'location_id' => $updatedTicket->location_id,
            'category_id' => $updatedTicket->category_id,
            'actor_id' => $actor->id,
            'correlation_id' => $correlationId,
            'state' => $updatedTicket->state,
        ]);

        return $this->refreshTicket($updatedTicket);
    }

    private function applyAdminAssignment(Ticket $ticket, User $actor, User $target, string $action): Ticket
    {
        $correlationId = $this->resolveCorrelationId('');

        $previousAssignee = null;

        $updatedTicket = DB::transaction(function () use ($ticket, $actor, $target, $action, &$previousAssignee): Ticket {
            $lockedTicket = Ticket::query()
                ->whereKey($ticket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertActorCanManageAssignment($actor);
            $this->assertAssignable($lockedTicket);
            $this->assertTargetIsMaintenance($target);

            $previousAssignee = $lockedTicket->assignee;

            $this->updateAssignmentFields(
                $lockedTicket,
                $target,
                $actor,
                true,
                Ticket::ASSIGNMENT_SOURCE_ADMIN
            );

            $this->recordAssignmentHistory(
                $lockedTicket,
                $actor,
                $previousAssignee,
                $target,
                $action
            );

            return $lockedTicket;
        });

        $this->dispatchAssignmentEvent($action, $updatedTicket, $actor, $previousAssignee, $target, $correlationId);

        $this->logger->info('ticket.assignment.'.$action, [
            'ticket_id' => $updatedTicket->id,
            'location_id' => $updatedTicket->location_id,
            'category_id' => $updatedTicket->category_id,
            'actor_id' => $actor->id,
            'assigned_to' => $target->id,
            'correlation_id' => $correlationId,
            'state' => $updatedTicket->state,
        ]);

        return $this->refreshTicket($updatedTicket);
    }

    private function updateAssignmentFields(
        Ticket $ticket,
        ?User $assignee,
        User $actor,
        bool $locked,
        ?string $source
    ): void {
        $ticket->assigned_to = $assignee?->id;
        $ticket->assigned_by = $actor->id;
        $ticket->assigned_at = now();
        $ticket->assignment_locked = $locked;
        $ticket->assignment_source = $source;
        $ticket->save();
    }

    private function assertClaimable(Ticket $ticket): void
    {
        if ($ticket->state !== Ticket::STATE_OPEN) {
            throw new InvalidArgumentException('Solo se pueden tomar tickets en estado open.');
        }

        if ($ticket->assigned_to !== null) {
            throw new InvalidArgumentException('El ticket ya tiene un responsable asignado.');
        }

        if ($ticket->assignment_locked) {
            throw new InvalidArgumentException('El ticket tiene asignacion fija y no puede ser tomado.');
        }
    }

    private function assertReleasableByMaintenance(Ticket $ticket, User $actor): void
    {
        $this->assertActorIsMaintenance($actor);

        if ($ticket->state !== Ticket::STATE_OPEN) {
            throw new InvalidArgumentException('Solo se pueden liberar tickets en estado open.');
        }

        if ($ticket->assigned_to !== $actor->id) {
            throw new InvalidArgumentException('Solo el responsable actual puede liberar el ticket.');
        }

        if ($ticket->assignment_locked) {
            throw new InvalidArgumentException('No se puede liberar una asignacion fija por administracion.');
        }

        if ($ticket->assignment_source !== Ticket::ASSIGNMENT_SOURCE_SELF) {
            throw new InvalidArgumentException('Solo se pueden liberar tickets tomados por maintenance.');
        }
    }

    private function assertAssignable(Ticket $ticket): void
    {
        if (! in_array($ticket->state, [Ticket::STATE_OPEN, Ticket::STATE_IN_PROGRESS], true)) {
            throw new InvalidArgumentException('Solo se pueden asignar tickets abiertos o en progreso.');
        }
    }

    private function assertActorCanManageAssignment(User $actor): void
    {
        if (! method_exists($actor, 'hasAnyRole')) {
            throw new AuthorizationException('No se puede validar roles para la asignacion solicitada.');
        }

        if (! $actor->hasAnyRole(['admin', 'super_admin'])) {
            throw new AuthorizationException('Solo admin/super_admin pueden gestionar asignaciones.');
        }
    }

    private function assertActorIsMaintenance(User $actor): void
    {
        if (! method_exists($actor, 'hasRole')) {
            throw new AuthorizationException('No se puede validar roles para la asignacion solicitada.');
        }

        if (! $actor->hasRole('maintenance')) {
            throw new AuthorizationException('Solo maintenance puede tomar o liberar tickets.');
        }
    }

    private function assertTargetIsMaintenance(User $target): void
    {
        if (! method_exists($target, 'hasRole')) {
            throw new InvalidArgumentException('No se puede validar el rol del usuario asignado.');
        }

        if (! $target->hasRole('maintenance')) {
            throw new InvalidArgumentException('El usuario asignado debe tener rol maintenance.');
        }
    }

    private function recordAssignmentHistory(
        Ticket $ticket,
        User $actor,
        ?User $from,
        ?User $to,
        string $action
    ): void {
        $comment = match ($action) {
            'claimed' => 'Ticket tomado por maintenance: '.$this->resolveUserLabel($actor),
            'released' => 'Ticket liberado por maintenance: '.$this->resolveUserLabel($actor),
            'assigned' => 'Ticket asignado a '.$this->resolveUserLabel($to).' por admin/super_admin: '.$this->resolveUserLabel($actor),
            'reassigned' => 'Ticket reasignado de '.$this->resolveUserLabel($from).' a '.$this->resolveUserLabel($to).' por admin/super_admin: '.$this->resolveUserLabel($actor),
            'unassigned' => 'Ticket desasignado por admin/super_admin: '.$this->resolveUserLabel($actor).'. Responsable anterior: '.$this->resolveUserLabel($from),
            default => 'Actualizacion de asignacion por '.$this->resolveUserLabel($actor),
        };

        StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => $ticket->state,
            'to_state' => $ticket->state,
            'changed_by' => $actor->id,
            'comment' => $comment,
        ]);
    }

    private function dispatchAssignmentEvent(
        string $action,
        Ticket $ticket,
        User $actor,
        ?User $previousAssignee,
        ?User $newAssignee,
        string $correlationId
    ): void {
        if (! class_exists(TicketAssigned::class)) {
            return;
        }

        event(new TicketAssigned(
            ticket: $ticket,
            actor: $actor,
            previousAssignee: $previousAssignee,
            newAssignee: $newAssignee,
            action: $action,
            correlationId: $correlationId,
        ));
    }

    private function refreshTicket(Ticket $ticket): Ticket
    {
        return $ticket->fresh(['reporter', 'assignee', 'assignedBy', 'location', 'category', 'stateHistory'])
            ?? $ticket;
    }

    private function resolveUserLabel(?User $user): string
    {
        $label = 'Sin asignar';

        if ($user !== null) {
            $name = trim((string) $user->name.' '.(string) $user->last_name);
            if ($name !== '') {
                $label = $name;
            } elseif ((string) $user->email !== '') {
                $label = (string) $user->email;
            } else {
                $label = (string) $user->id;
            }
        }

        return $label;
    }

    private function resolveCorrelationId(string $correlationId): string
    {
        $resolved = trim($correlationId);

        if ($resolved === '' && app()->bound('request')) {
            $request = request();
            if ($request instanceof Request) {
                $fromAttribute = trim((string) $request->attributes->get('correlation_id', ''));
                if ($fromAttribute !== '') {
                    $resolved = $fromAttribute;
                } else {
                    $fromHeader = trim((string) $request->headers->get('X-Correlation-Id', ''));
                    if ($fromHeader !== '') {
                        $request->attributes->set('correlation_id', $fromHeader);
                        $resolved = $fromHeader;
                    }
                }
            }
        }

        if ($resolved === '') {
            $resolved = (string) Str::uuid();
        }

        return $resolved;
    }
}
