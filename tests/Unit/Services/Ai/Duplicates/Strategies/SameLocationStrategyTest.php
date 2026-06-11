<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\SameLocationStrategy;

final class SameLocationStrategyTest extends StrategyTestCase
{
    private SameLocationStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new SameLocationStrategy;
        config(['ai.dedup.strategies.same_location' => [
            'enabled' => true,
            'weight' => 25,
            'different_penalty' => -30,
        ]]);
    }

    public function test_returns_weight_when_same_location(): void
    {
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-001'],
            candidateAttrs: ['location_id' => 'loc-001'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(25, $result->points);
        $this->assertTrue($result->metadata['same_location']);
    }

    public function test_returns_penalty_when_different_locations(): void
    {
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-001'],
            candidateAttrs: ['location_id' => 'loc-002'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(-30, $result->points);
        $this->assertFalse($result->metadata['same_location']);
    }

    public function test_returns_zero_when_location_id_is_null(): void
    {
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => null],
            candidateAttrs: ['location_id' => 'loc-001'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.same_location' => ['enabled' => false]]);

        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-001'],
            candidateAttrs: ['location_id' => 'loc-001'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_metadata_contains_both_location_ids(): void
    {
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-A'],
            candidateAttrs: ['location_id' => 'loc-A'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertArrayHasKey('ticket_location_id', $result->metadata);
        $this->assertArrayHasKey('candidate_location_id', $result->metadata);
        $this->assertSame('loc-A', $result->metadata['ticket_location_id']);
    }
}
