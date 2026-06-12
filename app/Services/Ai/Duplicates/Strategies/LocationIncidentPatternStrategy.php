<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\DuplicateCandidateContext;

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
final class LocationIncidentPatternStrategy extends AbstractNoopPhase3Strategy
{
    protected function strategyKey(): string
    {
        return 'location_incident_pattern';
    }

    protected function disabledReason(): string
    {
        return 'Location incident pattern strategy disabled.';
    }

    protected function noopReason(): string
    {
        return 'Location incident pattern: no pattern data available yet (Phase 3 — no-op).';
    }

    protected function noopMetadata(DuplicateCandidateContext $context): array
    {
        return [
            'location_id' => $context->ticket->location_id,
            'category_id' => $context->ticket->category_id,
            'same_location' => $context->sameLocation,
        ];
    }
}
