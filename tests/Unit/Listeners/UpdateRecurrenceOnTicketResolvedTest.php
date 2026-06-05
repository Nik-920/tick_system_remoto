<?php

namespace Tests\Unit\Listeners;

use App\Events\TicketResolved;
use App\Jobs\UpdateRecurrenceHistory;
use App\Listeners\UpdateRecurrenceOnTicketResolved;
use App\Models\Ticket;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class UpdateRecurrenceOnTicketResolvedTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // Caso normal: recurrence habilitado, ticket resuelto (state),
    // async → dispatch
    // ──────────────────────────────────────────────────────────

    public function test_dispatches_job_async_when_recurrence_enabled_and_ticket_resolved_by_state(): void
    {
        Bus::fake();
        config([
            'ai.recurrence.enabled'         => true,
            'ai.automation.async_processing' => true,
        ]);

        $ticket = $this->makeTicket(state: 'resolved', resolvedAt: now());
        $event  = new TicketResolved($ticket, 'corr-rec-001');

        (new UpdateRecurrenceOnTicketResolved)->handle($event);

        Bus::assertDispatched(UpdateRecurrenceHistory::class, function (UpdateRecurrenceHistory $job) use ($ticket): bool {
            return $job->ticket->id === $ticket->id
                && $job->correlationId === 'corr-rec-001';
        });
    }

    // ──────────────────────────────────────────────────────────
    // async_processing = false → dispatchSync, no async
    // ──────────────────────────────────────────────────────────

    public function test_dispatches_job_sync_when_async_processing_disabled(): void
    {
        Bus::fake();
        config([
            'ai.recurrence.enabled'         => true,
            'ai.automation.async_processing' => false,
        ]);

        $ticket = $this->makeTicket(state: 'resolved', resolvedAt: now());
        $event  = new TicketResolved($ticket, 'corr-rec-sync-001');

        (new UpdateRecurrenceOnTicketResolved)->handle($event);

        Bus::assertDispatchedSync(UpdateRecurrenceHistory::class, function (UpdateRecurrenceHistory $job) use ($ticket): bool {
            return $job->ticket->id === $ticket->id
                && $job->correlationId === 'corr-rec-sync-001';
        });
    }

    // ──────────────────────────────────────────────────────────
    // ai.recurrence.enabled = false → early return, sin dispatch
    // ──────────────────────────────────────────────────────────

    public function test_does_nothing_when_recurrence_disabled(): void
    {
        Bus::fake();
        config(['ai.recurrence.enabled' => false]);

        $ticket = $this->makeTicket(state: 'resolved', resolvedAt: now());
        $event  = new TicketResolved($ticket, 'corr-rec-002');

        (new UpdateRecurrenceOnTicketResolved)->handle($event);

        Bus::assertNothingDispatched();
    }

    // ──────────────────────────────────────────────────────────
    // Guard: state != 'resolved' AND resolved_at == null → early return
    // (el guard del listener es: state !== 'resolved' && resolved_at === null)
    // ──────────────────────────────────────────────────────────

    public function test_does_nothing_when_ticket_not_resolved_and_resolved_at_is_null(): void
    {
        Bus::fake();
        config([
            'ai.recurrence.enabled'         => true,
            'ai.automation.async_processing' => true,
        ]);

        $ticket = $this->makeTicket(state: 'in_progress', resolvedAt: null);
        $event  = new TicketResolved($ticket, 'corr-rec-003');

        (new UpdateRecurrenceOnTicketResolved)->handle($event);

        Bus::assertNothingDispatched();
    }

    // ──────────────────────────────────────────────────────────
    // Guard edge case: state = 'open' pero resolved_at != null
    // El guard es AND, por tanto resolved_at != null lo hace pasar
    // ──────────────────────────────────────────────────────────

    public function test_dispatches_when_resolved_at_is_set_even_if_state_is_not_resolved(): void
    {
        Bus::fake();
        config([
            'ai.recurrence.enabled'         => true,
            'ai.automation.async_processing' => true,
        ]);

        // resolved_at presente pero state != 'resolved' — el guard (&&) lo deja pasar
        $ticket = $this->makeTicket(state: 'open', resolvedAt: now());
        $event  = new TicketResolved($ticket, 'corr-rec-004');

        (new UpdateRecurrenceOnTicketResolved)->handle($event);

        Bus::assertDispatched(UpdateRecurrenceHistory::class);
    }

    // ──────────────────────────────────────────────────────────
    // Guard edge case: state = 'resolved' pero resolved_at == null
    // El guard es AND, por tanto state='resolved' lo deja pasar
    // ──────────────────────────────────────────────────────────

    public function test_dispatches_when_state_is_resolved_even_if_resolved_at_is_null(): void
    {
        Bus::fake();
        config([
            'ai.recurrence.enabled'         => true,
            'ai.automation.async_processing' => true,
        ]);

        $ticket = $this->makeTicket(state: 'resolved', resolvedAt: null);
        $event  = new TicketResolved($ticket, 'corr-rec-005');

        (new UpdateRecurrenceOnTicketResolved)->handle($event);

        Bus::assertDispatched(UpdateRecurrenceHistory::class);
    }

    // ──────────────────────────────────────────────────────────
    // correlationId se propaga al job
    // ──────────────────────────────────────────────────────────

    public function test_propagates_correlation_id_to_dispatched_job(): void
    {
        Bus::fake();
        config([
            'ai.recurrence.enabled'         => true,
            'ai.automation.async_processing' => true,
        ]);

        $ticket = $this->makeTicket(state: 'resolved', resolvedAt: now());
        $event  = new TicketResolved($ticket, 'corr-rec-propagate-001');

        (new UpdateRecurrenceOnTicketResolved)->handle($event);

        Bus::assertDispatched(UpdateRecurrenceHistory::class, function (UpdateRecurrenceHistory $job): bool {
            return $job->correlationId === 'corr-rec-propagate-001';
        });
    }

    // ──────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────

    private function makeTicket(string $state, ?\DateTimeInterface $resolvedAt): Ticket
    {
        $ticket              = new Ticket;
        $ticket->id          = 'ticket-rec-' . uniqid();
        $ticket->state       = $state;
        $ticket->resolved_at = $resolvedAt;

        return $ticket;
    }
}
