<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\TimeWindowStrategy;
use Illuminate\Support\Carbon;

final class TimeWindowStrategyTest extends StrategyTestCase
{
    private TimeWindowStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new TimeWindowStrategy;
        config(['ai.dedup.strategies.time_window' => [
            'enabled' => true,
            'within_24h' => 25,
            'within_72h' => 15,
            'within_7d' => 5,
            'older_than_30d_penalty' => -20,
        ]]);
    }

    public function test_returns_high_bonus_when_candidate_within_24h(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            candidateAttrs: ['created_at' => $now->copy()->subHours(12)],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(25, $result->points);
    }

    public function test_returns_medium_bonus_when_candidate_within_72h(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            candidateAttrs: ['created_at' => $now->copy()->subHours(48)],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(15, $result->points);
    }

    public function test_returns_low_bonus_when_candidate_within_7_days(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            candidateAttrs: ['created_at' => $now->copy()->subDays(5)],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(5, $result->points);
    }

    public function test_returns_neutral_when_candidate_between_7_and_30_days(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            candidateAttrs: ['created_at' => $now->copy()->subDays(15)],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
        $this->assertFalse($result->suggestsRecurrence);
    }

    public function test_returns_penalty_and_suggests_recurrence_when_older_than_30_days(): void
    {
        $now = Carbon::now();
        $ctx = $this->makeContext(
            candidateAttrs: ['created_at' => $now->copy()->subDays(45)],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(-20, $result->points);
        $this->assertTrue($result->suggestsRecurrence);
    }

    public function test_returns_zero_when_created_at_is_null(): void
    {
        $ctx = $this->makeContext(candidateAttrs: ['created_at' => null]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.time_window' => ['enabled' => false]]);

        $now = Carbon::now();
        $ctx = $this->makeContext(
            candidateAttrs: ['created_at' => $now->copy()->subHours(1)],
            now: $now,
        );

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }
}
