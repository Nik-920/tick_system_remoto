<?php

namespace App\Services\Ai\Duplicates;

/**
 * Immutable result produced by one concrete Strategy.
 *
 * Points can be positive (evidence of duplication) or negative (counter-evidence).
 * The engine sums all points to compute a total score.
 */
final readonly class DuplicateStrategyResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        /** Short identifier of the strategy class, e.g. 'embedding_similarity'. */
        public string $strategy,

        /** Points contributed by this strategy (positive or negative). */
        public int $points,

        /** Human-readable explanation for this score. */
        public string $reason,

        /** Optional key-value metadata for traceability. */
        public array $metadata = [],

        /**
         * When true, the candidate CANNOT be marked as duplicate regardless of total score.
         * Use for hard blocking signals (e.g. candidate was cancelled/rejected).
         */
        public bool $blocksDuplicate = false,

        /**
         * When true, this strategy found signals pointing to recurrence rather than
         * an exact duplicate (e.g. old candidate in same location/category).
         */
        public bool $suggestsRecurrence = false,
    ) {}
}
