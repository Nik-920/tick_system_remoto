<?php

namespace App\Contracts\Ai\Duplicates;

use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy Pattern — Target interface for duplicate detection rules.
 *
 * Each concrete strategy encapsulates one scoring rule.
 * The engine iterates over all registered strategies and aggregates results.
 */
interface DuplicateDetectionStrategy
{
    /**
     * Evaluate a duplicate candidate and return a scored result.
     *
     * Implementations MUST:
     * - Return a DuplicateStrategyResult with a meaningful reason.
     * - Never throw exceptions; return 0 points if data is missing.
     * - Not persist any state or dispatch events.
     */
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult;
}
