<?php

namespace Tests\Unit\Listeners;

use App\Events\TicketAssigned;
use App\Listeners\CreateInAppNotificationOnTicketAssigned;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Notifications\NotificationPayload;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Fase 4.3 — listener in-app de TicketAssigned (separado del push FCM).
 * Cubre la resolución de destinatario por acción (assigned/reassigned/unassigned)
 * y los gaps intencionales (claimed/released).
 */
class CreateInAppNotificationOnTicketAssignedTest extends TestCase
{
    public function test_assigned_action_notifies_new_assignee(): void
    {
        $this->assertNotifiesUser(
            event: new TicketAssigned($this->makeTicket(), $this->makeUser('actor-1'), null, $this->makeUser('assignee-1'), 'assigned'),
            expectedUserId: 'assignee-1',
        );
    }

    public function test_reassigned_action_notifies_new_assignee(): void
    {
        $this->assertNotifiesUser(
            event: new TicketAssigned($this->makeTicket(), $this->makeUser('actor-2'), $this->makeUser('prev-2'), $this->makeUser('new-2'), 'reassigned'),
            expectedUserId: 'new-2',
        );
    }

    public function test_unassigned_action_notifies_previous_assignee(): void
    {
        $this->assertNotifiesUser(
            event: new TicketAssigned($this->makeTicket(), $this->makeUser('actor-3'), $this->makeUser('prev-3'), null, 'unassigned'),
            expectedUserId: 'prev-3',
        );
    }

    public function test_claimed_action_does_nothing(): void
    {
        $this->assertNotifiesNobody(
            new TicketAssigned($this->makeTicket(), $this->makeUser('actor-c'), null, $this->makeUser('assignee-c'), 'claimed')
        );
    }

    public function test_released_action_does_nothing(): void
    {
        $this->assertNotifiesNobody(
            new TicketAssigned($this->makeTicket(), $this->makeUser('actor-r'), $this->makeUser('prev-r'), null, 'released')
        );
    }

    public function test_unknown_action_does_nothing(): void
    {
        $this->assertNotifiesNobody(
            new TicketAssigned($this->makeTicket(), $this->makeUser('actor-u'), null, null, 'unknown_action')
        );
    }

    public function test_null_target_does_nothing(): void
    {
        // assigned pero newAssignee = null → target null → early return
        $this->assertNotifiesNobody(
            new TicketAssigned($this->makeTicket(), $this->makeUser('actor-n'), null, null, 'assigned')
        );
    }

    public function test_passes_ticket_id_for_dedup(): void
    {
        Log::spy();

        $capturedTicketId = 'unset';
        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyUser')
            ->willReturnCallback(function (User $u, NotificationPayload $p) use (&$capturedTicketId) {
                $capturedTicketId = $p->ticketId;
            });

        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-1'), null, $this->makeUser('assignee-1'), 'assigned');
        (new CreateInAppNotificationOnTicketAssigned($notif))->handle($event);

        $this->assertSame('ticket-assign-001', $capturedTicketId);
    }

    public function test_passes_dedup_key_with_type_ticket_and_action(): void
    {
        Log::spy();

        $capturedDedupKey = 'unset';
        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyUser')
            ->willReturnCallback(function (User $u, NotificationPayload $p) use (&$capturedDedupKey) {
                $capturedDedupKey = $p->dedupKey;
            });

        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-1'), $this->makeUser('prev-1'), $this->makeUser('new-1'), 'reassigned');
        (new CreateInAppNotificationOnTicketAssigned($notif))->handle($event);

        $this->assertSame('ticket_reassigned:ticket-assign-001:reassigned', $capturedDedupKey);
    }

    public function test_exception_is_caught_and_logged(): void
    {
        Log::spy();

        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyUser')
            ->willThrowException(new \RuntimeException('DB failed'));

        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-6'), null, $this->makeUser('assignee-6'), 'assigned');
        (new CreateInAppNotificationOnTicketAssigned($notif))->handle($event);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'in-app'));

        $this->addToAssertionCount(1);
    }

    // ── Queue contract: in-app usa queue 'default' con 3 intentos ──

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->makeListener());
    }

    public function test_uses_default_queue(): void
    {
        $this->assertSame('default', $this->makeListener()->queue);
    }

    public function test_has_max_tries_of_three(): void
    {
        $this->assertSame(3, $this->makeListener()->tries);
    }

    public function test_after_commit_is_true(): void
    {
        $this->assertTrue($this->makeListener()->afterCommit);
    }

    // ── Helpers ──

    private function assertNotifiesUser(TicketAssigned $event, string $expectedUserId): void
    {
        Log::spy();

        $capturedUserId = null;
        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->once())
            ->method('notifyUser')
            ->willReturnCallback(function (User $u, NotificationPayload $p) use (&$capturedUserId) {
                $capturedUserId = $u->id;
            });

        (new CreateInAppNotificationOnTicketAssigned($notif))->handle($event);

        $this->assertSame($expectedUserId, $capturedUserId);
    }

    private function assertNotifiesNobody(TicketAssigned $event): void
    {
        Log::spy();

        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->never())->method('notifyUser');

        (new CreateInAppNotificationOnTicketAssigned($notif))->handle($event);

        $this->addToAssertionCount(1);
    }

    private function makeListener(): CreateInAppNotificationOnTicketAssigned
    {
        return new CreateInAppNotificationOnTicketAssigned(
            $this->createMock(NotificationService::class),
        );
    }

    private function makeTicket(string $title = 'Ticket asignación'): Ticket
    {
        $ticket = new Ticket;
        $ticket->id = 'ticket-assign-001';
        $ticket->title = $title;

        return $ticket;
    }

    private function makeUser(string $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }
}
