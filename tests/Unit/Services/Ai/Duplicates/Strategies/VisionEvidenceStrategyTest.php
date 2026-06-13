<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\VisionEvidenceStrategy;

final class VisionEvidenceStrategyTest extends StrategyTestCase
{
    private VisionEvidenceStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new VisionEvidenceStrategy;
    }

    public function test_returns_zero_when_disabled_by_default(): void
    {
        config(['ai.dedup.strategies.vision_evidence' => ['enabled' => false]]);

        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
        $this->assertSame('vision_evidence', $result->strategy);
    }

    public function test_returns_zero_even_when_enabled_because_no_vision_ai(): void
    {
        config(['ai.dedup.strategies.vision_evidence' => ['enabled' => true]]);

        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        // Phase 3 no-op: 0 points even when enabled flag is set
        $this->assertSame(0, $result->points);
    }

    public function test_metadata_contains_phase_marker(): void
    {
        config(['ai.dedup.strategies.vision_evidence' => ['enabled' => false]]);

        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        $this->assertArrayHasKey('phase', $result->metadata);
        $this->assertSame(3, $result->metadata['phase']);
    }

    public function test_never_blocks_duplicate_nor_suggests_recurrence(): void
    {
        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        $this->assertFalse($result->blocksDuplicate);
        $this->assertFalse($result->suggestsRecurrence);
    }
}
