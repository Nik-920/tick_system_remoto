<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Models\Ticket;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy: scores or penalises based on the current state of the candidate.
 *
 * Open/in-progress candidates are stronger duplicate signals.
 * Cancelled or rejected candidates hard-penalise or block the duplicate flag.
 *
 * Config keys (under ai.dedup.strategies.candidate_state):
 *   open           (default  20)
 *   assigned       (default  15) — open + assigned_to set
 *   in_progress    (default  10)
 *   resolved_recent (default  5)
 *   cancelled      (default -30)
 *   rejected       (default -20)
 */
final class CandidateStateStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.candidate_state', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'candidate_state',
                points: 0,
                reason: 'Candidate state strategy disabled.',
            );
        }

        $state = (string) ($context->candidate->state ?? '');
        $metadata = ['candidate_state' => $state];

        $openPoints = (int) ($cfg['open'] ?? 20);
        $assignedPoints = (int) ($cfg['assigned'] ?? 15);
        $inProgressPoints = (int) ($cfg['in_progress'] ?? 10);
        $resolvedRecentPoints = (int) ($cfg['resolved_recent'] ?? 5);
        $cancelledPoints = (int) ($cfg['cancelled'] ?? -30);
        $rejectedPoints = (int) ($cfg['rejected'] ?? -20);

        return match ($state) {
            Ticket::STATE_OPEN => new DuplicateStrategyResult(
                strategy: 'candidate_state',
                points: $openPoints,
                reason: 'Candidate is open — strong duplicate signal.',
                metadata: $metadata,
            ),
            Ticket::STATE_IN_PROGRESS => new DuplicateStrategyResult(
                strategy: 'candidate_state',
                points: $inProgressPoints,
                reason: 'Candidate is in progress.',
                metadata: $metadata,
            ),
            Ticket::STATE_RESOLVED => new DuplicateStrategyResult(
                strategy: 'candidate_state',
                points: $resolvedRecentPoints,
                reason: 'Candidate is resolved (recently).',
                metadata: $metadata,
            ),
            Ticket::STATE_CANCELLED => new DuplicateStrategyResult(
                strategy: 'candidate_state',
                points: $cancelledPoints,
                reason: 'Candidate was cancelled — strong counter-evidence.',
                metadata: $metadata,
            ),
            Ticket::STATE_REJECTED => new DuplicateStrategyResult(
                strategy: 'candidate_state',
                points: $rejectedPoints,
                reason: 'Candidate was rejected — counter-evidence.',
                metadata: $metadata,
            ),
            default => new DuplicateStrategyResult(
                strategy: 'candidate_state',
                points: 0,
                reason: "Unknown candidate state '{$state}'.",
                metadata: $metadata,
            ),
        };
    }
}
