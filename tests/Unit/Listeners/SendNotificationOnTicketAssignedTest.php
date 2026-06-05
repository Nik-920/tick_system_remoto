<?php

namespace Tests\Unit\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketAssigned;
use App\Listeners\SendNotificationOnTicketAssigned;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\FakePushNotificationProvider;
use Tests\TestCase;

class SendNotificationOnTicketAssignedTest extends TestCase
{
    // ──────────────────────────────────────────────
    // assigned → notifica al nuevo asignado
    // ──────────────────────────────────────────────

    public function test_assigned_action_notifies_new_assignee(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;
        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->once())->method('notifyUser');

        $ticket = $this->makeTicket();
        $actor = $this->makeUser('actor-1');
        $newAssignee = $this->makeUser('assignee-1');

        $event = new TicketAssigned($ticket, $actor, null, $newAssignee, 'assigned');
        (new SendNotificationOnTicketAssigned($fake, $notif))->handle($event);

        $fake->assertSentToUser('assignee-1');
    }

    // ──────────────────────────────────────────────
    // reassigned → notifica al nuevo asignado, no al anterior
    // ──────────────────────────────────────────────

    public function test_reassigned_action_notifies_new_assignee_not_previous(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;
        $notif = $this->createMock(NotificationService::class);

        $ticket = $this->makeTicket();
        $newAssignee = $this->makeUser('new-2');
        $prevAssignee = $this->makeUser('prev-2');

        $event = new TicketAssigned($ticket, $this->makeUser('actor-2'), $prevAssignee, $newAssignee, 'reassigned');
        (new SendNotificationOnTicketAssigned($fake, $notif))->handle($event);

        $fake->assertSentToUser('new-2');
        $fake->assertNotSentToUser('prev-2');
    }

    // ──────────────────────────────────────────────
    // unassigned → notifica al asignado anterior
    // ──────────────────────────────────────────────

    public function test_unassigned_action_notifies_previous_assignee(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;
        $notif = $this->createMock(NotificationService::class);

        $ticket = $this->makeTicket();
        $prevAssignee = $this->makeUser('prev-3');

        $event = new TicketAssigned($ticket, $this->makeUser('actor-3'), $prevAssignee, null, 'unassigned');
        (new SendNotificationOnTicketAssigned($fake, $notif))->handle($event);

        $fake->assertSentToUser('prev-3');
    }

    // ──────────────────────────────────────────────
    // Acción desconocida → no envía nada
    // ──────────────────────────────────────────────

    public function test_unknown_action_does_nothing(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;
        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->never())->method('notifyUser');

        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-4'), null, null, 'unknown_action');
        (new SendNotificationOnTicketAssigned($fake, $notif))->handle($event);

        $fake->assertNothingSent();
    }

    // ──────────────────────────────────────────────
    // Target null (assigned sin newAssignee) → salida temprana
    // ──────────────────────────────────────────────

    public function test_null_target_user_does_nothing(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;
        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->never())->method('notifyUser');

        // assigned pero newAssignee = null → target = null → early return
        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-5'), null, null, 'assigned');
        (new SendNotificationOnTicketAssigned($fake, $notif))->handle($event);

        $fake->assertNothingSent();
    }

    // ──────────────────────────────────────────────
    // Excepción interna → se captura y loguea
    // ──────────────────────────────────────────────

    public function test_exception_is_caught_and_logged(): void
    {
        Log::spy();

        $push = $this->createMock(PushNotificationProvider::class);
        $push->method('sendToUser')
            ->willThrowException(new \RuntimeException('Push failed'));

        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyUser');

        $newAssignee = $this->makeUser('assignee-6');
        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-6'), null, $newAssignee, 'assigned');
        (new SendNotificationOnTicketAssigned($push, $notif))->handle($event);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'asignación'));

        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

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
