<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy: bonus when ticket and candidate share the same category,
 * soft penalty when categories differ.
 *
 * Config keys (under ai.dedup.strategies.same_category):
 *   weight           (default 15) — bonus when same category
 *   different_penalty (default -10) — penalty when different categories
 */
final class SameCategoryStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.same_category', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'same_category',
                points: 0,
                reason: 'Same category strategy disabled.',
            );
        }

        if ($context->sameCategory === null) {
            return new DuplicateStrategyResult(
                strategy: 'same_category',
                points: 0,
                reason: 'Category data missing on ticket or candidate.',
            );
        }

        $weight = (int) ($cfg['weight'] ?? 15);
        $penalty = (int) ($cfg['different_penalty'] ?? -10);

        $metadata = [
            'ticket_category_id' => $context->ticket->category_id,
            'candidate_category_id' => $context->candidate->category_id,
            'same_category' => $context->sameCategory,
        ];

        if ($context->sameCategory) {
            return new DuplicateStrategyResult(
                strategy: 'same_category',
                points: $weight,
                reason: 'Ticket and candidate share the same category.',
                metadata: $metadata,
            );
        }

        return new DuplicateStrategyResult(
            strategy: 'same_category',
            points: $penalty,
            reason: 'Ticket and candidate are in different categories.',
            metadata: $metadata,
        );
    }
}
