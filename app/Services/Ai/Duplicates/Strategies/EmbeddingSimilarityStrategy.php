<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy: scores based on the cosine similarity of embedding vectors.
 *
 * Config keys (under ai.dedup.strategies.embedding_similarity):
 *   high_threshold  (default 0.90) → weight_high  (default 50)
 *   medium_threshold (default 0.85) → weight_medium (default 35)
 *   low_threshold   (default 0.80) → weight_low   (default 20)
 */
final class EmbeddingSimilarityStrategy implements DuplicateDetectionStrategy
{
    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.embedding_similarity', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled || $context->embeddingSimilarity === null) {
            return new DuplicateStrategyResult(
                strategy: 'embedding_similarity',
                points: 0,
                reason: 'Embedding similarity not available or strategy disabled.',
            );
        }

        $sim = $context->embeddingSimilarity;
        $highThreshold = (float) ($cfg['high_threshold'] ?? 0.90);
        $mediumThreshold = (float) ($cfg['medium_threshold'] ?? 0.85);
        $lowThreshold = (float) ($cfg['low_threshold'] ?? 0.80);

        $weightHigh = (int) ($cfg['weight_high'] ?? 50);
        $weightMedium = (int) ($cfg['weight_medium'] ?? 35);
        $weightLow = (int) ($cfg['weight_low'] ?? 20);

        if ($sim >= $highThreshold) {
            $points = $weightHigh;
            $reason = "High embedding similarity ({$sim}) >= {$highThreshold}.";
            $metadata = ['similarity' => $sim, 'threshold_used' => $highThreshold, 'level' => 'high'];
        } elseif ($sim >= $mediumThreshold) {
            $points = $weightMedium;
            $reason = "Medium embedding similarity ({$sim}) >= {$mediumThreshold}.";
            $metadata = ['similarity' => $sim, 'threshold_used' => $mediumThreshold, 'level' => 'medium'];
        } elseif ($sim >= $lowThreshold) {
            $points = $weightLow;
            $reason = "Low embedding similarity ({$sim}) >= {$lowThreshold}.";
            $metadata = ['similarity' => $sim, 'threshold_used' => $lowThreshold, 'level' => 'low'];
        } else {
            $points = 0;
            $reason = "Embedding similarity ({$sim}) below all thresholds.";
            $metadata = ['similarity' => $sim];
        }

        return new DuplicateStrategyResult(
            strategy: 'embedding_similarity',
            points: $points,
            reason: $reason,
            metadata: $metadata,
        );
    }
}
