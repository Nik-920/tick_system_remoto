<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\HistoricalRecurrenceStrategy;

final class HistoricalRecurrenceStrategyTest extends StrategyTestCase
{
    private HistoricalRecurrenceStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new HistoricalRecurrenceStrategy;
    }

    public function test_returns_zero_because_no_history_table_exists(): void
    {
        config(['ai.dedup.strategies.historical_recurrence' => ['enabled' => true]]);

        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
        $this->assertSame('historical_recurrence', $result->strategy);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.historical_recurrence' => ['enabled' => false]]);

        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_metadata_contains_phase_and_status_markers(): void
    {
        config(['ai.dedup.strategies.historical_recurrence' => ['enabled' => true]]);

        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        $this->assertArrayHasKey('phase', $result->metadata);
        $this->assertArrayHasKey('status', $result->metadata);
        $this->assertSame(3, $result->metadata['phase']);
        $this->assertSame('noop', $result->metadata['status']);
    }

    public function test_metadata_contains_location_and_category_for_traceability(): void
    {
        config(['ai.dedup.strategies.historical_recurrence' => ['enabled' => true]]);

        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-trace', 'category_id' => 'cat-trace']
        );
        $result = $this->strategy->evaluate($ctx);

        $this->assertArrayHasKey('location_id', $result->metadata);
        $this->assertArrayHasKey('category_id', $result->metadata);
        $this->assertSame('loc-trace', $result->metadata['location_id']);
    }

    public function test_never_blocks_or_suggests_recurrence(): void
    {
        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        $this->assertFalse($result->blocksDuplicate);
        $this->assertFalse($result->suggestsRecurrence);
    }
}
