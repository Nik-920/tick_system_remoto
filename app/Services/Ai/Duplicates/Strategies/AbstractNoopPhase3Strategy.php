<?php

namespace App\Services\Ai\Duplicates\Strategies;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;

/**
 * Base for Phase 3 conservative / no-op strategies.
 *
 * These strategies always award 0 points and never modify any record; they
 * only emit traceable metadata until their backing data sources exist. The
 * shared evaluate() handles the config toggle and the disabled/noop result
 * shape so each concrete strategy only declares its key, reasons and metadata.
 *
 * Config key: ai.dedup.strategies.{key}.enabled (default true)
 */
abstract class AbstractNoopPhase3Strategy implements DuplicateDetectionStrategy
{
    private const PHASE = 3;

    public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
    {
        $cfg = config('ai.dedup.strategies.'.$this->strategyKey(), []);
        $enabled = (bool) ($cfg['enabled'] ?? true);

        if (! $enabled) {
            return new DuplicateStrategyResult(
                strategy: $this->strategyKey(),
                points: 0,
                reason: $this->disabledReason(),
                metadata: ['phase' => self::PHASE, 'status' => 'disabled'],
            );
        }

        return new DuplicateStrategyResult(
            strategy: $this->strategyKey(),
            points: 0,
            reason: $this->noopReason(),
            metadata: [
                'phase' => self::PHASE,
                'status' => 'noop',
                ...$this->noopMetadata($context),
            ],
        );
    }

    abstract protected function strategyKey(): string;

    abstract protected function disabledReason(): string;

    abstract protected function noopReason(): string;

    /**
     * Extra traceability metadata for the no-op result.
     *
     * @return array<string, mixed>
     */
    abstract protected function noopMetadata(DuplicateCandidateContext $context): array;
}
