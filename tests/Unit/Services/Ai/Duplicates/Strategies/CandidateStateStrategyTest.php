<?php

namespace Tests\Unit\Services\Ai\Duplicates\Strategies;

use App\Models\Ticket;
use App\Services\Ai\Duplicates\Strategies\CandidateStateStrategy;

final class CandidateStateStrategyTest extends StrategyTestCase
{
    private CandidateStateStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new CandidateStateStrategy;
        config(['ai.dedup.strategies.candidate_state' => [
            'enabled' => true,
            'open' => 20,
            'assigned' => 15,
            'in_progress' => 10,
            'resolved_recent' => 5,
            'cancelled' => -30,
            'rejected' => -20,
        ]]);
    }

    public function test_open_candidate_returns_positive_bonus(): void
    {
        $ctx = $this->makeContext(candidateAttrs: ['state' => Ticket::STATE_OPEN]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(20, $result->points);
    }

    public function test_in_progress_candidate_returns_medium_bonus(): void
    {
        $ctx = $this->makeContext(candidateAttrs: ['state' => Ticket::STATE_IN_PROGRESS]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(10, $result->points);
    }

    public function test_resolved_candidate_returns_low_bonus(): void
    {
        $ctx = $this->makeContext(candidateAttrs: ['state' => Ticket::STATE_RESOLVED]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(5, $result->points);
    }

    public function test_cancelled_candidate_returns_strong_negative_score(): void
    {
        $ctx = $this->makeContext(candidateAttrs: ['state' => Ticket::STATE_CANCELLED]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(-30, $result->points);
    }

    public function test_rejected_candidate_returns_negative_score(): void
    {
        $ctx = $this->makeContext(candidateAttrs: ['state' => Ticket::STATE_REJECTED]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(-20, $result->points);
    }

    public function test_unknown_state_returns_zero(): void
    {
        $ctx = $this->makeContext(candidateAttrs: ['state' => 'unknown_state']);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_returns_zero_when_disabled(): void
    {
        config(['ai.dedup.strategies.candidate_state' => ['enabled' => false]]);

        $ctx = $this->makeContext(candidateAttrs: ['state' => Ticket::STATE_OPEN]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertSame(0, $result->points);
    }

    public function test_metadata_contains_candidate_state(): void
    {
        $ctx = $this->makeContext(candidateAttrs: ['state' => Ticket::STATE_OPEN]);

        $result = $this->strategy->evaluate($ctx);

        $this->assertArrayHasKey('candidate_state', $result->metadata);
        $this->assertSame(Ticket::STATE_OPEN, $result->metadata['candidate_state']);
    }
}
