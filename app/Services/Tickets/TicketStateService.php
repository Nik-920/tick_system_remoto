<?php

namespace App\Services\Tickets;

use App\Events\TicketResolved;
use App\Events\TicketStateChanged;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Concerns\ResolvesCorrelationId;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketStateService
{
    use ResolvesCorrelationId;

    public function __construct(private TicketQrLogger $logger) {}

    public function transition(
        Ticket $ticket,
        User $actor,
        string $toState,
        ?string $comment = null,
        string $correlationId = ''
    ): Ticket {
        $correlationId = $this->resolveCorrelationId($correlationId);

        $fromState = (string) $ticket->state;

        if ($fromState === $toState) {
            $this->logger->info('ticket.state.no_change', [
                'ticket_id' => $ticket->id,
                'location_id' => $ticket->location_id,
                'category_id' => $ticket->category_id,
                'actor_id' => $actor->id,
                'correlation_id' => $correlationId,
                'from_state' => $fromState,
                'to_state' => $toState,
            ]);

            return $ticket;
        }

        $this->assertTransitionIsAllowed($fromState, $toState);
        $this->assertRoleCanTransition($actor, $ticket, $fromState, $toState);
        $this->assertCommentIsValid($fromState, $toState, $comment);

        $updatedTicket = DB::transaction(function () use ($ticket, $actor, $toState, $comment, $fromState): Ticket {
            $ticket->state = $toState;
            if ($toState === 'resolved') {
                $ticket->resolved_at = now();
            } elseif ($fromState === 'resolved' && $toState === 'open') {
                $ticket->resolved_at = null;
            }

            $ticket->save();

            StateHistory::create([
                'ticket_id' => $ticket->id,
                'from_state' => $fromState,
                'to_state' => $toState,
                'changed_by' => $actor->id,
                'comment' => $comment,
            ]);

            return $ticket;
        });

        if ($toState === 'resolved') {
            event(new TicketResolved($updatedTicket, $correlationId));
        }

        event(new TicketStateChanged(
            ticket: $updatedTicket,
            actor: $actor,
            fromState: $fromState,
            toState: $toState,
            correlationId: $correlationId,
        ));

        $this->logger->info('ticket.state.transitioned', [
            'ticket_id' => $updatedTicket->id,
            'location_id' => $updatedTicket->location_id,
            'category_id' => $updatedTicket->category_id,
            'actor_id' => $actor->id,
            'correlation_id' => $correlationId,
            'from_state' => $fromState,
            'to_state' => $toState,
            'comment' => $comment,
        ]);

        return $updatedTicket->fresh(['reporter', 'assignee', 'location', 'category', 'stateHistory']) ?? $updatedTicket;
    }

    private function assertTransitionIsAllowed(string $fromState, string $toState): void
    {
        $allowed = $this->allowedTransitions()[$fromState] ?? [];
        if (! in_array($toState, $allowed, true)) {
            throw new InvalidArgumentException('Transicion de estado no permitida.');
        }
    }

    private function assertCommentIsValid(string $fromState, string $toState, ?string $comment): void
    {
        $requiresComment = $toState === 'resolved'
            || $toState === 'rejected'
            || ($fromState === 'resolved' && $toState === 'open')
            || ($fromState === 'rejected' && $toState === 'open');

        if ($requiresComment && trim((string) $comment) === '') {
            throw new InvalidArgumentException('El comentario es obligatorio para esta transicion.');
        }
    }

    private function assertRoleCanTransition(User $actor, Ticket $ticket, string $fromState, string $toState): void
    {
        if (! method_exists($actor, 'hasAnyRole') || ! method_exists($actor, 'hasRole')) {
            throw new InvalidArgumentException('No se puede validar roles para la transicion solicitada.');
        }

        // Defensa en profundidad: maintenance solo puede transicionar tickets asignados a él.
        // La policy ya bloquea en la capa HTTP; este guard protege llamadas directas al servicio.
        if ($actor->hasRole('maintenance')
            && ! $actor->hasAnyRole(['admin', 'super_admin'])
            && $ticket->assigned_to !== $actor->id) {
            throw new InvalidArgumentException(
                'El técnico solo puede cambiar estado de tickets asignados a él.'
            );
        }

        if (! $this->roleCanDoTransition($actor, $ticket, $fromState, $toState)) {
            throw new InvalidArgumentException(
                "El usuario no tiene permisos para la transición {$fromState} → {$toState}."
            );
        }
    }

    /**
     * Single source of truth: determines if $actor can transition $ticket
     * from $fromState to $toState based on role rules.
     *
     * Used by both assertRoleCanTransition() and availableTransitionsFor()
     * to guarantee they never diverge.
     */
    private function roleCanDoTransition(User $actor, Ticket $ticket, string $fromState, string $toState): bool
    {
        $isAdminOrAbove = $actor->hasAnyRole(['admin', 'super_admin']);
        $isMaintenance = $actor->hasRole('maintenance') && ! $isAdminOrAbove;

        if ($isMaintenance) {
            // Ownership guard + maintenance can only: open→in_progress
            // and in_progress→resolved.
            $allowed = $ticket->assigned_to === $actor->id
                && (($fromState === 'open' && $toState === 'in_progress')
                    || ($fromState === 'in_progress' && $toState === 'resolved'));
        } elseif ($isAdminOrAbove) {
            // resolved→open: only super_admin; admin/super_admin can do all
            // other allowed transitions.
            $allowed = ! ($fromState === 'resolved' && $toState === 'open')
                || $actor->hasRole('super_admin');
        } else {
            // Reporters and unknown roles: no transitions.
            $allowed = false;
        }

        return $allowed;
    }

    /**
     * Returns the list of states that $actor is allowed to transition $ticket to.
     * Pure read — no side effects, no exceptions thrown.
     *
     * Uses the same roleCanDoTransition() as assertRoleCanTransition() to
     * guarantee parity between what the UI shows and what the backend accepts.
     *
     * @return list<string>
     */
    public function availableTransitionsFor(Ticket $ticket, User $actor): array
    {
        if (! method_exists($actor, 'hasRole') || ! method_exists($actor, 'hasAnyRole')) {
            return [];
        }

        $fromState = (string) $ticket->state;
        $candidates = $this->allowedTransitions()[$fromState] ?? [];

        if (empty($candidates)) {
            return [];
        }

        return array_values(array_filter(
            $candidates,
            fn (string $toState): bool => $this->roleCanDoTransition($actor, $ticket, $fromState, $toState)
        ));
    }

    /**
     * State machine: which transitions are structurally valid regardless of role.
     *
     * @return array<string, list<string>>
     */
    private function allowedTransitions(): array
    {
        return [
            'open' => ['in_progress'],
            'in_progress' => ['resolved', 'rejected'],
            'rejected' => ['open'],
            'resolved' => ['open'],
        ];
    }
}
