<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Jobs\GenerateTicketEmbedding;

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
