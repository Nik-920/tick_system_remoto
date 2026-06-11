<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy: guards against misclassifying recurrent incidents as duplicates.
 *
 * When a candidate is in the same location+category with high similarity
 * but is older than the configured minimum days, it is more likely a
 * recurrence than an exact duplicate. This strategy sets suggestsRecurrence
 * and reduces the score to prevent false-positive duplicate flagging.
 *
 * Config key: ai.dedup.strategies.recurrence_guard.min_days_for_recurrence (default 30)
 */
final class RecurrenceGuardStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.recurrence_guard', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'recurrence_guard',
                points: 0,
                reason: 'Recurrence guard strategy disabled.',
            );
        }

        $ageHours = $context->candidateAgeHours;
        if ($ageHours === null) {
            return new DuplicateStrategyResult(
                strategy: 'recurrence_guard',
                points: 0,
                reason: 'Candidate age unavailable; skipping recurrence guard.',
            );
        }

        $minDays = (int) ($cfg['min_days_for_recurrence'] ?? 30);
        $minHours = $minDays * 24;

        // Only flag recurrence when location and category also match.
        $sameContext = $context->sameLocation === true && $context->sameCategory === true;

        $metadata = [
            'candidate_age_hours' => $ageHours,
            'min_hours_for_recurrence' => $minHours,
            'same_context' => $sameContext,
        ];

        if ($sameContext && $ageHours > $minHours) {
            return new DuplicateStrategyResult(
                strategy: 'recurrence_guard',
                points: -10,
                reason: "Candidate is {$ageHours} h old (> {$minHours} h) in same location+category — likely recurrence.",
                metadata: $metadata,
                suggestsRecurrence: true,
            );
        }

        return new DuplicateStrategyResult(
            strategy: 'recurrence_guard',
            points: 0,
            reason: 'No recurrence signal detected.',
            metadata: $metadata,
        );
    }
}
