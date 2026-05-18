<?php

namespace Tests\Unit\Listeners;

use App\Events\TicketStateChanged;
use App\Listeners\SendPushNotificationOnTicketStateChanged;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Firebase\FcmNotificationService;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SendPushNotificationOnTicketStateChangedTest extends TestCase
{
    // ──────────────────────────────────────────────
    // Escenario normal: reporter != actor → notificar
    // ──────────────────────────────────────────────

    public function test_handle_notifies_reporter_when_actor_is_different(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-1');
        $actor = $this->makeUser('actor-99');
        $ticket = $this->makeTicket($reporter);

        $fcm = $this->createMock(FcmNotificationService::class);
        $fcm->expects($this->once())
            ->method('sendToUser')
            ->with(
                user: $reporter,
                title: $this->isType('string'),
                body: $this->isType('string'),
                data: $this->arrayHasKey('ticket_id'),
            );

        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->once())
            ->method('notifyUser')
            ->with(
                user: $reporter,
                type: 'ticket_state_changed',
                title: $this->isType('string'),
                body: $this->isType('string'),
                url: $this->isType('string'),
                icon: $this->isType('string'),
            );

        $event = new TicketStateChanged($ticket, $actor, 'open', 'in_progress');
        $listener = new SendPushNotificationOnTicketStateChanged($fcm, $notif);
        $listener->handle($event);
    }

    // ──────────────────────────────────────────────
    // Reporter == actor → NO se notifica
    // ──────────────────────────────────────────────

    public function test_handle_skips_notification_when_reporter_is_actor(): void
    {
        Log::spy();

        $user = $this->makeUser('same-user');
        $ticket = $this->makeTicket($user);

        $fcm = $this->createMock(FcmNotificationService::class);
        $fcm->expects($this->never())->method('sendToUser');

        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->never())->method('notifyUser');

        $event = new TicketStateChanged($ticket, $user, 'open', 'resolved');
        $listener = new SendPushNotificationOnTicketStateChanged($fcm, $notif);
        $listener->handle($event);
    }

    // ──────────────────────────────────────────────
    // El título incluye la etiqueta del estado
    // ──────────────────────────────────────────────

    /**
     * @dataProvider stateLabelsProvider
     */
    public function test_title_uses_correct_state_label(string $state, string $expectedEmoji): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-2');
        $actor = $this->makeUser('actor-2');
        $ticket = $this->makeTicket($reporter, 'Mi ticket');

        $capturedTitle = null;

        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyUser')
            ->willReturnCallback(function (User $u, string $type, string $title) use (&$capturedTitle) {
                $capturedTitle = $title;
            });

        $fcm = $this->createMock(FcmNotificationService::class);
        $fcm->method('sendToUser');

        $event = new TicketStateChanged($ticket, $actor, 'open', $state);
        $listener = new SendPushNotificationOnTicketStateChanged($fcm, $notif);
        $listener->handle($event);

        $this->assertStringContainsString($expectedEmoji, $capturedTitle ?? '');
        $this->assertStringContainsString('Mi ticket', $capturedTitle ?? '');
    }

    /** @return array<string, array{string, string}> */
    public static function stateLabelsProvider(): array
    {
        return [
            'open' => ['open',        '🔔'],
            'in_progress' => ['in_progress', '🔧'],
            'resolved' => ['resolved',    '✅'],
            'rejected' => ['rejected',    '❌'],
            'desconocido' => ['otro_estado', '📋'],
        ];
    }

    // ──────────────────────────────────────────────
    // Reporter no es un User real → salida temprana
    // ──────────────────────────────────────────────

    public function test_handle_skips_when_reporter_is_null(): void
    {
        Log::spy();

        $actor = $this->makeUser('actor-99');
        $ticket = $this->makeTicket(null);   // sin reporter

        $fcm = $this->createMock(FcmNotificationService::class);
        $fcm->expects($this->never())->method('sendToUser');

        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->never())->method('notifyUser');

        $event = new TicketStateChanged($ticket, $actor, 'open', 'resolved');
        $listener = new SendPushNotificationOnTicketStateChanged($fcm, $notif);
        $listener->handle($event);

        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    // Excepción interna no se propaga
    // ──────────────────────────────────────────────

    public function test_exception_inside_handler_is_caught_and_logged(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter-3');
        $actor = $this->makeUser('actor-3');
        $ticket = $this->makeTicket($reporter);

        $fcm = $this->createMock(FcmNotificationService::class);
        $fcm->method('sendToUser')
            ->willThrowException(new \RuntimeException('FCM error'));

        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyUser');

        $event = new TicketStateChanged($ticket, $actor, 'open', 'resolved');
        $listener = new SendPushNotificationOnTicketStateChanged($fcm, $notif);
        $listener->handle($event);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'notificación'));

        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

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
