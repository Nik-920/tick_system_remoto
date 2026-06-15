<?php

declare(strict_types=1);

namespace App\Support\Locks;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Distributed Redis lock for critical ticket mutations.
 *
 * Complements the PostgreSQL row-level locks already present in
 * TicketAssignmentService (SELECT FOR UPDATE). Redis locks coordinate
 * across processes before the DB transaction begins, preventing redundant
 * work and race conditions that would otherwise surface as DB constraint
 * violations or inconsistent state.
 *
 * Usage:
 *   TicketLock::mutation($ticket->id)->block(3, $callback);
 *   TicketLock::state($ticket->id)->get($callback);
 */
class TicketLock
{
    private const PREFIX = 'tick:ticket:';

    private function __construct(
        private readonly string $key,
        private readonly int $ttlSeconds,
    ) {}

    public static function mutation(string $ticketId): self
    {
        return new self(self::PREFIX."{$ticketId}:mutation", 15);
    }

    public static function state(string $ticketId): self
    {
        return new self(self::PREFIX."{$ticketId}:state", 15);
    }

    public static function assignment(string $ticketId): self
    {
        return new self(self::PREFIX."{$ticketId}:assignment", 15);
    }

    public static function duplicateDetection(string $ticketId): self
    {
        return new self(self::PREFIX."{$ticketId}:duplicate-detection", 120);
    }

    public static function embedding(string $ticketId): self
    {
        return new self(self::PREFIX."{$ticketId}:embedding", 120);
    }

    /**
     * Acquire the lock immediately and run the callback, or return null if
     * the lock is already held. The lock is released after the callback.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T|null — null means the lock was NOT acquired (not the callback's return)
     */
    public function get(Closure $callback): mixed
    {
        $lock = Cache::lock($this->key, $this->ttlSeconds);

        if (! $lock->get()) {
            return null;
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Wait up to $waitSeconds for the lock, then run the callback.
     * Throws LockTimeoutException if the lock cannot be acquired in time.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws LockTimeoutException
     */
    public function block(int $waitSeconds, Closure $callback): mixed
    {
        return Cache::lock($this->key, $this->ttlSeconds)->block($waitSeconds, $callback);
    }
}
