<?php

namespace App\Jobs;

use App\Events\DuplicateDetected;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Services\Ai\DeduplicationService;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateDecision;
use App\Services\Ai\Duplicates\DuplicateDetectionEngine;
use App\Services\Ai\EmbeddingService;
use App\Services\Observability\TicketQrLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use Throwable;

class DetectDuplicates implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(public Ticket $ticket, public string $correlationId = '')
    {
        $this->onQueue('ai');
        $this->afterCommit = true;
    }

    public function handle(
        DeduplicationService $deduplication,
        EmbeddingService $embeddings,
        TicketQrLogger $logger,
        DuplicateDetectionEngine $engine,
    ): void {
        $ticket = $this->ticket;

        try {
            if (! $deduplication->isEnabled()) {
                return;
            }

            $this->runDetection($ticket, $deduplication, $embeddings, $engine, $logger);
        } catch (Throwable $exception) {
            $context = $this->errorContext($ticket, $exception);
            $logger->error('ticket.duplicate.failed', $context);
            $this->reportToSentry($exception, $context);
        }
    }

    /**
     * Core detection flow after the enabled-gate passes.
     * Extracted to keep handle() under 3 returns (early-exit + catch).
     */
    private function runDetection(
        Ticket $ticket,
        DeduplicationService $deduplication,
        EmbeddingService $embeddings,
        DuplicateDetectionEngine $engine,
        TicketQrLogger $logger,
    ): void {
        $embedding = $this->resolveEmbedding($ticket, $embeddings, $logger);
        if ($embedding === null) {
            return;
        }

        $candidates = $this->fetchCandidates($ticket, $deduplication);
        if ($candidates === []) {
            $this->resetEmbeddingMatch($embedding);

            return;
        }

        $best = $deduplication->findBestMatch($embedding->embedding_vector, $candidates);
        $matchedTicket = $best['ticket'] ?? null;
        $similarity = $best['similarity'] ?? null;

        if ($best === null || ! $matchedTicket || ! is_numeric($similarity)) {
            $this->resetEmbeddingMatch($embedding);

            return;
        }

        $this->processBestMatch($ticket, $matchedTicket, (float) $similarity, $embedding, $deduplication, $engine, $logger);
    }

    /**
     * Resolve (or generate) the embedding for the ticket.
     * Returns null when processing should be aborted.
     */
    private function resolveEmbedding(
        Ticket $ticket,
        EmbeddingService $embeddings,
        TicketQrLogger $logger,
    ): ?TicketEmbedding {
        $text = $ticket->embeddingText();
        if ($text === '') {
            return null;
        }

        $hash = hash('sha256', $text);
        $existing = TicketEmbedding::where('ticket_id', $ticket->id)->first();

        if ($existing && $existing->description_hash === $hash && is_array($existing->embedding_vector)) {
            return $existing;
        }

        if (! $embeddings->isAvailable()) {
            return null;
        }

        try {
            $vector = $embeddings->generate($text);
        } catch (Throwable $exception) {
            $context = $this->errorContext($ticket, $exception);
            $logger->warning('ticket.duplicate.embedding_failed', $context);
            $this->reportToSentry($exception, $context);

            return null;
        }

        return TicketEmbedding::updateOrCreate(
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

    /**
     * @return list<array{ticket: Ticket, embedding: list<float>}>
     */
    private function fetchCandidates(Ticket $ticket, DeduplicationService $deduplication): array
    {
        $windowStart = now()->subHours($deduplication->windowHours());

        $rows = TicketEmbedding::query()
            ->where('ticket_id', '!=', $ticket->id)
            ->whereHas('ticket', function ($query) use ($ticket, $windowStart): void {
                $query->where('location_id', $ticket->location_id)
                    ->where('category_id', $ticket->category_id)
                    ->whereIn('state', ['open', 'in_progress'])
                    ->where('created_at', '>=', $windowStart);
            })
            ->with('ticket')
            ->get();

        $candidates = [];
        foreach ($rows as $candidate) {
            if (! is_array($candidate->embedding_vector)) {
                continue;
            }

            $candidates[] = [
                'ticket' => $candidate->ticket,
                'embedding' => $candidate->embedding_vector,
            ];
        }

        return $candidates;
    }

    /**
     * Evaluate the best match, persist results, and dispatch events.
     */
    private function processBestMatch(
        Ticket $ticket,
        Ticket $matchedTicket,
        float $similarity,
        TicketEmbedding $embedding,
        DeduplicationService $deduplication,
        DuplicateDetectionEngine $engine,
        TicketQrLogger $logger,
    ): void {
        $titleAligned = $deduplication->titleOverlapSatisfied($ticket->title ?? '', $matchedTicket->title ?? '');
        $isDuplicate = $deduplication->isStrongDuplicate($similarity, $titleAligned);
        $isObserved = $deduplication->isObservationCandidate($similarity, $titleAligned);

        // Strategy engine: parallel scoring for explainability.
        // Does NOT override the legacy isDuplicate gate — enriches the audit trail.
        $strategyDecision = $this->runStrategyEngine($ticket, $matchedTicket, $similarity, $engine, $logger);

        $this->persistMatchResult($embedding, $matchedTicket, $similarity, $isDuplicate, $isObserved, $strategyDecision);

        $this->logObservationIfNeeded($ticket, $matchedTicket, $similarity, $deduplication, $strategyDecision, $logger);

        if ($isDuplicate) {
            $this->dispatchDuplicateDetected($ticket, $matchedTicket, $similarity, $strategyDecision, $logger);
        }
    }

    private function runStrategyEngine(
        Ticket $ticket,
        Ticket $matchedTicket,
        float $similarity,
        DuplicateDetectionEngine $engine,
        TicketQrLogger $logger,
    ): ?DuplicateDecision {
        try {
            $context = new DuplicateCandidateContext(
                ticket: $ticket,
                candidate: $matchedTicket,
                now: Carbon::now(),
                embeddingSimilarity: $similarity,
            );

            return $engine->evaluate($context);
        } catch (Throwable $strategyException) {
            // Strategy engine failure must NEVER break the main flow.
            $logger->warning('ticket.duplicate.strategy_engine_failed', [
                'ticket_id' => $ticket->id,
                'correlation_id' => $this->correlationId,
                'error_message' => Str::limit($strategyException->getMessage(), 300, ''),
            ]);

            return null;
        }
    }

    private function persistMatchResult(
        TicketEmbedding $embedding,
        Ticket $matchedTicket,
        float $similarity,
        bool $isDuplicate,
        bool $isObserved,
        ?DuplicateDecision $strategyDecision,
    ): void {
        if ($isDuplicate || $isObserved) {
            $embedding->similarity_score = $similarity;
            $embedding->matched_ticket_id = $matchedTicket->id;
            $embedding->is_duplicate = $isDuplicate;

            if ($strategyDecision !== null) {
                $embedding->strategy_score = $strategyDecision->score;
                $embedding->strategy_results = $strategyDecision->resultsForPersistence();
                $embedding->strategy_metadata = $strategyDecision->summaryMetadata($isDuplicate);
                $embedding->strategy_suggests_recurrence = $strategyDecision->isRecurrence;
            }
        } else {
            $embedding->similarity_score = null;
            $embedding->matched_ticket_id = null;
            $embedding->is_duplicate = false;
            $this->clearStrategyExplanation($embedding);
        }

        $embedding->save();
    }

    private function logObservationIfNeeded(
        Ticket $ticket,
        Ticket $matchedTicket,
        float $similarity,
        DeduplicationService $deduplication,
        ?DuplicateDecision $strategyDecision,
        TicketQrLogger $logger,
    ): void {
        $observationThreshold = $deduplication->observationThreshold();
        $strongThreshold = $deduplication->similarityThreshold();

        if ($similarity >= $observationThreshold && $similarity < $strongThreshold) {
            $logger->info('ticket.duplicate.observation', [
                'ticket_id' => $ticket->id,
                'matched_ticket_id' => $matchedTicket->id,
                'similarity_score' => $similarity,
                'threshold' => $strongThreshold,
                'observation_threshold' => $observationThreshold,
                'correlation_id' => $this->correlationId,
                'operation_type' => 'duplicate_detection',
                'strategy_score' => $strategyDecision?->score,
                'strategy_is_recurrence' => $strategyDecision?->isRecurrence,
            ]);
        }
    }

    private function dispatchDuplicateDetected(
        Ticket $ticket,
        Ticket $matchedTicket,
        float $similarity,
        ?DuplicateDecision $strategyDecision,
        TicketQrLogger $logger,
    ): void {
        event(new DuplicateDetected(
            $ticket,
            $matchedTicket,
            $similarity,
            $this->correlationId,
        ));

        if ($strategyDecision !== null) {
            $logger->info('ticket.duplicate.strategy_decision', array_merge(
                [
                    'ticket_id' => $ticket->id,
                    'matched_ticket_id' => $matchedTicket->id,
                    'correlation_id' => $this->correlationId,
                    'operation_type' => 'duplicate_detection',
                ],
                $strategyDecision->toLogContext()
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

    /**
     * @return array<string, mixed>
     */
    private function errorContext(Ticket $ticket, Throwable $exception): array
    {
        return [
            'ticket_id' => $ticket->id,
            'location_id' => $ticket->location_id,
            'category_id' => $ticket->category_id,
            'correlation_id' => $this->correlationId,
            'operation_type' => 'duplicate_detection',
            'exception_class' => $exception::class,
            'error_message' => Str::limit($exception->getMessage(), 500, ''),
        ];
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
        $this->clearStrategyExplanation($embedding);
        $embedding->save();
    }

    /**
     * Clear the Strategy explainability columns (AI-managed). Does not persist;
     * the caller is responsible for saving.
     */
    private function clearStrategyExplanation(TicketEmbedding $embedding): void
    {
        $embedding->strategy_score = null;
        $embedding->strategy_results = null;
        $embedding->strategy_metadata = null;
        $embedding->strategy_suggests_recurrence = false;
    }
}
