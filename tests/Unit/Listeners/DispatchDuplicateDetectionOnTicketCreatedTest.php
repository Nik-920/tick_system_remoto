<?php

namespace Tests\Unit\Listeners;

use App\Events\TicketCreated;
use App\Jobs\DetectDuplicates;
use App\Listeners\DispatchDuplicateDetectionOnTicketCreated;
use App\Models\Ticket;
use App\Services\Ai\DeduplicationService;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DispatchDuplicateDetectionOnTicketCreatedTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // Caso normal: dedup habilitado, async → dispatch
    // ──────────────────────────────────────────────────────────

    public function test_dispatches_job_async_when_deduplication_enabled(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.automation.async_processing' => true,
        ]);

        $ticket = $this->makeTicket();
        $event = new TicketCreated($ticket, 'corr-dedup-001');

        (new DispatchDuplicateDetectionOnTicketCreated($this->makeDeduplicationService()))->handle($event);

        Bus::assertDispatched(DetectDuplicates::class, function (DetectDuplicates $job) use ($ticket): bool {
            return $job->ticket->id === $ticket->id
                && $job->correlationId === 'corr-dedup-001';
        });
    }

    // ──────────────────────────────────────────────────────────
    // async_processing = false → dispatchSync, no async
    // ──────────────────────────────────────────────────────────

    public function test_dispatches_job_sync_when_async_processing_disabled(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.automation.async_processing' => false,
        ]);

        $ticket = $this->makeTicket();
        $event = new TicketCreated($ticket, 'corr-dedup-sync-001');

        (new DispatchDuplicateDetectionOnTicketCreated($this->makeDeduplicationService()))->handle($event);

        Bus::assertDispatchedSync(DetectDuplicates::class, function (DetectDuplicates $job) use ($ticket): bool {
            return $job->ticket->id === $ticket->id
                && $job->correlationId === 'corr-dedup-sync-001';
        });
    }

    // ──────────────────────────────────────────────────────────
    // ai.dedup.enabled = false → isEnabled() = false → early return
    // ──────────────────────────────────────────────────────────

    public function test_does_nothing_when_dedup_flag_disabled(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => false,
            'ai.automation.async_processing' => true,
        ]);

        $event = new TicketCreated($this->makeTicket(), 'corr-dedup-002');

        (new DispatchDuplicateDetectionOnTicketCreated($this->makeDeduplicationService()))->handle($event);

        Bus::assertNothingDispatched();
    }

    // ──────────────────────────────────────────────────────────
    // ai.enabled = false → isEnabled() = false → early return
    // ──────────────────────────────────────────────────────────

    public function test_does_nothing_when_ai_globally_disabled(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => false,
            'ai.dedup.enabled' => true,
            'ai.automation.async_processing' => true,
        ]);

        $event = new TicketCreated($this->makeTicket(), 'corr-dedup-003');

        (new DispatchDuplicateDetectionOnTicketCreated($this->makeDeduplicationService()))->handle($event);

        Bus::assertNothingDispatched();
    }

    // ──────────────────────────────────────────────────────────
    // correlationId se propaga al job
    // ──────────────────────────────────────────────────────────

    public function test_propagates_correlation_id_to_dispatched_job(): void
    {
        Bus::fake();
        config([
            'ai.enabled' => true,
            'ai.dedup.enabled' => true,
            'ai.automation.async_processing' => true,
        ]);

        $ticket = $this->makeTicket();
        $event = new TicketCreated($ticket, 'corr-dedup-propagate-001');

        (new DispatchDuplicateDetectionOnTicketCreated($this->makeDeduplicationService()))->handle($event);

        Bus::assertDispatched(DetectDuplicates::class, function (DetectDuplicates $job): bool {
            return $job->correlationId === 'corr-dedup-propagate-001';
        });
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function makeDeduplicationService(): DeduplicationService
    {
        return $this->app->make(DeduplicationService::class);
    }

    private function makeTicket(): Ticket
    {
        $ticket = new Ticket;
        $ticket->id = 'ticket-dedup-'.uniqid();
        $ticket->description = 'Descripcion para deteccion de duplicados';

        return $ticket;
    }
}
