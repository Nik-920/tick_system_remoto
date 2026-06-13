<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\ContextualDuplicateStrategy;
use Illuminate\Support\Carbon;

final class ContextualDuplicateStrategyTest extends StrategyTestCase
{
    private ContextualDuplicateStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ContextualDuplicateStrategy;
        config(['ai.dedup.strategies.contextual_duplicate' => [
            'enabled' => true,
            'same_location_category_24h_bonus' => 35,
        ]]);
    }

    public function test_returns_bonus_when_same_location_category_within_24h(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-A', 'category_id' => 'cat-A'],
            candidateAttrs: [
                'location_id' => 'loc-A',
                'category_id' => 'cat-A',
                'created_at' => $now->copy()->subHours(6),
            ],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(35, $result->points);
    }

    public function test_returns_zero_when_different_location_even_within_24h(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-A', 'category_id' => 'cat-A'],
            candidateAttrs: [
                'location_id' => 'loc-B',
                'category_id' => 'cat-A',
                'created_at' => $now->copy()->subHours(1),
            ],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_returns_zero_when_same_context_but_older_than_24h(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-A', 'category_id' => 'cat-A'],
            candidateAttrs: [
                'location_id' => 'loc-A',
                'category_id' => 'cat-A',
                'created_at' => $now->copy()->subHours(48),
            ],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_returns_zero_when_location_is_null(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => null, 'category_id' => 'cat-A'],
            candidateAttrs: [
                'location_id' => null,
                'category_id' => 'cat-A',
                'created_at' => $now->copy()->subHours(1),
            ],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.contextual_duplicate' => ['enabled' => false]]);

        $now = Carbon::now();
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-A', 'category_id' => 'cat-A'],
            candidateAttrs: [
                'location_id' => 'loc-A',
                'category_id' => 'cat-A',
                'created_at' => $now->copy()->subHours(2),
            ],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }
}
