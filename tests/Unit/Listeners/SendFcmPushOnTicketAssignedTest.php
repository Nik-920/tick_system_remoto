<?php

namespace Tests\Unit\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketAssigned;
use App\Listeners\SendFcmPushOnTicketAssigned;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\FakePushNotificationProvider;
use Tests\TestCase;

/**
 * Fase 4.3 — listener push FCM de TicketAssigned (separado del in-app).
 */
class SendFcmPushOnTicketAssignedTest extends TestCase
{
    public function test_assigned_action_pushes_to_new_assignee(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;
        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-1'), null, $this->makeUser('assignee-1'), 'assigned');

        (new SendFcmPushOnTicketAssigned($fake))->handle($event);

        $fake->assertSentToUser('assignee-1');
    }

    public function test_reassigned_pushes_to_new_assignee_not_previous(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;
        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-2'), $this->makeUser('prev-2'), $this->makeUser('new-2'), 'reassigned');

        (new SendFcmPushOnTicketAssigned($fake))->handle($event);

        $fake->assertSentToUser('new-2');
        $fake->assertNotSentToUser('prev-2');
    }

    public function test_claimed_action_sends_nothing(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;
        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-c'), null, $this->makeUser('assignee-c'), 'claimed');

        (new SendFcmPushOnTicketAssigned($fake))->handle($event);

        $fake->assertNothingSent();
    }

    public function test_released_action_sends_nothing(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;
        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-r'), $this->makeUser('prev-r'), null, 'released');

        (new SendFcmPushOnTicketAssigned($fake))->handle($event);

        $fake->assertNothingSent();
    }

    public function test_push_data_contains_action_and_correlation_id(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;
        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-1'), null, $this->makeUser('assignee-1'), 'assigned', 'corr-assign-1');

        (new SendFcmPushOnTicketAssigned($fake))->handle($event);

        $data = $fake->sentToUsers[0]['data'] ?? [];
        $this->assertSame('assigned', $data['action'] ?? null);
        $this->assertSame('corr-assign-1', $data['correlation_id'] ?? null);
        $this->assertSame('ticket-assign-001', $data['ticket_id'] ?? null);
    }

    public function test_exception_is_caught_and_logged(): void
    {
        Log::spy();

        $fcm = $this->createMock(PushNotificationProvider::class);
        $fcm->method('sendToUser')
            ->willThrowException(new \RuntimeException('Push failed'));

        $event = new TicketAssigned($this->makeTicket(), $this->makeUser('actor-6'), null, $this->makeUser('assignee-6'), 'assigned');
        (new SendFcmPushOnTicketAssigned($fcm))->handle($event);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'push FCM'));

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

    private function makeListener(): SendFcmPushOnTicketAssigned
    {
        return new SendFcmPushOnTicketAssigned(new FakePushNotificationProvider);
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
