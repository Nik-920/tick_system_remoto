<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Locks;

use App\Support\Locks\TicketLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Tests\TestCase;

class TicketLockTest extends TestCase
{
    public function test_get_executes_callback_and_returns_its_value(): void
    {
        $result = TicketLock::mutation('t-abc')->get(fn () => 'done');

        $this->assertSame('done', $result);
    }

    public function test_get_returns_null_when_same_ticket_lock_is_already_held(): void
    {
        $secondResult = 'not set';

        TicketLock::mutation('t-race')->get(function () use (&$secondResult): void {
            $secondResult = TicketLock::mutation('t-race')->get(fn () => 'executed');
        });

        $this->assertNull($secondResult, 'get() should return null when the lock is already held by another process');
    }

    public function test_get_allows_null_return_from_callback(): void
    {
        // Ensure we can distinguish callback-returned-null from lock-not-acquired-null
        // by confirming get() ran (side effect check).
        $ran = false;

        $result = TicketLock::mutation('t-null')->get(function () use (&$ran): null {
            $ran = true;

            return null;
        });

        $this->assertTrue($ran);
        // result is null but lock WAS acquired — null is the callback's own return
        $this->assertNull($result);
    }

    public function test_block_executes_callback_when_lock_is_free(): void
    {
        $executed = false;

        TicketLock::mutation('t-block')->block(2, function () use (&$executed): void {
            $executed = true;
        });

        $this->assertTrue($executed);
    }

    public function test_block_throws_lock_timeout_exception_when_lock_is_held(): void
    {
        $this->expectException(LockTimeoutException::class);

        TicketLock::mutation('t-timeout')->get(function (): void {
            // Inner: try to block with 0-second wait → must fail immediately
            TicketLock::mutation('t-timeout')->block(0, fn () => 'noop');
        });
    }

    public function test_different_ticket_ids_have_independent_locks(): void
    {
        $innerResult = null;

        TicketLock::mutation('t-1')->get(function () use (&$innerResult): void {
            // Lock on t-1 must NOT block t-2
            $innerResult = TicketLock::mutation('t-2')->get(fn () => 'independent');
        });

        $this->assertSame('independent', $innerResult);
    }

    public function test_different_operation_types_have_independent_locks(): void
    {
        $innerResult = null;

        TicketLock::mutation('t-ops')->get(function () use (&$innerResult): void {
            // mutation lock on t-ops must NOT block embedding lock on t-ops
            $innerResult = TicketLock::embedding('t-ops')->get(fn () => 'independent');
        });

        $this->assertSame('independent', $innerResult);
    }

    public function test_lock_is_released_after_successful_callback(): void
    {
        TicketLock::mutation('t-release')->get(fn () => null);

        // Must be acquirable again immediately after release
        $result = TicketLock::mutation('t-release')->get(fn () => 'reacquired');

        $this->assertSame('reacquired', $result);
    }

    public function test_lock_is_released_even_after_exception_inside_callback(): void
    {
        try {
            TicketLock::mutation('t-ex')->get(function (): void {
                throw new \RuntimeException('intentional test error');
            });
        } catch (\RuntimeException) {
            // expected
        }

        // Lock must be released despite the exception
        $result = TicketLock::mutation('t-ex')->get(fn () => 'released');

        $this->assertSame('released', $result);
    }

    public function test_state_lock_key_is_independent_of_mutation_lock(): void
    {
        $innerResult = null;

        TicketLock::state('t-state')->get(function () use (&$innerResult): void {
            $innerResult = TicketLock::mutation('t-state')->get(fn () => 'acquired');
        });

        // state and mutation have different key suffixes → independent locks
        $this->assertSame('acquired', $innerResult);
    }

    public function test_assignment_lock_key_is_independent_of_mutation_lock(): void
    {
        $innerResult = null;

        TicketLock::assignment('t-assign')->get(function () use (&$innerResult): void {
            $innerResult = TicketLock::mutation('t-assign')->get(fn () => 'acquired');
        });

        $this->assertSame('acquired', $innerResult);
    }
}
