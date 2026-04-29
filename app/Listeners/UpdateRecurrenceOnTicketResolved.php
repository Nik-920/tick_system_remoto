<?php

namespace App\Listeners;

use App\Events\TicketResolved;
use App\Jobs\UpdateRecurrenceHistory;

class UpdateRecurrenceOnTicketResolved
{
    public function handle(TicketResolved $event): void
    {
        if (! (bool) config('ai.recurrence.enabled')) {
            return;
        }

        $ticket = $event->ticket;
        $correlationId = $event->correlationId;
        if ($ticket->state !== 'resolved' && $ticket->resolved_at === null) {
            return;
        }

        $async = (bool) config('ai.automation.async_processing', true);
        if ($async) {
            UpdateRecurrenceHistory::dispatch($ticket, $correlationId);

            return;
        }

        UpdateRecurrenceHistory::dispatchSync($ticket, $correlationId);
    }
}
