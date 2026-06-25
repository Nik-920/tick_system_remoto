<?php

namespace Tests\Unit\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketStateChanged;
use App\Listeners\SendFcmPushOnTicketStateChanged;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\FakePushNotificationProvider;
use Tests\TestCase;

/**
 * Fase 4.2 — listener push FCM de TicketStateChanged (separado del in-app).
 */
class SendFcmPushOnTicketStateChangedTest extends TestCase
{
    public function test_handle_pushes_to_reporter_when_actor_is_different(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-1');
        $actor = $this->makeUser('actor-99');
        $ticket = $this->makeTicket($reporter);

        $fake = new FakePushNotificationProvider;

        (new SendFcmPushOnTicketStateChanged($fake))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'in_progress'));

        $fake->assertSentToUser('reporter-1');
    }

    public function test_skips_push_when_reporter_is_actor(): void
    {
        Log::spy();

        $user = $this->makeUser('same-user');
        $ticket = $this->makeTicket($user);

        $fake = new FakePushNotificationProvider;

        (new SendFcmPushOnTicketStateChanged($fake))
            ->handle(new TicketStateChanged($ticket, $user, 'open', 'resolved'));

        $fake->assertNothingSent();
    }

    public function test_skips_push_when_reporter_is_null(): void
    {
        Log::spy();

        $actor = $this->makeUser('actor-99');
        $ticket = $this->makeTicket(null);

        $fake = new FakePushNotificationProvider;

        (new SendFcmPushOnTicketStateChanged($fake))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'resolved'));

        $fake->assertNothingSent();
    }

    public function test_push_data_contains_state_and_ticket_id(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-2');
        $actor = $this->makeUser('actor-2');
        $ticket = $this->makeTicket($reporter);

        $fake = new FakePushNotificationProvider;

        (new SendFcmPushOnTicketStateChanged($fake))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'resolved'));

        $data = $fake->sentToUsers[0]['data'] ?? [];
        $this->assertSame('ticket-state-001', $data['ticket_id'] ?? null);
        $this->assertSame('resolved', $data['state'] ?? null);
    }

    public function test_exception_inside_handler_is_caught_and_logged(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-3');
        $actor = $this->makeUser('actor-3');
        $ticket = $this->makeTicket($reporter);

        $fcm = $this->createMock(PushNotificationProvider::class);
        $fcm->method('sendToUser')
            ->willThrowException(new \RuntimeException('FCM error'));

        (new SendFcmPushOnTicketStateChanged($fcm))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'resolved'));

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'push FCM'));

        $this->addToAssertionCount(1);
    }

    // ── Maintenance assignee: push FCM al técnico asignado ──

    public function test_maintenance_assignee_receives_fcm_push_on_state_change(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-1');
        $actor = $this->makeUser('actor-m1');
        $assignee = $this->makeMaintenanceUser('assignee-m1');
        $ticket = $this->makeTicketWithAssignee($reporter, $assignee);

        $fake = new FakePushNotificationProvider;

        (new SendFcmPushOnTicketStateChanged($fake))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'in_progress'));

        $fake->assertSentToUser('assignee-m1');
    }

    public function test_both_reporter_and_maintenance_assignee_receive_push(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-both');
        $actor = $this->makeUser('actor-both');
        $assignee = $this->makeMaintenanceUser('assignee-both');
        $ticket = $this->makeTicketWithAssignee($reporter, $assignee);

        $fake = new FakePushNotificationProvider;

        (new SendFcmPushOnTicketStateChanged($fake))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'resolved'));

        $fake->assertSentToUser('reporter-both');
        $fake->assertSentToUser('assignee-both');
        $this->assertCount(2, $fake->sentToUsers);
    }

    public function test_no_self_push_when_actor_is_maintenance_assignee(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-self');
        $assignee = $this->makeMaintenanceUser('assignee-self');
        $ticket = $this->makeTicketWithAssignee($reporter, $assignee);

        $fake = new FakePushNotificationProvider;

        (new SendFcmPushOnTicketStateChanged($fake))
            ->handle(new TicketStateChanged($ticket, $assignee, 'open', 'in_progress'));

        $fake->assertNotSentToUser('assignee-self');
    }

    public function test_no_fcm_push_when_assignee_is_not_maintenance(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-nm');
        $actor = $this->makeUser('actor-nm');
        $assignee = $this->makeNonMaintenanceUser('assignee-nm');
        $ticket = $this->makeTicketWithAssignee($reporter, $assignee);

        $fake = new FakePushNotificationProvider;

        (new SendFcmPushOnTicketStateChanged($fake))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'in_progress'));

        $fake->assertNotSentToUser('assignee-nm');
    }

    public function test_fcm_data_for_assignee_contains_from_to_state_and_recipient_role(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-data');
        $actor = $this->makeUser('actor-data');
        $assignee = $this->makeMaintenanceUser('assignee-data');
        $ticket = $this->makeTicketWithAssignee($reporter, $assignee, 'Fuga de agua');

        $fake = new FakePushNotificationProvider;

        (new SendFcmPushOnTicketStateChanged($fake))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'resolved'));

        $assigneeEntry = collect($fake->sentToUsers)
            ->firstWhere(fn ($s) => $s['user']->id === 'assignee-data');

        $this->assertNotNull($assigneeEntry);
        $data = $assigneeEntry['data'];
        $this->assertSame('open', $data['from_state']);
        $this->assertSame('resolved', $data['to_state']);
        $this->assertSame('maintenance', $data['recipient_role']);
        $this->assertSame('ticket_state_changed', $data['type']);
    }

    public function test_exception_in_assignee_path_is_caught_independently(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-exc');
        $actor = $this->makeUser('actor-exc');
        $assignee = $this->makeMaintenanceUser('assignee-exc');
        $ticket = $this->makeTicketWithAssignee($reporter, $assignee);

        $callCount = 0;
        $fcm = $this->createMock(PushNotificationProvider::class);
        $fcm->method('sendToUser')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 2) {
                    throw new \RuntimeException('FCM assignee error');
                }
            });

        (new SendFcmPushOnTicketStateChanged($fcm))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'in_progress'));

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'técnico'));

        $this->addToAssertionCount(1);
    }

    // ── Queue contract: push usa queue 'notifications' con 1 intento ──

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->makeListener());
    }

    public function test_uses_notifications_queue(): void
    {
        $this->assertSame('notifications', $this->makeListener()->queue);
    }

    public function test_has_max_tries_of_one(): void
    {
        $this->assertSame(1, $this->makeListener()->tries);
    }

    public function test_after_commit_is_true(): void
    {
        $this->assertTrue($this->makeListener()->afterCommit);
    }

    // ── Helpers ──

    private function makeListener(): SendFcmPushOnTicketStateChanged
    {
        return new SendFcmPushOnTicketStateChanged(new FakePushNotificationProvider);
    }

    private function makeUser(string $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    private function makeMaintenanceUser(string $id): User
    {
        /** @phpstan-ignore-next-line */
        $user = $this->createPartialMock(User::class, ['hasRole']);
        $user->id = $id;
        /** @phpstan-ignore-next-line */
        $user->method('hasRole')->willReturnCallback(fn ($role) => $role === 'maintenance');

        return $user;
    }

    private function makeNonMaintenanceUser(string $id): User
    {
        /** @phpstan-ignore-next-line */
        $user = $this->createPartialMock(User::class, ['hasRole']);
        $user->id = $id;
        /** @phpstan-ignore-next-line */
        $user->method('hasRole')->willReturn(false);

        return $user;
    }

    private function makeTicket(?User $reporter, string $title = 'Ticket de prueba'): Ticket
    {
        $ticket = new Ticket;
        $ticket->id = 'ticket-state-001';
        $ticket->title = $title;
        $ticket->setRelation('reporter', $reporter);

        return $ticket;
    }

    private function makeTicketWithAssignee(?User $reporter, User $assignee, string $title = 'Ticket de prueba'): Ticket
    {
        $ticket = $this->makeTicket($reporter, $title);
        $ticket->setRelation('assignee', $assignee);

        return $ticket;
    }
}
