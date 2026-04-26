<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Services\Ai\EmbeddingService;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Throwable;

class GenerateTicketEmbedding implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public Ticket $ticket, public string $correlationId = '')
    {
    }

    public function handle(EmbeddingService $embeddings, TicketQrLogger $logger): void
    {
        if (! (bool) config('ai.enabled') || ! (bool) config('ai.huggingface.enabled')) {
            return;
        }

        $description = trim((string) $this->ticket->description);
        if ($description === '') {
            return;
        }

        $hash = hash('sha256', $description);
        $existing = TicketEmbedding::where('ticket_id', $this->ticket->id)->first();
        if ($existing && $existing->description_hash === $hash && is_array($existing->embedding_vector)) {
            return;
        }

        try {
            $vector = $embeddings->generate($description);
        } catch (Throwable $exception) {
            $context = [
                'ticket_id' => $this->ticket->id,
                'location_id' => $this->ticket->location_id,
                'category_id' => $this->ticket->category_id,
                'correlation_id' => $this->correlationId,
                'operation_type' => 'embedding_generation',
                'exception_class' => $exception::class,
                'error_message' => Str::limit($exception->getMessage(), 500, ''),
            ];

            $logger->warning('ticket.embedding.generation_failed', $context);
            $this->reportToSentry($exception, $context);

            return;
        }

        TicketEmbedding::updateOrCreate(
            ['ticket_id' => $this->ticket->id],
            [
                'embedding_vector' => $vector,
                'description_hash' => $hash,
                'similarity_score' => null,
                'matched_ticket_id' => null,
                'is_duplicate' => false,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reportToSentry(Throwable $exception, array $context): void
    {
        \Sentry\withScope(function (Scope $scope) use ($context): void {
            $scope->setTag('domain', 'ticket');
            $scope->setTag('operation_type', 'embedding_generation');

            if ($this->correlationId !== '') {
                $scope->setTag('correlation_id', $this->correlationId);
            }

            $scope->setContext('ticket_job', $context);
        });

        \Sentry\captureException($exception);
    }
}
