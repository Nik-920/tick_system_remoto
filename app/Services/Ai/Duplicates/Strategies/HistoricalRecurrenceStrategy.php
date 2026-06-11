<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy (Phase 3 — Conservative / no-op): Historical Recurrence.
 *
 * CURRENT IMPLEMENTATION: No-op.
 *
 * A future version would query a historical incident log table
 * (e.g. location_incident_history or similar) to detect whether the
 * same type of problem in this location has occurred repeatedly.
 *
 * This strategy NEVER modifies any record. It is read-only.
 *
 * When to enable: once a dedicated incident history table exists and is
 * populated with enough data for pattern detection (> 3 months, > 5 events).
 *
 * Config key: ai.dedup.strategies.historical_recurrence.enabled (default true)
 */
final class HistoricalRecurrenceStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.historical_recurrence', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'historical_recurrence',
                points: 0,
                reason: 'Historical recurrence strategy disabled.',
                metadata: ['phase' => 3, 'status' => 'disabled'],
            );
        }

        // No historical incident table exists yet.
        // Return 0 points with clear traceability.
        return new DuplicateStrategyResult(
            strategy: 'historical_recurrence',
            points: 0,
            reason: 'Historical recurrence: no incident history table available yet (Phase 3 — no-op).',
            metadata: [
                'phase' => 3,
                'status' => 'noop',
                'location_id' => $context->ticket->location_id,
                'category_id' => $context->ticket->category_id,
            ],
        );
    }
}
