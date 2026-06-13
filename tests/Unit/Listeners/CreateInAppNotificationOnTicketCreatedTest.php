<?php

namespace Tests\Unit\Listeners;

use App\Events\TicketCreated;
use App\Listeners\CreateInAppNotificationOnTicketCreated;
use App\Models\Ticket;
use App\Services\Notifications\NotificationPayload;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Fase 4.1 — listener in-app de TicketCreated (separado del push FCM).
 * Responsabilidad única: persistir la notificación in-app a admins.
 */
class CreateInAppNotificationOnTicketCreatedTest extends TestCase
{
    public function test_handle_creates_in_app_notification_for_admins(): void
    {
        Log::spy();

        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->once())
            ->method('notifyAdmins')
            ->with($this->callback(function (NotificationPayload $p) {
                return $p->type === 'ticket_created'
                    && str_contains($p->title, 'Nuevo ticket')
                    && is_string($p->body)
                    && is_string($p->url)
                    && $p->icon === '🎫'
                    && $p->ticketId === 'ticket-uuid-001'
                    && $p->dedupKey === 'ticket_created:ticket-uuid-001';
            }));

        $listener = new CreateInAppNotificationOnTicketCreated($notif);
        $listener->handle(new TicketCreated($this->makeTicket(), 'corr-test-001'));
    }

    public function test_notification_body_contains_location_and_category(): void
    {
        Log::spy();

        $capturedBody = null;
        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyAdmins')
            ->willReturnCallback(function (NotificationPayload $p) use (&$capturedBody) {
                $capturedBody = $p->body;
            });

        $ticket = $this->makeTicket(
            title: 'Fuga de agua',
            locationName: 'Edificio A',
            categoryName: 'Infraestructura',
        );

        (new CreateInAppNotificationOnTicketCreated($notif))->handle(new TicketCreated($ticket));

        $this->assertStringContainsString('Fuga de agua', $capturedBody ?? '');
        $this->assertStringContainsString('Edificio A', $capturedBody ?? '');
        $this->assertStringContainsString('Infraestructura', $capturedBody ?? '');
    }

    public function test_handle_uses_fallback_when_location_and_category_are_null(): void
    {
        Log::spy();

        $capturedBody = null;
        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyAdmins')
            ->willReturnCallback(function (NotificationPayload $p) use (&$capturedBody) {
                $capturedBody = $p->body;
            });

        $ticket = $this->makeTicket(title: 'Sin relaciones', locationName: null, categoryName: null);

        (new CreateInAppNotificationOnTicketCreated($notif))->handle(new TicketCreated($ticket));

        $this->assertStringContainsString('Ubicación desconocida', $capturedBody ?? '');
        $this->assertStringContainsString('Sin categoría', $capturedBody ?? '');
    }

    public function test_passes_ticket_id_for_dedup(): void
    {
        Log::spy();

        $capturedTicketId = 'unset';
        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyAdmins')
            ->willReturnCallback(function (NotificationPayload $p) use (&$capturedTicketId) {
                $capturedTicketId = $p->ticketId;
            });

        (new CreateInAppNotificationOnTicketCreated($notif))->handle(new TicketCreated($this->makeTicket()));

        $this->assertSame('ticket-uuid-001', $capturedTicketId);
    }

    public function test_passes_dedup_key(): void
    {
        Log::spy();

        $capturedDedupKey = 'unset';
        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyAdmins')
            ->willReturnCallback(function (NotificationPayload $p) use (&$capturedDedupKey) {
                $capturedDedupKey = $p->dedupKey;
            });

        (new CreateInAppNotificationOnTicketCreated($notif))->handle(new TicketCreated($this->makeTicket()));

        $this->assertSame('ticket_created:ticket-uuid-001', $capturedDedupKey);
    }

    public function test_exception_inside_handler_is_caught_and_logged(): void
    {
        Log::spy();

        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyAdmins')
            ->willThrowException(new \RuntimeException('DB down'));

        (new CreateInAppNotificationOnTicketCreated($notif))->handle(new TicketCreated($this->makeTicket()));

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

    private function makeListener(): CreateInAppNotificationOnTicketCreated
    {
        return new CreateInAppNotificationOnTicketCreated(
            $this->createMock(NotificationService::class),
        );
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
