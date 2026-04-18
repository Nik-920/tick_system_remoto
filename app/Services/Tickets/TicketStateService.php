<?php

namespace App\Services\Tickets;

use App\Events\TicketResolved;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketStateService
{
    public function __construct(private TicketQrLogger $logger)
    {
    }

    /**
     * @param string|null $comment
     */
    public function transition(Ticket $ticket, User $actor, string $toState, ?string $comment = null): Ticket
    {
        $fromState = (string) $ticket->state;

        if ($fromState === $toState) {
            $this->logger->info('ticket.state.no_change', [
                'ticket_id' => $ticket->id,
                'location_id' => $ticket->location_id,
                'category_id' => $ticket->category_id,
                'actor_id' => $actor->id,
                'from_state' => $fromState,
                'to_state' => $toState,
            ]);

            return $ticket;
        }

        $this->assertTransitionIsAllowed($fromState, $toState);
        $this->assertRoleCanTransition($actor, $fromState, $toState);
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

        $event = TicketResolved::forTicket($updatedTicket);
        if ($event !== null) {
            event($event);
        }

        $this->logger->info('ticket.state.transitioned', [
            'ticket_id' => $updatedTicket->id,
            'location_id' => $updatedTicket->location_id,
            'category_id' => $updatedTicket->category_id,
            'actor_id' => $actor->id,
            'from_state' => $fromState,
            'to_state' => $toState,
            'comment' => $comment,
        ]);

        return $updatedTicket->fresh(['reporter', 'assignee', 'location', 'category', 'stateHistory']) ?? $updatedTicket;
    }

    private function assertTransitionIsAllowed(string $fromState, string $toState): void
    {
        $allowedTransitions = [
            'open' => ['in_progress'],
            'in_progress' => ['resolved', 'rejected'],
            'rejected' => ['open'],
            'resolved' => ['open'],
        ];

        $allowed = $allowedTransitions[$fromState] ?? [];
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

    private function assertRoleCanTransition(User $actor, string $fromState, string $toState): void
    {
        if (! method_exists($actor, 'hasAnyRole') || ! method_exists($actor, 'hasRole')) {
            throw new InvalidArgumentException('No se puede validar roles para la transicion solicitada.');
        }

        if ($fromState === 'open' && $toState === 'in_progress' && ! $actor->hasAnyRole(['maintenance', 'admin', 'super_admin'])) {
            throw new InvalidArgumentException('Solo maintenance/admin/super_admin pueden tomar tickets.');
        }

        if ($fromState === 'in_progress' && $toState === 'resolved' && ! $actor->hasAnyRole(['maintenance', 'admin', 'super_admin'])) {
            throw new InvalidArgumentException('Solo maintenance/admin/super_admin pueden resolver tickets.');
        }

        if ($fromState === 'in_progress' && $toState === 'rejected' && ! $actor->hasAnyRole(['admin', 'super_admin'])) {
            throw new InvalidArgumentException('Solo admin/super_admin pueden rechazar tickets.');
        }

        if ($fromState === 'rejected' && $toState === 'open' && ! $actor->hasAnyRole(['admin', 'super_admin'])) {
            throw new InvalidArgumentException('Solo admin/super_admin pueden reabrir tickets rechazados.');
        }

        if ($fromState === 'resolved' && $toState === 'open' && ! $actor->hasRole('super_admin')) {
            throw new InvalidArgumentException('Solo super_admin puede reabrir tickets resueltos.');
        }
    }
}
