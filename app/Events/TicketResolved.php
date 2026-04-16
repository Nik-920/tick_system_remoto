<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketResolved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public static function forTicket(Ticket $ticket): ?self
    {
        if ($ticket->state === 'resolved' || $ticket->resolved_at !== null) {
            return new self($ticket);
        }

        return null;
    }
}
