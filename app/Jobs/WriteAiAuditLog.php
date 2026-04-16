<?php

namespace App\Jobs;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

    public function handle(): void
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

        Log::info($this->message, $context);
    }
}
