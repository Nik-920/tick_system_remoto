<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Jobs\GenerateTicketEmbedding;

/**
 * Lightweight observer for TicketCreated.
 *
 * This listener intentionally does not implement ShouldQueue because it does not
 * perform the heavy AI operation itself. It only validates feature flags and
 * dispatches GenerateTicketEmbedding as a job when async processing is enabled.
 */
class GenerateEmbeddingOnTicketCreated
{
    public function handle(TicketCreated $event): void
    {
        if (! (bool) config('ai.enabled') || ! (bool) config('ai.huggingface.enabled')) {
            return;
        }

        $ticket = $event->ticket;
        $description = trim((string) $ticket->description);
        if ($description === '') {
            return;
        }

        $correlationId = $event->correlationId;

        $async = (bool) config('ai.automation.async_processing', true);
        if ($async) {
            GenerateTicketEmbedding::dispatch($ticket, $correlationId);

            return;
        }

        GenerateTicketEmbedding::dispatchSync($ticket, $correlationId);
    }
}
