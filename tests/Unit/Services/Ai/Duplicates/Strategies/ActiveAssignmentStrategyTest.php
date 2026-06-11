<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Services\Ai\Duplicates\Strategies\ActiveAssignmentStrategy;

final class ActiveAssignmentStrategyTest extends StrategyTestCase
{
    private ActiveAssignmentStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ActiveAssignmentStrategy;
        config(['ai.dedup.strategies.active_assignment' => [
            'enabled' => true,
            'assigned_bonus' => 15,
            'locked_bonus' => 15,
        ]]);
    }

    public function test_returns_bonus_when_candidate_has_assigned_to(): void
    {
        $ctx = $this->makeContext(candidateAttrs: ['assigned_to' => 'user-abc']);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(15, $result->points);
        $this->assertTrue($result->metadata['assigned_to_set']);
    }

    public function test_returns_bonus_when_candidate_is_assignment_locked(): void
    {
        $ctx = $this->makeContext(candidateAttrs: [
            'assigned_to' => null,
            'assignment_locked' => true,
        ]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(15, $result->points);
        $this->assertTrue($result->metadata['assignment_locked']);
    }

    public function test_returns_double_bonus_when_assigned_and_locked(): void
    {
        $ctx = $this->makeContext(candidateAttrs: [
            'assigned_to' => 'user-xyz',
            'assignment_locked' => true,
        ]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(30, $result->points);
    }

    public function test_returns_zero_when_candidate_has_no_assignment(): void
    {
        $ctx = $this->makeContext(candidateAttrs: [
            'assigned_to' => null,
            'assignment_locked' => false,
        ]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.active_assignment' => ['enabled' => false]]);

        $ctx = $this->makeContext(candidateAttrs: ['assigned_to' => 'user-abc']);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_metadata_contains_assignment_flags(): void
    {
        $ctx = $this->makeContext(candidateAttrs: ['assigned_to' => 'user-1']);

        $result = $this->strategy->evaluate($ctx);

        $this->assertArrayHasKey('assigned_to_set', $result->metadata);
        $this->assertArrayHasKey('assignment_locked', $result->metadata);
        $this->assertArrayHasKey('points', $result->metadata);
    }
}
