<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketAssigned
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public User $actor,
        public ?User $previousAssignee,
        public ?User $newAssignee,
        public string $action,
        public string $correlationId = ''
    ) {}
}
