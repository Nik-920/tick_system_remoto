<?php

namespace App\Services\Tickets;

use App\Events\TicketResolved;
use App\Events\TicketStateChanged;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TicketStateService
{
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

        $event = TicketResolved::forTicket($updatedTicket, $correlationId);
        if ($event !== null) {
            event($event);
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

    private function assertRoleCanTransition(User $actor, Ticket $ticket, string $fromState, string $toState): void
    {
        if (! method_exists($actor, 'hasAnyRole') || ! method_exists($actor, 'hasRole')) {
            throw new InvalidArgumentException('No se puede validar roles para la transicion solicitada.');
        }

        // Defensa en profundidad: maintenance solo puede transicionar tickets asignados a él.
        // La policy ya bloquea en la capa HTTP; este guard protege llamadas directas al servicio.
        if ($actor->hasRole('maintenance') && ! $actor->hasAnyRole(['admin', 'super_admin'])) {
            if ($ticket->assigned_to !== $actor->id) {
                throw new InvalidArgumentException(
                    'El técnico solo puede cambiar estado de tickets asignados a él.'
                );
            }
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

    /**
     * Returns the list of states that $actor is allowed to transition $ticket to.
     * Pure read — no side effects, no exceptions thrown.
     *
     * @return list<string>
     */
    public function availableTransitionsFor(Ticket $ticket, User $actor): array
    {
        if (! method_exists($actor, 'hasRole') || ! method_exists($actor, 'hasAnyRole')) {
            return [];
        }

        $fromState = (string) $ticket->state;

        $allowedTransitions = [
            'open' => ['in_progress'],
            'in_progress' => ['resolved', 'rejected'],
            'rejected' => ['open'],
            'resolved' => ['open'],
        ];

        $candidates = $allowedTransitions[$fromState] ?? [];

        if (empty($candidates)) {
            return [];
        }

        $isMaintenance = $actor->hasRole('maintenance') && ! $actor->hasAnyRole(['admin', 'super_admin']);
        $isAdminOrAbove = $actor->hasAnyRole(['admin', 'super_admin']);

        if ($isMaintenance) {
            // Ownership guard: maintenance can only act on their own tickets.
            if ($ticket->assigned_to !== $actor->id) {
                return [];
            }

            // Maintenance can only: open→in_progress and in_progress→resolved.
            return array_values(array_filter($candidates, fn (string $s) => in_array($s, ['in_progress', 'resolved'], true)));
        }

        if ($isAdminOrAbove) {
            // super_admin can reopen resolved tickets; regular admin cannot.
            if ($fromState === 'resolved') {
                return $actor->hasRole('super_admin') ? ['open'] : [];
            }

            return $candidates;
        }

        // Reporters and unknown roles: no transitions.
        return [];
    }

    private function resolveCorrelationId(string $correlationId): string
    {
        $trimmed = trim($correlationId);
        if ($trimmed !== '') {
            return $trimmed;
        }

        if (app()->bound('request')) {
            $request = request();
            if ($request instanceof Request) {
                $fromAttribute = trim((string) $request->attributes->get('correlation_id', ''));
                if ($fromAttribute !== '') {
                    return $fromAttribute;
                }

                $fromHeader = trim((string) $request->headers->get('X-Correlation-Id', ''));
                if ($fromHeader !== '') {
                    $request->attributes->set('correlation_id', $fromHeader);

                    return $fromHeader;
                }
            }
        }

        return (string) Str::uuid();
    }
}
