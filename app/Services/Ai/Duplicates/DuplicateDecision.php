<?php

namespace App\Services\Ai\Duplicates;

/**
 * Aggregated decision produced by DuplicateDetectionEngine after
 * running all registered strategies.
 *
 * This object carries full traceability: score, individual results,
 * reasons and whether the candidate is a duplicate or a probable recurrence.
 */
final class DuplicateDecision
{
    /**
     * @param  array<int, DuplicateStrategyResult>  $results  Ordered list of individual strategy results.
     * @param  array<int, string>  $reasons  Human-readable reasons collected from results.
     * @param  array<string, mixed>  $metadata  Aggregated metadata for audit/logging.
     */
    private function __construct(
        public readonly bool $isDuplicate,
        public readonly bool $isRecurrence,
        public readonly int $score,
        public readonly array $results,
        public readonly array $reasons,
        public readonly array $metadata,
    ) {}

    // ── Factory ──────────────────────────────────────────────────────────────

    /**
     * Build a DuplicateDecision from the list of strategy results.
     *
     * Rules applied (in order):
     *  1. Sum all points to produce total score.
     *  2. If ANY result has blocksDuplicate = true → isDuplicate = false.
     *  3. If score >= duplicateThreshold AND not blocked → isDuplicate = true.
     *  4. If not a duplicate AND any result has suggestsRecurrence = true
     *     AND score >= recurrenceThreshold → isRecurrence = true.
     *
     * @param  array<int, DuplicateStrategyResult>  $results
     */
    public static function fromResults(
        array $results,
        int $duplicateThreshold,
        int $recurrenceThreshold = 0,
    ): self {
        $score = 0;
        $blocked = false;
        $recurrenceHinted = false;
        $reasons = [];
        $metadata = [];

        foreach ($results as $result) {
            $score += $result->points;

            if ($result->blocksDuplicate) {
                $blocked = true;
            }

            if ($result->suggestsRecurrence) {
                $recurrenceHinted = true;
            }

            if ($result->reason !== '') {
                $reasons[] = "[{$result->strategy}] {$result->reason}";
            }

            if ($result->metadata !== []) {
                $metadata[$result->strategy] = $result->metadata;
            }
        }

        $isDuplicate = ! $blocked && $score >= $duplicateThreshold;
        $isRecurrence = ! $isDuplicate && $recurrenceHinted && $score >= $recurrenceThreshold;

        $metadata['_summary'] = [
            'score' => $score,
            'threshold' => $duplicateThreshold,
            'recurrence_threshold' => $recurrenceThreshold,
            'blocked' => $blocked,
            'is_duplicate' => $isDuplicate,
            'is_recurrence' => $isRecurrence,
            'strategies_evaluated' => count($results),
        ];

        return new self(
            isDuplicate: $isDuplicate,
            isRecurrence: $isRecurrence,
            score: $score,
            results: $results,
            reasons: $reasons,
            metadata: $metadata,
        );
    }

    // ── Persistence / explainability ─────────────────────────────────────────

    /**
     * Serialise each strategy result into a portable array, suitable for
     * persisting in a json column (ticket_embeddings.strategy_results) and
     * later rendering a human explanation in the ticket detail screen.
     *
     * Only results that actually contributed a signal are kept (non-zero
     * points, a hard block, or a recurrence hint) so the stored payload stays
     * compact and meaningful. No-op Phase-3 strategies are dropped.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resultsForPersistence(): array
    {
        $serialised = [];

        foreach ($this->results as $result) {
            if ($result->points === 0 && ! $result->blocksDuplicate && ! $result->suggestsRecurrence) {
                continue;
            }

            $serialised[] = [
                'strategy' => $result->strategy,
                'points' => $result->points,
                'reason' => $result->reason,
                'metadata' => $result->metadata,
                'blocksDuplicate' => $result->blocksDuplicate,
                'suggestsRecurrence' => $result->suggestsRecurrence,
            ];
        }

        return $serialised;
    }

    /**
     * Build the aggregated summary persisted in
     * ticket_embeddings.strategy_metadata. The legacy gate decision is recorded
     * alongside the Strategy decision so the audit trail keeps both sources.
     *
     * @return array<string, mixed>
     */
    public function summaryMetadata(bool $legacyIsDuplicate): array
    {
        return [
            'decision_source' => 'legacy_with_strategy_metadata',
            'legacy_is_duplicate' => $legacyIsDuplicate,
            'strategy_is_duplicate' => $this->isDuplicate,
            'strategy_is_recurrence' => $this->isRecurrence,
            'score' => $this->score,
            'score_threshold' => $this->metadata['_summary']['threshold'] ?? null,
            'top_reasons' => $this->topPositiveStrategies(),
        ];
    }

    /**
     * Strategy identifiers of the top positive-scoring results, ordered by
     * points descending. Used for quick audit summaries (the UI presenter does
     * the full human-readable mapping from the per-strategy results).
     *
     * @return array<int, string>
     */
    public function topPositiveStrategies(int $limit = 3): array
    {
        $positive = array_filter($this->results, static fn ($result): bool => $result->points > 0);

        usort($positive, static fn ($a, $b): int => $b->points <=> $a->points);

        return array_values(array_map(
            static fn ($result): string => $result->strategy,
            array_slice($positive, 0, $limit),
        ));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Return a flat array suitable for logging/auditing.
     *
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        return [
            'strategy_score' => $this->score,
            'is_duplicate' => $this->isDuplicate,
            'is_recurrence' => $this->isRecurrence,
            'reasons' => $this->reasons,
            'strategies_metadata' => $this->metadata,
        ];
    }
}
