<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Events\TicketCreated;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Throwable;

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
