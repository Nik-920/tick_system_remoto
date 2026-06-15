<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Exceptions\TicketLockUnavailableException;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Locks\TicketLock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reporter-initiated cancellation (voluntary withdrawal) of an OWN, still-open
 * request.
 *
 * This is intentionally SEPARATE from TicketStateService: that service is the
 * maintenance/admin operational state machine (open→in_progress→resolved/…),
 * where reporters have NO transitions (and a parity test pins that down).
 * Cancellation is a distinct reporter ability — gated by TicketPolicy@cancelAsReporter —
 * with its own terminal transition open→cancelled.
 *
 * It is NOT a rejection (that is a maintenance/admin decision) and NOT a delete:
 * the ticket row is preserved; only the state changes and a state_history entry
 * is recorded so the timeline/audit keeps the full trail.
 */
class TicketCancellationService
{
    public const DEFAULT_COMMENT = 'Solicitud cancelada por el reporter.';

    /**
     * Cancel the reporter's own open/unassigned/unlocked ticket. The HTTP layer
     * already authorizes via TicketPolicy@cancelAsReporter; this guard is
     * defence-in-depth for direct service calls.
     */
    public function cancelByReporter(Ticket $ticket, User $reporter, ?string $comment = null): Ticket
    {
        $result = TicketLock::mutation((string) $ticket->id)->get(
            function () use ($ticket, $reporter, $comment): Ticket {
                $fromState = (string) $ticket->state;

                if ($fromState !== Ticket::STATE_OPEN
                    || $ticket->assigned_to !== null
                    || $ticket->assignment_locked) {
                    throw new InvalidArgumentException('La solicitud ya no puede cancelarse.');
                }

                $comment = trim((string) $comment);
                if ($comment === '') {
                    $comment = self::DEFAULT_COMMENT;
                }

                return DB::transaction(function () use ($ticket, $reporter, $fromState, $comment): Ticket {
                    $ticket->state = Ticket::STATE_CANCELLED;
                    $ticket->save();

                    StateHistory::create([
                        'ticket_id' => $ticket->id,
                        'from_state' => $fromState,
                        'to_state' => Ticket::STATE_CANCELLED,
                        'changed_by' => $reporter->id,
                        'comment' => $comment,
                    ]);

                    return $ticket;
                });
            }
        );

        if ($result === null) {
            throw new TicketLockUnavailableException((string) $ticket->id);
        }

        return $result;
    }
}
