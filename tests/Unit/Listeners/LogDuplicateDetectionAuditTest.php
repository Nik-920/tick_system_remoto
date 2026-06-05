<?php

namespace Tests\Unit\Listeners;

use App\Events\DuplicateDetected;
use App\Jobs\LogAiDecision;
use App\Jobs\WriteAiAuditLog;
use App\Listeners\LogDuplicateDetectionAudit;
use App\Models\Ticket;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogDuplicateDetectionAuditTest extends TestCase
{
    public function test_listener_dispatches_jobs_async_when_enabled(): void
    {
        config([
            'ai.automation.async_processing' => true,
            'ai.dedup.similarity_threshold' => 0.8,
        ]);

        Bus::fake();

        $ticket = new Ticket([
            'id' => 'ticket-1',
            'location_id' => 'loc-1',
            'category_id' => 'cat-1',
        ]);
        $matched = new Ticket(['id' => 'ticket-2']);
        $event = new DuplicateDetected($ticket, $matched, 0.91, 'corr-async-001');

        $listener = new LogDuplicateDetectionAudit;
        $listener->handle($event);

        Bus::assertDispatched(LogAiDecision::class, function (LogAiDecision $job): bool {
            return $job->ticket->id === 'ticket-1'
                && $job->operationType === 'semantic_dedup_check'
                && $job->correlationId === 'corr-async-001';
        });

        Bus::assertDispatched(WriteAiAuditLog::class, function (WriteAiAuditLog $job): bool {
            return $job->message === 'Duplicate ticket detected.'
                && ($job->context['matched_ticket_id'] ?? null) === 'ticket-2'
                && $job->correlationId === 'corr-async-001';
        });
    }

    public function test_listener_dispatches_jobs_sync_when_async_disabled(): void
    {
        config([
            'ai.automation.async_processing' => false,
            'ai.dedup.similarity_threshold' => 0.75,
        ]);

        Bus::fake();

        $ticket = new Ticket([
            'id' => 'ticket-1',
            'location_id' => 'loc-1',
            'category_id' => 'cat-1',
        ]);
        $matched = new Ticket(['id' => 'ticket-2']);
        $event = new DuplicateDetected($ticket, $matched, 0.82, 'corr-sync-001');

        $listener = new LogDuplicateDetectionAudit;
        $listener->handle($event);

        Bus::assertDispatchedSync(LogAiDecision::class, function (LogAiDecision $job): bool {
            return $job->ticket->id === 'ticket-1'
                && $job->operationType === 'semantic_dedup_check'
                && $job->correlationId === 'corr-sync-001';
        });

        Bus::assertDispatchedSync(WriteAiAuditLog::class, function (WriteAiAuditLog $job): bool {
            return $job->message === 'Duplicate ticket detected.'
                && $job->correlationId === 'corr-sync-001';
        });
    }

    public function test_dispatch_failure_is_caught_and_logged(): void
    {
        config(['ai.automation.async_processing' => true]);

        Bus::fake();
        Log::spy();

        // Make LogAiDecision::dispatch() throw by using a bus fake that
        // forwards the call but we override via a dispatch callback.
        // Simplest approach: make the dispatch throw via a custom expectation.
        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('Queue unavailable'));

        $ticket = new Ticket([
            'id'          => 'ticket-err',
            'location_id' => 'loc-1',
            'category_id' => 'cat-1',
        ]);
        $event = new DuplicateDetected($ticket, null, 0.85, 'corr-err-001');

        $listener = new LogDuplicateDetectionAudit;
        $listener->handle($event);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'audit'));

        $this->addToAssertionCount(1);
    }
}
