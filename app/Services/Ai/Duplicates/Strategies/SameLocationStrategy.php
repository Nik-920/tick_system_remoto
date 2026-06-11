<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy: bonus when ticket and candidate share the same location,
 * penalty when they have different (but both set) locations.
 *
 * Config keys (under ai.dedup.strategies.same_location):
 *   weight           (default 25) — bonus when same location
 *   different_penalty (default -30) — penalty when different locations
 */
final class SameLocationStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.same_location', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'same_location',
                points: 0,
                reason: 'Same location strategy disabled.',
            );
        }

        if ($context->sameLocation === null) {
            return new DuplicateStrategyResult(
                strategy: 'same_location',
                points: 0,
                reason: 'Location data missing on ticket or candidate.',
            );
        }

        $weight = (int) ($cfg['weight'] ?? 25);
        $penalty = (int) ($cfg['different_penalty'] ?? -30);

        $metadata = [
            'ticket_location_id' => $context->ticket->location_id,
            'candidate_location_id' => $context->candidate->location_id,
            'same_location' => $context->sameLocation,
        ];

        if ($context->sameLocation) {
            return new DuplicateStrategyResult(
                strategy: 'same_location',
                points: $weight,
                reason: 'Ticket and candidate share the same location.',
                metadata: $metadata,
            );
        }

        return new DuplicateStrategyResult(
            strategy: 'same_location',
            points: $penalty,
            reason: 'Ticket and candidate are in different locations.',
            metadata: $metadata,
        );
    }
}
