<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy: composite signal — same location + same category + < 24 h window.
 *
 * This represents a contextually strong duplicate situation where the exact
 * same place and type of problem recurred in a very short time. It avoids
 * double-counting by being additive to (not replacing) the individual
 * SameLocationStrategy, SameCategoryStrategy, and TimeWindowStrategy.
 *
 * Config key: ai.dedup.strategies.contextual_duplicate.same_location_category_24h_bonus (default 35)
 */
final class ContextualDuplicateStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.contextual_duplicate', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'contextual_duplicate',
                points: 0,
                reason: 'Contextual duplicate strategy disabled.',
            );
        }

        $sameLocation = $context->sameLocation;
        $sameCategory = $context->sameCategory;
        $ageHours = $context->candidateAgeHours;

        $metadata = [
            'same_location' => $sameLocation,
            'same_category' => $sameCategory,
            'candidate_age_hours' => $ageHours,
        ];

        if ($sameLocation === true && $sameCategory === true && $ageHours !== null && $ageHours <= 24) {
            $bonus = (int) ($cfg['same_location_category_24h_bonus'] ?? 35);

            return new DuplicateStrategyResult(
                strategy: 'contextual_duplicate',
                points: $bonus,
                reason: 'Contextual duplicate: same location + category within 24 h.',
                metadata: $metadata,
            );
        }

        return new DuplicateStrategyResult(
            strategy: 'contextual_duplicate',
            points: 0,
            reason: 'Contextual conditions not fully met.',
            metadata: $metadata,
        );
    }
}
