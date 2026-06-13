<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\RecurrenceGuardStrategy;
use Illuminate\Support\Carbon;

final class RecurrenceGuardStrategyTest extends StrategyTestCase
{
    private RecurrenceGuardStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new RecurrenceGuardStrategy;
        config(['ai.dedup.strategies.recurrence_guard' => [
            'enabled' => true,
            'min_days_for_recurrence' => 30,
        ]]);
    }

    public function test_suggests_recurrence_when_old_candidate_in_same_context(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-A', 'category_id' => 'cat-A'],
            candidateAttrs: [
                'location_id' => 'loc-A',
                'category_id' => 'cat-A',
                'created_at' => $now->copy()->subDays(45),
            ],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertTrue($result->suggestsRecurrence);
        $this->assertLessThan(0, $result->points);
    }

    public function test_does_not_suggest_recurrence_for_recent_candidate(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-A', 'category_id' => 'cat-A'],
            candidateAttrs: [
                'location_id' => 'loc-A',
                'category_id' => 'cat-A',
                'created_at' => $now->copy()->subDays(5),
            ],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertFalse($result->suggestsRecurrence);
        $this->assertSame(0, $result->points);
    }

    public function test_does_not_suggest_recurrence_when_different_location(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-A', 'category_id' => 'cat-A'],
            candidateAttrs: [
                'location_id' => 'loc-B', // different location
                'category_id' => 'cat-A',
                'created_at' => $now->copy()->subDays(45),
            ],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertFalse($result->suggestsRecurrence);
    }

    public function test_returns_zero_when_age_unavailable(): void
    {
        $ctx = $this->makeContext(candidateAttrs: ['created_at' => null]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
        $this->assertFalse($result->suggestsRecurrence);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.recurrence_guard' => ['enabled' => false]]);

        $now = Carbon::now();
        $ctx = $this->makeContext(
            ticketAttrs: ['location_id' => 'loc-A', 'category_id' => 'cat-A'],
            candidateAttrs: [
                'location_id' => 'loc-A',
                'category_id' => 'cat-A',
                'created_at' => $now->copy()->subDays(60),
            ],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
        $this->assertFalse($result->suggestsRecurrence);
    }
}
