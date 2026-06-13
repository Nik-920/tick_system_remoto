<?php

namespace App\Services\Ai\Duplicates;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;

/**
 * Orchestrates all registered Strategy implementations.
 *
 * Receives an iterable of DuplicateDetectionStrategy (typically injected
 * via Laravel container tag 'duplicate.detection.strategies').
 * Evaluates each strategy against the candidate context and returns
 * a DuplicateDecision with full traceability.
 *
 * This class knows NOTHING about concrete strategy implementations.
 * It depends only on the DuplicateDetectionStrategy contract.
 */
final class DuplicateDetectionEngine
{
    private int $duplicateThreshold;

    private int $recurrenceThreshold;

    /**
     * @param  iterable<DuplicateDetectionStrategy>  $strategies
     */
    public function __construct(
        private readonly iterable $strategies,
        int $duplicateThreshold = 70,
        int $recurrenceThreshold = 0,
    ) {
        $this->duplicateThreshold = $duplicateThreshold;
        $this->recurrenceThreshold = $recurrenceThreshold;
    }

    /**
     * Run all strategies against the context and produce a decision.
     */
    public function evaluate(DuplicateCandidateContext $context): DuplicateDecision
    {
        $results = [];

        foreach ($this->strategies as $strategy) {
            $results[] = $strategy->evaluate($context);
        }

        return DuplicateDecision::fromResults(
            $results,
            $this->duplicateThreshold,
            $this->recurrenceThreshold,
        );
    }

    /** Expose the configured threshold (useful for logging). */
    public function duplicateThreshold(): int
    {
        return $this->duplicateThreshold;
    }
}
