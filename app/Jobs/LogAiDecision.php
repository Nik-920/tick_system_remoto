<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Models\TicketAiLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogAiDecision implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $inputData
     * @param  array<string, mixed>  $outputData
     */
    public function __construct(
        public Ticket $ticket,
        public string $operationType,
        public array $inputData = [],
        public array $outputData = [],
        public ?float $confidenceScore = null,
        public ?string $actionTaken = null,
        public string $correlationId = ''
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        if (! (bool) config('ai.enabled')) {
            return;
        }

        // Idempotencia (Fase 5.1): un reintento del job (failed + retry) no debe
        // crear un segundo registro de auditoría para la misma decisión. La
        // identidad de la operación es (ticket_id, operation_type, correlation_id).
        TicketAiLog::firstOrCreate(
            [
                'ticket_id' => $this->ticket->id,
                'operation_type' => $this->operationType,
                'correlation_id' => $this->correlationId,
            ],
            [
                'input_data' => $this->inputData,
                'output_data' => $this->outputData,
                'confidence_score' => $this->confidenceScore,
                'action_taken' => $this->actionTaken,
            ]
        );
    }
}
