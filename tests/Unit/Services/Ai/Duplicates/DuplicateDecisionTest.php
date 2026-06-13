<?php

namespace Tests\Unit\Services\Ai\Duplicates;

use App\Services\Ai\Duplicates\DuplicateDecision;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;
use Tests\TestCase;

/**
 * Unit tests for DuplicateDecision::fromResults() logic.
 */
final class DuplicateDecisionTest extends TestCase
{
    public function test_is_duplicate_when_score_meets_threshold(): void
    {
        $results = [
            new DuplicateStrategyResult('strategy_a', 50, 'High similarity.'),
            new DuplicateStrategyResult('strategy_b', 25, 'Same location.'),
        ];

        $decision = DuplicateDecision::fromResults($results, duplicateThreshold: 70);

        $this->assertTrue($decision->isDuplicate);
        $this->assertFalse($decision->isRecurrence);
        $this->assertSame(75, $decision->score);
    }

    public function test_not_duplicate_when_score_below_threshold(): void
    {
        $results = [
            new DuplicateStrategyResult('strategy_a', 30, 'Low similarity.'),
        ];

        $decision = DuplicateDecision::fromResults($results, duplicateThreshold: 70);

        $this->assertFalse($decision->isDuplicate);
        $this->assertSame(30, $decision->score);
    }

    public function test_blocked_prevents_duplicate_even_when_score_is_high(): void
    {
        $results = [
            new DuplicateStrategyResult('strategy_a', 100, 'Very high score.'),
            new DuplicateStrategyResult('blocker', 0, 'Candidate cancelled.', blocksDuplicate: true),
        ];

        $decision = DuplicateDecision::fromResults($results, duplicateThreshold: 70);

        $this->assertFalse($decision->isDuplicate);
        $this->assertSame(100, $decision->score);
    }

    public function test_is_recurrence_when_not_duplicate_and_recurrence_hinted(): void
    {
        $results = [
            new DuplicateStrategyResult('time_window', -20, 'Old candidate.', suggestsRecurrence: true),
        ];

        $decision = DuplicateDecision::fromResults($results, duplicateThreshold: 70, recurrenceThreshold: -30);

        $this->assertFalse($decision->isDuplicate);
        $this->assertTrue($decision->isRecurrence);
    }

    public function test_not_recurrence_when_is_duplicate(): void
    {
        // 100 (high) + (-20) (recurrence hint) = 80 >= threshold 70 → isDuplicate=true
        $results = [
            new DuplicateStrategyResult('strategy_a', 100, 'Very high similarity.'),
            new DuplicateStrategyResult('time_window', -20, 'Old candidate.', suggestsRecurrence: true),
        ];

        $decision = DuplicateDecision::fromResults($results, duplicateThreshold: 70, recurrenceThreshold: -30);

        $this->assertTrue($decision->isDuplicate);
        $this->assertFalse($decision->isRecurrence);
    }

    public function test_reasons_are_collected_from_all_results(): void
    {
        $results = [
            new DuplicateStrategyResult('a', 10, 'Reason A.'),
            new DuplicateStrategyResult('b', 10, 'Reason B.'),
            new DuplicateStrategyResult('c', 0, ''), // empty reason should not appear
        ];

        $decision = DuplicateDecision::fromResults($results, duplicateThreshold: 70);

        $this->assertCount(2, $decision->reasons);
        $this->assertStringContainsString('Reason A', $decision->reasons[0]);
        $this->assertStringContainsString('Reason B', $decision->reasons[1]);
    }

    public function test_metadata_contains_summary(): void
    {
        $results = [
            new DuplicateStrategyResult('s', 80, 'High.', metadata: ['sim' => 0.95]),
        ];

        $decision = DuplicateDecision::fromResults($results, duplicateThreshold: 70);

        $this->assertArrayHasKey('_summary', $decision->metadata);
        $this->assertSame(80, $decision->metadata['_summary']['score']);
        $this->assertSame(1, $decision->metadata['_summary']['strategies_evaluated']);
    }

    public function test_to_log_context_contains_expected_keys(): void
    {
        $results = [new DuplicateStrategyResult('x', 50, 'Test.')];
        $decision = DuplicateDecision::fromResults($results, 70);
        $ctx = $decision->toLogContext();

        $this->assertArrayHasKey('strategy_score', $ctx);
        $this->assertArrayHasKey('is_duplicate', $ctx);
        $this->assertArrayHasKey('is_recurrence', $ctx);
        $this->assertArrayHasKey('reasons', $ctx);
        $this->assertArrayHasKey('strategies_metadata', $ctx);
    }

    public function test_empty_results_produce_no_duplicate(): void
    {
        $decision = DuplicateDecision::fromResults([], duplicateThreshold: 70);

        $this->assertFalse($decision->isDuplicate);
        $this->assertFalse($decision->isRecurrence);
        $this->assertSame(0, $decision->score);
        $this->assertEmpty($decision->results);
        $this->assertEmpty($decision->reasons);
    }
}
