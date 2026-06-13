<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\SameCategoryStrategy;

final class SameCategoryStrategyTest extends StrategyTestCase
{
    private SameCategoryStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new SameCategoryStrategy;
        config(['ai.dedup.strategies.same_category' => [
            'enabled' => true,
            'weight' => 15,
            'different_penalty' => -10,
        ]]);
    }

    public function test_returns_weight_when_same_category(): void
    {
        $ctx = $this->makeContext(
            ticketAttrs: ['category_id' => 'cat-001'],
            candidateAttrs: ['category_id' => 'cat-001'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(15, $result->points);
        $this->assertTrue($result->metadata['same_category']);
    }

    public function test_returns_soft_penalty_when_different_categories(): void
    {
        $ctx = $this->makeContext(
            ticketAttrs: ['category_id' => 'cat-001'],
            candidateAttrs: ['category_id' => 'cat-002'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(-10, $result->points);
        $this->assertFalse($result->metadata['same_category']);
    }

    public function test_returns_zero_when_category_id_is_null(): void
    {
        $ctx = $this->makeContext(
            ticketAttrs: ['category_id' => null],
            candidateAttrs: ['category_id' => 'cat-001'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.same_category' => ['enabled' => false]]);

        $ctx = $this->makeContext(
            ticketAttrs: ['category_id' => 'cat-001'],
            candidateAttrs: ['category_id' => 'cat-001'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_penalty_is_softer_than_location_penalty(): void
    {
        // Ensures that a different category is a weaker counter-signal than
        // a different location (-10 vs -30 configured for location).
        $ctx = $this->makeContext(
            ticketAttrs: ['category_id' => 'cat-001'],
            candidateAttrs: ['category_id' => 'cat-999'],
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertGreaterThan(-15, $result->points); // softer than location penalty
    }
}
