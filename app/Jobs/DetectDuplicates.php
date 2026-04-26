<?php

namespace App\Jobs;

use App\Events\DuplicateDetected;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Services\Ai\DeduplicationService;
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

class DetectDuplicates implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public Ticket $ticket, public string $correlationId = '') {}

    public function handle(
        DeduplicationService $deduplication,
        EmbeddingService $embeddings,
        TicketQrLogger $logger
    ): void {
        if (! $deduplication->isEnabled()) {
            return;
        }

        $ticket = $this->ticket;
        $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
        $vector = $embedding?->embedding_vector;

        if (! is_array($vector)) {
            $description = trim((string) $ticket->description);
            if ($description === '') {
                return;
            }

            try {
                $vector = $embeddings->generate($description);
            } catch (Throwable $exception) {
                $context = [
                    'ticket_id' => $ticket->id,
                    'location_id' => $ticket->location_id,
                    'category_id' => $ticket->category_id,
                    'correlation_id' => $this->correlationId,
                    'operation_type' => 'duplicate_detection',
                    'exception_class' => $exception::class,
                    'error_message' => Str::limit($exception->getMessage(), 500, ''),
                ];

                $logger->warning('ticket.duplicate.embedding_failed', $context);
                $this->reportToSentry($exception, $context);

                return;
            }

            $embedding = TicketEmbedding::updateOrCreate(
                ['ticket_id' => $ticket->id],
                [
                    'embedding_vector' => $vector,
                    'description_hash' => hash('sha256', $description),
                    'similarity_score' => null,
                    'matched_ticket_id' => null,
                    'is_duplicate' => false,
                ]
            );
        }

        $windowStart = now()->subHours($deduplication->windowHours());
        $candidates = TicketEmbedding::query()
            ->where('ticket_id', '!=', $ticket->id)
            ->whereHas('ticket', function ($query) use ($ticket, $windowStart): void {
                $query->where('location_id', $ticket->location_id)
                    ->where('category_id', $ticket->category_id)
                    ->whereIn('state', ['open', 'in_progress'])
                    ->where('created_at', '>=', $windowStart);
            })
            ->with('ticket')
            ->get();

        $candidateRows = [];
        foreach ($candidates as $candidate) {
            if (! is_array($candidate->embedding_vector)) {
                continue;
            }

            $candidateRows[] = [
                'ticket' => $candidate->ticket,
                'embedding' => $candidate->embedding_vector,
            ];
        }

        if ($candidateRows === []) {
            return;
        }

        $best = $deduplication->findBestMatch($vector, $candidateRows);
        if ($best === null) {
            return;
        }

        $matchedTicket = $best['ticket'] ?? null;
        $similarity = $best['similarity'] ?? null;
        $isDuplicate = (bool) ($best['is_duplicate'] ?? false);

        if ($embedding) {
            $embedding->similarity_score = is_numeric($similarity) ? (float) $similarity : null;
            $embedding->matched_ticket_id = $matchedTicket?->id;
            $embedding->is_duplicate = $isDuplicate;
            $embedding->save();
        }

        if ($isDuplicate && $matchedTicket !== null) {
            event(new DuplicateDetected(
                $ticket,
                $matchedTicket,
                is_numeric($similarity) ? (float) $similarity : null,
                $this->correlationId,
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reportToSentry(Throwable $exception, array $context): void
    {
        \Sentry\withScope(function (Scope $scope) use ($context): void {
            $scope->setTag('domain', 'ticket');
            $scope->setTag('operation_type', 'duplicate_detection');

            if ($this->correlationId !== '') {
                $scope->setTag('correlation_id', $this->correlationId);
            }

            $scope->setContext('ticket_job', $context);
        });

        \Sentry\captureException($exception);
    }
}
