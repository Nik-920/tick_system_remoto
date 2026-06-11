<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy (Phase 3 — Conservative / no-op): Location Incident Pattern.
 *
 * CURRENT IMPLEMENTATION: No-op.
 *
 * A future version would detect when a specific location has had a pattern
 * of similar incidents (e.g., projector failures every semester) and use
 * that pattern to adjust the recurrence signal.
 *
 * This strategy NEVER modifies any record. It is read-only.
 *
 * When to enable: once per-location incident frequency data is available
 * (e.g., via a scheduled aggregation job or dedicated reporting table).
 *
 * Config key: ai.dedup.strategies.location_incident_pattern.enabled (default true)
 */
final class LocationIncidentPatternStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.location_incident_pattern', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'location_incident_pattern',
                points: 0,
                reason: 'Location incident pattern strategy disabled.',
                metadata: ['phase' => 3, 'status' => 'disabled'],
            );
        }

        // No location incident frequency table exists yet.
        // Return 0 points with location context for future traceability.
        return new DuplicateStrategyResult(
            strategy: 'location_incident_pattern',
            points: 0,
            reason: 'Location incident pattern: no pattern data available yet (Phase 3 — no-op).',
            metadata: [
                'phase' => 3,
                'status' => 'noop',
                'location_id' => $context->ticket->location_id,
                'category_id' => $context->ticket->category_id,
                'same_location' => $context->sameLocation,
            ],
        );
    }
}
