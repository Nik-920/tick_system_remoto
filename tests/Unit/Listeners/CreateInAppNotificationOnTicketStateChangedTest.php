<?php

namespace Tests\Unit\Listeners;

use App\Events\TicketStateChanged;
use App\Listeners\CreateInAppNotificationOnTicketStateChanged;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Notifications\NotificationPayload;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Fase 4.2 — listener in-app de TicketStateChanged (separado del push FCM).
 */
class CreateInAppNotificationOnTicketStateChangedTest extends TestCase
{
    public function test_handle_notifies_reporter_when_actor_is_different(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-1');
        $actor = $this->makeUser('actor-99');
        $ticket = $this->makeTicket($reporter);

        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->once())
            ->method('notifyUser')
            ->with(
                $reporter,
                $this->callback(function (NotificationPayload $p) {
                    return $p->type === 'ticket_state_changed'
                        && is_string($p->title)
                        && is_string($p->body)
                        && is_string($p->url)
                        && is_string($p->icon)
                        && $p->ticketId === 'ticket-state-001'
                        && $p->dedupKey === 'ticket_state_changed:ticket-state-001:open:in_progress';
                })
            );

        (new CreateInAppNotificationOnTicketStateChanged($notif))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'in_progress'));
    }

    public function test_handle_skips_notification_when_reporter_is_actor(): void
    {
        Log::spy();

        $user = $this->makeUser('same-user');
        $ticket = $this->makeTicket($user);

        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->never())->method('notifyUser');

        (new CreateInAppNotificationOnTicketStateChanged($notif))
            ->handle(new TicketStateChanged($ticket, $user, 'open', 'resolved'));
    }

    #[DataProvider('stateLabelsProvider')]
    public function test_title_uses_correct_state_label(string $state, string $expectedEmoji): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-2');
        $actor = $this->makeUser('actor-2');
        $ticket = $this->makeTicket($reporter, 'Mi ticket');

        $capturedTitle = null;
        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyUser')
            ->willReturnCallback(function (User $u, NotificationPayload $p) use (&$capturedTitle) {
                $capturedTitle = $p->title;
            });

        (new CreateInAppNotificationOnTicketStateChanged($notif))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', $state));

        $this->assertStringContainsString($expectedEmoji, $capturedTitle ?? '');
        $this->assertStringContainsString('Mi ticket', $capturedTitle ?? '');
    }

    /** @return array<string, array{string, string}> */
    public static function stateLabelsProvider(): array
    {
        return [
            'open' => ['open', '🔔'],
            'in_progress' => ['in_progress', '🔧'],
            'resolved' => ['resolved', '✅'],
            'rejected' => ['rejected', '❌'],
            'desconocido' => ['otro_estado', '📋'],
        ];
    }

    public function test_handle_skips_when_reporter_is_null(): void
    {
        Log::spy();

        $actor = $this->makeUser('actor-99');
        $ticket = $this->makeTicket(null);

        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->never())->method('notifyUser');

        (new CreateInAppNotificationOnTicketStateChanged($notif))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'resolved'));

        $this->addToAssertionCount(1);
    }

    public function test_passes_ticket_id_for_dedup(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-3');
        $actor = $this->makeUser('actor-3');
        $ticket = $this->makeTicket($reporter);

        $capturedTicketId = 'unset';
        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyUser')
            ->willReturnCallback(function (User $u, NotificationPayload $p) use (&$capturedTicketId) {
                $capturedTicketId = $p->ticketId;
            });

        (new CreateInAppNotificationOnTicketStateChanged($notif))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'resolved'));

        $this->assertSame('ticket-state-001', $capturedTicketId);
    }

    public function test_exception_inside_handler_is_caught_and_logged(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-4');
        $actor = $this->makeUser('actor-4');
        $ticket = $this->makeTicket($reporter);

        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyUser')
            ->willThrowException(new \RuntimeException('DB error'));

        (new CreateInAppNotificationOnTicketStateChanged($notif))
            ->handle(new TicketStateChanged($ticket, $actor, 'open', 'resolved'));

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

    private function makeListener(): CreateInAppNotificationOnTicketStateChanged
    {
        return new CreateInAppNotificationOnTicketStateChanged(
            $this->createMock(NotificationService::class),
        );
    }

    private function makeUser(string $id): User
    {
        $user = new User;
        $user->id = $id;

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
}
