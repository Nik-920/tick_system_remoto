<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Events\TicketCreated;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dispatch TicketCreated after the HTTP response is sent.
 *
 * This keeps ticket creation fast and lets observers such as notifications,
 * embeddings and duplicate detection react after the ticket has been persisted.
 * The controller knows only the event, not its listeners.
 *
 * The dispatch is registered via app()->terminating() so it runs after the
 * kernel sends the response to the client, guaranteeing that the ticket
 * has been committed to the database before any listener processes it.
 */
trait DispatchesTicketCreatedAfterResponse
{
    private function dispatchAfterResponse(Ticket $ticket, string $correlationId): void
    {
        app()->terminating(function () use ($ticket, $correlationId): void {
            try {
                event(new TicketCreated($ticket, $correlationId));
            } catch (Throwable $exception) {
                Log::error('afterResponse TicketCreated failed', [
                    'ticket_id' => $ticket->id,
                    'correlation_id' => $correlationId,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    private function isDedupEnabled(): bool
    {
        return (bool) config('ai.enabled') && (bool) config('ai.dedup.enabled');
    }
}
