<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketResolved;
use App\Events\TicketStateChanged;
use App\Services\Cache\DashboardCache;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Synchronous subscriber: bumps the per-reporter version key whenever a ticket
 * event may change the reporter's dashboard data (chips, recent list, summary).
 *
 * Synchronous because the operation is a cheap cache-key write and we want the
 * invalidation to be visible on the very next request. No ShouldQueue.
 *
 * Failure policy: logs a warning and swallows the exception so a cache-write
 * failure never propagates into the business flow.
 *
 * Does NOT invalidate on notification events — those belong to the notification
 * cache layer (NotificationService / NotificationController), not dashboards.
 */
class InvalidateDashboardCacheOnTicketChanged
{
    public function __construct(private readonly DashboardCache $cache) {}

    public function handleTicketCreated(TicketCreated $event): void
    {
        $this->invalidateReporter((string) $event->ticket->reporter_id);
    }

    public function handleTicketStateChanged(TicketStateChanged $event): void
    {
        $this->invalidateReporter((string) $event->ticket->reporter_id);
    }

    public function handleTicketAssigned(TicketAssigned $event): void
    {
        $this->invalidateReporter((string) $event->ticket->reporter_id);
    }

    public function handleTicketResolved(TicketResolved $event): void
    {
        $this->invalidateReporter((string) $event->ticket->reporter_id);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(TicketCreated::class, [self::class, 'handleTicketCreated']);
        $events->listen(TicketStateChanged::class, [self::class, 'handleTicketStateChanged']);
        $events->listen(TicketAssigned::class, [self::class, 'handleTicketAssigned']);
        $events->listen(TicketResolved::class, [self::class, 'handleTicketResolved']);
    }

    private function invalidateReporter(string $userId): void
    {
        try {
            $this->cache->invalidateReporter($userId);
        } catch (Throwable $e) {
            Log::warning('InvalidateDashboardCacheOnTicketChanged: invalidation failed.', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
