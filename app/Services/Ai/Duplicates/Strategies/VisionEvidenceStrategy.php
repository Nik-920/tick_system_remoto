<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy (Phase 3 — Conservative / no-op): Vision Evidence.
 *
 * CURRENT IMPLEMENTATION: No-op.
 *
 * This strategy is intentionally conservative. A future version may use
 * image/evidence metadata to detect visually similar incidents. Until a
 * vision AI service is integrated and tested, it returns 0 points.
 *
 * When to enable: once ticket_media records carry AI-generated tags or
 * embeddings that allow visual comparison across tickets.
 *
 * Config key: ai.dedup.strategies.vision_evidence.enabled (default false)
 */
final class VisionEvidenceStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.vision_evidence', []);
        $enabled = (bool) ($cfg['enabled'] ?? false);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'vision_evidence',
                points: 0,
                reason: 'Vision evidence strategy disabled (Phase 3 — not yet implemented).',
                metadata: ['phase' => 3, 'status' => 'noop'],
            );
        }

        // Future: check whether both tickets have ticket_media records
        // and compare AI-generated visual descriptors.
        // For now, even if enabled, return 0 to avoid phantom signals.
        $ticketHasMedia = $context->ticket->relationLoaded('media')
            ? $context->ticket->media->isNotEmpty()
            : false;

        $candidateHasMedia = $context->candidate->relationLoaded('media')
            ? $context->candidate->media->isNotEmpty()
            : false;

        $metadata = [
            'phase' => 3,
            'status' => 'noop_enabled_but_not_implemented',
            'ticket_has_media' => $ticketHasMedia,
            'candidate_has_media' => $candidateHasMedia,
        ];

        return new DuplicateStrategyResult(
            strategy: 'vision_evidence',
            points: 0,
            reason: 'Vision evidence: no visual AI analysis available yet.',
            metadata: $metadata,
        );
    }
}
