<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Strategy: penalises tickets whose title has very few useful words,
 * or whose title consists only of common/generic terms.
 *
 * A generic title like "urgente", "problema", "no funciona" contributes
 * little diagnostic value; high similarity on such titles may be coincidental.
 *
 * Config keys (under ai.dedup.strategies.generic_text_penalty):
 *   min_useful_words (default 4)   — minimum distinct tokens to avoid penalty
 *   penalty          (default -20) — points deducted when text is generic
 */
final class GenericTextPenaltyStrategy implements DuplicateDetectionStrategy
{
    /** @var array<int, string> */
    private const GENERIC_WORDS = [
        'ayuda', 'urgente', 'problema', 'funciona', 'revisar',
        'malogrado', 'falla', 'error', 'roto', 'broken', 'issue',
        'help', 'urgent', 'fix', 'bug',
    ];

    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.generic_text_penalty', []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: 'generic_text_penalty',
                points: 0,
                reason: 'Generic text penalty strategy disabled.',
            );
        }

        $minUsefulWords = (int) ($cfg['min_useful_words'] ?? 4);
        $penalty = (int) ($cfg['penalty'] ?? -20);

        $tokens = $context->ticketTokens;
        $tokenCount = count($tokens);

        $metadata = [
            'ticket_tokens' => $tokens,
            'token_count' => $tokenCount,
            'min_useful_words' => $minUsefulWords,
        ];

        // Check if all tokens are generic
        $nonGeneric = array_filter(
            $tokens,
            static fn (string $t) => ! in_array($t, self::GENERIC_WORDS, true)
        );

        if ($tokenCount < $minUsefulWords) {
            // Too few tokens overall
            $points = $penalty;
            $reason = "Ticket title has only {$tokenCount} useful token(s) (minimum: {$minUsefulWords}).";
        } elseif ($nonGeneric === []) {
            $points = $penalty;
            $reason = 'Ticket title consists only of generic/common words.';
        } else {
            $points = 0;
            $reason = 'Ticket title has sufficient specific content.';
        }

        return new DuplicateStrategyResult(
            strategy: 'generic_text_penalty',
            points: $points,
            reason: $reason,
            metadata: $metadata,
        );
    }
}
