<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy: scores based on how recently the candidate was created.
 *
 * Fresher candidates are stronger duplicate signals. Very old candidates
 * hint at recurrence rather than an exact duplicate.
 *
 * Config keys (under ai.dedup.strategies.time_window):
 *   within_24h         (default 25)
 *   within_72h         (default 15)
 *   within_7d          (default  5)
 *   older_than_30d_penalty (default -20)
 */
final class TimeWindowStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.time_window', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'time_window',
                points: 0,
                reason: 'Time window strategy disabled.',
            );
        }

        $ageHours = $context->candidateAgeHours;

        if ($ageHours === null) {
            return new DuplicateStrategyResult(
                strategy: 'time_window',
                points: 0,
                reason: 'Candidate created_at not available.',
            );
        }

        $within24h = (int) ($cfg['within_24h'] ?? 25);
        $within72h = (int) ($cfg['within_72h'] ?? 15);
        $within7d = (int) ($cfg['within_7d'] ?? 5);
        $olderThan30dPenalty = (int) ($cfg['older_than_30d_penalty'] ?? -20);

        $metadata = ['candidate_age_hours' => $ageHours];

        $suggestsRecurrence = false;

        if ($ageHours <= 24) {
            $points = $within24h;
            $reason = "Candidate created within 24 h (age: {$ageHours} h).";
        } elseif ($ageHours <= 72) {
            $points = $within72h;
            $reason = "Candidate created within 72 h (age: {$ageHours} h).";
        } elseif ($ageHours <= 168) { // 7 days
            $points = $within7d;
            $reason = "Candidate created within 7 d (age: {$ageHours} h).";
        } elseif ($ageHours > 720) { // > 30 days
            $points = $olderThan30dPenalty;
            $reason = "Candidate is older than 30 d (age: {$ageHours} h) — possible recurrence.";
            $suggestsRecurrence = true;
        } else {
            // 7 d < age <= 30 d: neutral
            $points = 0;
            $reason = "Candidate age {$ageHours} h is within 7–30 d window (neutral).";
        }

        return new DuplicateStrategyResult(
            strategy: 'time_window',
            points: $points,
            reason: $reason,
            metadata: $metadata,
            suggestsRecurrence: $suggestsRecurrence,
        );
    }
}
