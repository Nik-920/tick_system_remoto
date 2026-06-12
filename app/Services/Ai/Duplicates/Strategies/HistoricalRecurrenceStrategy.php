<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\DuplicateCandidateContext;

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
final class HistoricalRecurrenceStrategy extends AbstractNoopPhase3Strategy
{
    protected function strategyKey(): string
    {
        return 'historical_recurrence';
    }

    protected function disabledReason(): string
    {
        return 'Historical recurrence strategy disabled.';
    }

    protected function noopReason(): string
    {
        return 'Historical recurrence: no incident history table available yet (Phase 3 — no-op).';
    }

    protected function noopMetadata(DuplicateCandidateContext $context): array
    {
        return [
            'location_id' => $context->ticket->location_id,
            'category_id' => $context->ticket->category_id,
        ];
    }
}
