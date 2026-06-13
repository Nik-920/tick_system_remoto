<?php

namespace Tests\Unit\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketCreated;
use App\Listeners\SendFcmPushOnTicketCreated;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Tests\Fakes\FakePushNotificationProvider;
use Tests\TestCase;

/**
 * Fase 4.1 — listener push FCM de TicketCreated (separado del in-app).
 * Responsabilidad única: enviar el push FCM a los roles admin.
 */
class SendFcmPushOnTicketCreatedTest extends TestCase
{
    public function test_handle_sends_push_to_admin_roles(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;

        (new SendFcmPushOnTicketCreated($fake))->handle(new TicketCreated($this->makeTicket(), 'corr-1'));

        $fake->assertSentToRole('admin');
        $fake->assertSentToRole('super_admin');
    }

    public function test_push_body_contains_location_and_category(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;

        $ticket = $this->makeTicket(
            title: 'Fuga de agua',
            locationName: 'Edificio A',
            categoryName: 'Infraestructura',
        );

        (new SendFcmPushOnTicketCreated($fake))->handle(new TicketCreated($ticket));

        $body = $fake->sentToRoles[0]['body'] ?? '';
        $this->assertStringContainsString('Fuga de agua', $body);
        $this->assertStringContainsString('Edificio A', $body);
        $this->assertStringContainsString('Infraestructura', $body);
    }

    public function test_push_data_contains_ticket_id_and_type(): void
    {
        Log::spy();

        $fake = new FakePushNotificationProvider;

        (new SendFcmPushOnTicketCreated($fake))->handle(new TicketCreated($this->makeTicket()));

        $data = $fake->sentToRoles[0]['data'] ?? [];
        $this->assertSame('ticket-uuid-001', $data['ticket_id'] ?? null);
        $this->assertSame('ticket_created', $data['type'] ?? null);
    }

    public function test_exception_inside_handler_is_caught_and_logged(): void
    {
        Log::spy();

        $fcm = $this->createMock(PushNotificationProvider::class);
        $fcm->method('sendToRoles')
            ->willThrowException(new \RuntimeException('FCM exploded'));

        (new SendFcmPushOnTicketCreated($fcm))->handle(new TicketCreated($this->makeTicket()));

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

    private function makeListener(): SendFcmPushOnTicketCreated
    {
        return new SendFcmPushOnTicketCreated(new FakePushNotificationProvider);
    }

    private function makeTicket(
        string $title = 'Ticket de prueba',
        ?string $locationName = 'Sala de reuniones',
        ?string $categoryName = 'Eléctrico',
    ): Ticket {
        $ticket = new Ticket;
        $ticket->id = 'ticket-uuid-001';
        $ticket->title = $title;

        $ticket->setRelation('location', $locationName !== null ? (object) ['name' => $locationName] : null);
        $ticket->setRelation('category', $categoryName !== null ? (object) ['name' => $categoryName] : null);

        return $ticket;
    }
}
