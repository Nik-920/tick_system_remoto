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
        $ticket = $this->ticket;

        try {
            if (! $deduplication->isEnabled()) {
                return;
            }

            $embedding = TicketEmbedding::where('ticket_id', $ticket->id)->first();
            $text = $ticket->embeddingText();
            if ($text === '') {
                return;
            }

            $hash = hash('sha256', $text);
            $vector = null;

            if ($embedding && $embedding->description_hash === $hash && is_array($embedding->embedding_vector)) {
                $vector = $embedding->embedding_vector;
            }

            if (! is_array($vector)) {
                if (! $embeddings->isAvailable()) {
                    return;
                }

                try {
                    $vector = $embeddings->generate($text);
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
                        'description_hash' => $hash,
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
                $this->resetEmbeddingMatch($embedding);

                return;
            }

            $best = $deduplication->findBestMatch($vector, $candidateRows);
            if ($best === null) {
                $this->resetEmbeddingMatch($embedding);

                return;
            }

            $matchedTicket = $best['ticket'] ?? null;
            $similarity = $best['similarity'] ?? null;

            if (! $matchedTicket || ! is_numeric($similarity)) {
                $this->resetEmbeddingMatch($embedding);

                return;
            }

            $similarity = (float) $similarity;
            $titleAligned = $deduplication->titleOverlapSatisfied($ticket->title ?? '', $matchedTicket->title ?? '');
            $isDuplicate = $deduplication->isStrongDuplicate($similarity, $titleAligned);
            $isObserved = $deduplication->isObservationCandidate($similarity, $titleAligned);

            if ($embedding) {
                if ($isDuplicate || $isObserved) {
                    $embedding->similarity_score = $similarity;
                    $embedding->matched_ticket_id = $matchedTicket->id;
                    $embedding->is_duplicate = $isDuplicate;
                } else {
                    $embedding->similarity_score = null;
                    $embedding->matched_ticket_id = null;
                    $embedding->is_duplicate = false;
                }

                $embedding->save();
            }

            $observationThreshold = $deduplication->observationThreshold();
            $strongThreshold = $deduplication->similarityThreshold();

            if ($similarity >= $observationThreshold && $similarity < $strongThreshold) {
                $logger->info('ticket.duplicate.observation', [
                    'ticket_id' => $ticket->id,
                    'matched_ticket_id' => $matchedTicket->id,
                    'similarity_score' => $similarity,
                    'threshold' => $strongThreshold,
                    'observation_threshold' => $observationThreshold,
                    'title_overlap' => $titleAligned,
                    'correlation_id' => $this->correlationId,
                    'operation_type' => 'duplicate_detection',
                ]);
            }

            if ($isDuplicate) {
                event(new DuplicateDetected(
                    $ticket,
                    $matchedTicket,
                    $similarity,
                    $this->correlationId,
                ));
            }
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

            $logger->error('ticket.duplicate.failed', $context);
            $this->reportToSentry($exception, $context);
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

    private function resetEmbeddingMatch(?TicketEmbedding $embedding): void
    {
        if (! $embedding) {
            return;
        }

        // Reset AI-managed columns only.
        // Human review columns (review_status, reviewed_by, reviewed_at, review_note)
        // are intentionally left untouched.
        $embedding->similarity_score = null;
        $embedding->matched_ticket_id = null;
        $embedding->is_duplicate = false;
        $embedding->save();
    }
}
