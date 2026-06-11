<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy: bonus when the candidate ticket already has an active assignment.
 *
 * A candidate that is actively assigned (assigned_to set) or assignment-locked
 * is a stronger duplicate signal because it's being actively worked on.
 *
 * Uses Eloquent getAttribute() safely — no schema queries or column assertions.
 *
 * Config keys (under ai.dedup.strategies.active_assignment):
 *   assigned_bonus (default 15) — candidate has assigned_to set
 *   locked_bonus   (default 15) — candidate has assignment_locked = true
 */
final class ActiveAssignmentStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.active_assignment', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'active_assignment',
                points: 0,
                reason: 'Active assignment strategy disabled.',
            );
        }

        $candidate = $context->candidate;

        // Use getAttribute() so we don't break if column name differs.
        $assignedTo = $candidate->getAttribute('assigned_to');
        $locked = (bool) $candidate->getAttribute('assignment_locked');

        $assignedBonus = (int) ($cfg['assigned_bonus'] ?? 15);
        $lockedBonus = (int) ($cfg['locked_bonus'] ?? 15);

        $points = 0;
        $reasons = [];

        if ($assignedTo !== null && $assignedTo !== '') {
            $points += $assignedBonus;
            $reasons[] = 'Candidate has an active assignee.';
        }

        if ($locked) {
            $points += $lockedBonus;
            $reasons[] = 'Candidate assignment is locked.';
        }

        $metadata = [
            'assigned_to_set' => ($assignedTo !== null && $assignedTo !== ''),
            'assignment_locked' => $locked,
            'points' => $points,
        ];

        return new DuplicateStrategyResult(
            strategy: 'active_assignment',
            points: $points,
            reason: $reasons !== [] ? implode(' ', $reasons) : 'Candidate has no active assignment.',
            metadata: $metadata,
        );
    }
}
