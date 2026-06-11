<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy: scores based on token overlap between ticket and candidate titles.
 *
 * Uses the pre-tokenised arrays from DuplicateCandidateContext.
 *
 * Config keys (under ai.dedup.strategies.title_overlap):
 *   weight_high   (default 25) — overlap ratio >= 0.70
 *   weight_medium (default 15) — overlap ratio >= 0.45
 */
final class TitleOverlapStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.title_overlap', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'title_overlap',
                points: 0,
                reason: 'Title overlap strategy disabled.',
            );
        }

        $sourceTokens = $context->ticketTokens;
        $candidateTokens = $context->candidateTokens;

        // If either title has no meaningful tokens, we cannot evaluate.
        if ($sourceTokens === [] || $candidateTokens === []) {
            return new DuplicateStrategyResult(
                strategy: 'title_overlap',
                points: 0,
                reason: 'Insufficient tokens to evaluate title overlap.',
                metadata: ['ticket_tokens' => $sourceTokens, 'candidate_tokens' => $candidateTokens],
            );
        }

        $intersection = array_intersect($sourceTokens, $candidateTokens);
        $overlapCount = count($intersection);
        $unionCount = count(array_unique(array_merge($sourceTokens, $candidateTokens)));
        $ratio = $unionCount > 0 ? round($overlapCount / $unionCount, 3) : 0.0;

        $weightHigh = (int) ($cfg['weight_high'] ?? 25);
        $weightMedium = (int) ($cfg['weight_medium'] ?? 15);

        $metadata = [
            'ticket_tokens' => $sourceTokens,
            'candidate_tokens' => $candidateTokens,
            'overlap_tokens' => array_values($intersection),
            'overlap_ratio' => $ratio,
        ];

        if ($ratio >= 0.70) {
            return new DuplicateStrategyResult(
                strategy: 'title_overlap',
                points: $weightHigh,
                reason: "High title overlap ratio {$ratio} (>= 0.70).",
                metadata: $metadata,
            );
        }

        if ($ratio >= 0.45) {
            return new DuplicateStrategyResult(
                strategy: 'title_overlap',
                points: $weightMedium,
                reason: "Medium title overlap ratio {$ratio} (>= 0.45).",
                metadata: $metadata,
            );
        }

        return new DuplicateStrategyResult(
            strategy: 'title_overlap',
            points: 0,
            reason: "Low title overlap ratio {$ratio} (< 0.45).",
            metadata: $metadata,
        );
    }
}
