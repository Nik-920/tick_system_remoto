<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WriteAiAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $message,
        public array $context = [],
        public ?Ticket $ticket = null,
        public ?string $operationType = null
    ) {
    }

    public function handle(TicketQrLogger $logger): void
    {
        $context = $this->context;

        if ($this->ticket) {
            $context['ticket_id'] = $this->ticket->id;
            $context['location_id'] = $this->ticket->location_id;
            $context['category_id'] = $this->ticket->category_id;
        }

        if ($this->operationType !== null && $this->operationType !== '') {
            $context['operation_type'] = $this->operationType;
        }

        $eventName = 'ticket.ai.audit';
        if ($this->operationType !== null && $this->operationType !== '') {
            $eventName = 'ticket.ai.' . str_replace('_', '.', $this->operationType);
        }

        $context['audit_message'] = $this->message;

        $logger->info($eventName, $context);
    }
}
