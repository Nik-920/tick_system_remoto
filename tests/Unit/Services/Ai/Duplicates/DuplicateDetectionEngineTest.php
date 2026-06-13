<?php

namespace Tests\Unit\Services\Ai\Duplicates;

use App\Contracts\Ai\Duplicates\DuplicateDetectionStrategy;
use App\Models\Ticket;
use App\Services\Ai\Duplicates\DuplicateCandidateContext;
use App\Services\Ai\Duplicates\DuplicateDetectionEngine;
use App\Services\Ai\Duplicates\DuplicateStrategyResult;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Unit tests for DuplicateDetectionEngine.
 * Uses anonymous strategy stubs — no concrete strategy classes are referenced.
 */
final class DuplicateDetectionEngineTest extends TestCase
{
    /** Build an anonymous strategy stub that returns a fixed result. */
    private function stubStrategy(int $points, string $name = 'stub', bool $blocks = false, bool $recurrence = false): DuplicateDetectionStrategy
    {
        return new class($points, $name, $blocks, $recurrence) implements DuplicateDetectionStrategy
        {
            public function __construct(
                private int $points,
                private string $name,
                private bool $blocks,
                private bool $recurrence,
            ) {}

            public function evaluate(DuplicateCandidateContext $context): DuplicateStrategyResult
            {
                return new DuplicateStrategyResult(
                    strategy: $this->name,
                    points: $this->points,
                    reason: "Stub result: {$this->points} pts.",
                    blocksDuplicate: $this->blocks,
                    suggestsRecurrence: $this->recurrence,
                );
            }
        };
    }

    private function makeContext(): DuplicateCandidateContext
    {
        $ticket = new Ticket(['id' => 'ticket-1', 'title' => 'Test ticket', 'state' => 'open']);
        $candidate = new Ticket(['id' => 'ticket-2', 'title' => 'Test candidate', 'state' => 'open']);

        return new DuplicateCandidateContext(
            ticket: $ticket,
            candidate: $candidate,
            now: Carbon::now(),
            embeddingSimilarity: 0.95,
        );
    }

    public function test_engine_aggregates_scores_from_all_strategies(): void
    {
        $engine = new DuplicateDetectionEngine(
            strategies: [
                $this->stubStrategy(40, 'a'),
                $this->stubStrategy(35, 'b'),
            ],
            duplicateThreshold: 70,
        );

        $decision = $engine->evaluate($this->makeContext());

        $this->assertSame(75, $decision->score);
        $this->assertTrue($decision->isDuplicate);
        $this->assertCount(2, $decision->results);
    }

    public function test_engine_returns_no_duplicate_when_score_below_threshold(): void
    {
        $engine = new DuplicateDetectionEngine(
            strategies: [$this->stubStrategy(30, 'low')],
            duplicateThreshold: 70,
        );

        $decision = $engine->evaluate($this->makeContext());

        $this->assertFalse($decision->isDuplicate);
        $this->assertSame(30, $decision->score);
    }

    public function test_engine_respects_blocking_strategy(): void
    {
        $engine = new DuplicateDetectionEngine(
            strategies: [
                $this->stubStrategy(100, 'high'),
                $this->stubStrategy(0, 'blocker', blocks: true),
            ],
            duplicateThreshold: 70,
        );

        $decision = $engine->evaluate($this->makeContext());

        $this->assertFalse($decision->isDuplicate);
    }

    public function test_engine_signals_recurrence_when_hinted_and_not_duplicate(): void
    {
        $engine = new DuplicateDetectionEngine(
            strategies: [$this->stubStrategy(-20, 'old_candidate', recurrence: true)],
            duplicateThreshold: 70,
            recurrenceThreshold: -30,
        );

        $decision = $engine->evaluate($this->makeContext());

        $this->assertFalse($decision->isDuplicate);
        $this->assertTrue($decision->isRecurrence);
    }

    public function test_engine_with_empty_strategies_returns_zero_score(): void
    {
        $engine = new DuplicateDetectionEngine(strategies: [], duplicateThreshold: 70);
        $decision = $engine->evaluate($this->makeContext());

        $this->assertFalse($decision->isDuplicate);
        $this->assertSame(0, $decision->score);
        $this->assertEmpty($decision->results);
    }

    public function test_engine_exposes_duplicate_threshold(): void
    {
        $engine = new DuplicateDetectionEngine(strategies: [], duplicateThreshold: 85);

        $this->assertSame(85, $engine->duplicateThreshold());
    }

    public function test_engine_depends_only_on_strategy_contract(): void
    {
        // This test ensures the engine accepts any iterable of the interface.
        // If it referenced concrete classes, this generator would fail type-check.
        $generator = (function (): \Generator {
            yield $this->stubStrategy(80, 'gen');
        })();

        $engine = new DuplicateDetectionEngine(strategies: $generator, duplicateThreshold: 70);
        $decision = $engine->evaluate($this->makeContext());

        $this->assertTrue($decision->isDuplicate);
    }
}
