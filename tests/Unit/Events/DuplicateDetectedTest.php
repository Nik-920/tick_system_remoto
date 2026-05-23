<?php

namespace Tests\Unit\Events;

use App\Events\DuplicateDetected;
use App\Models\Ticket;
use Tests\TestCase;

class DuplicateDetectedTest extends TestCase
{
    public function test_event_exposes_payload(): void
    {
        $ticket = new Ticket(['id' => 'ticket-1']);
        $matched = new Ticket(['id' => 'ticket-2']);

        $event = new DuplicateDetected($ticket, $matched, 0.92, 'corr-001');

        $this->assertSame('ticket-1', $event->ticket->id);
        $this->assertSame('ticket-2', $event->matchedTicket?->id);
        $this->assertSame(0.92, $event->similarityScore);
        $this->assertSame('corr-001', $event->correlationId);
    }
}
