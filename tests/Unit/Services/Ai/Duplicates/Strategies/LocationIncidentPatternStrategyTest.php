<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\LocationIncidentPatternStrategy;

final class LocationIncidentPatternStrategyTest extends StrategyTestCase
{
    private LocationIncidentPatternStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new LocationIncidentPatternStrategy;
    }

    public function test_returns_zero_because_no_pattern_data_exists(): void
    {
        config(['ai.dedup.strategies.location_incident_pattern' => ['enabled' => true]]);

        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
        $this->assertSame('location_incident_pattern', $result->strategy);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.location_incident_pattern' => ['enabled' => false]]);

        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_metadata_contains_phase_marker(): void
    {
        config(['ai.dedup.strategies.location_incident_pattern' => ['enabled' => true]]);

        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        $this->assertArrayHasKey('phase', $result->metadata);
        $this->assertSame(3, $result->metadata['phase']);
        $this->assertSame('noop', $result->metadata['status']);
    }

    public function test_metadata_contains_location_for_future_traceability(): void
    {
        config(['ai.dedup.strategies.location_incident_pattern' => ['enabled' => true]]);

        $ctx = $this->makeContext(ticketAttrs: ['location_id' => 'loc-future']);
        $result = $this->strategy->evaluate($ctx);

        $this->assertArrayHasKey('location_id', $result->metadata);
        $this->assertSame('loc-future', $result->metadata['location_id']);
    }

    public function test_never_blocks_or_suggests_recurrence(): void
    {
        $ctx = $this->makeContext();
        $result = $this->strategy->evaluate($ctx);

        $this->assertFalse($result->blocksDuplicate);
        $this->assertFalse($result->suggestsRecurrence);
    }
}
