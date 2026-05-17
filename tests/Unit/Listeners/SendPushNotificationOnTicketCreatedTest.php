<?php

namespace Tests\Unit\Listeners;

use App\Events\TicketCreated;
use App\Listeners\SendPushNotificationOnTicketCreated;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Firebase\FcmNotificationService;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SendPushNotificationOnTicketCreatedTest extends TestCase
{
    // ──────────────────────────────────────────────
    // Escenario normal: llamadas esperadas
    // ──────────────────────────────────────────────

    public function test_handle_calls_notify_admins_and_fcm_send_to_roles(): void
    {
        Log::spy();

        $fcm = $this->createMock(FcmNotificationService::class);
        $fcm->expects($this->once())
            ->method('sendToRoles')
            ->with(
                roles: ['admin', 'super_admin'],
                title: $this->stringContains('Nuevo ticket'),
                body: $this->isType('string'),
                data: $this->arrayHasKey('ticket_id'),
            );

        $notif = $this->createMock(NotificationService::class);
        $notif->expects($this->once())
            ->method('notifyAdmins')
            ->with(
                type: 'ticket_created',
                title: $this->stringContains('Nuevo ticket'),
                body: $this->isType('string'),
                url: $this->isType('string'),
                icon: '🎫',
            );

        $ticket           = $this->makeTicket();
        $event            = new TicketCreated($ticket, 'corr-test-001');

        $listener = new SendPushNotificationOnTicketCreated($fcm, $notif);
        $listener->handle($event);
    }

    // ──────────────────────────────────────────────
    // El body incluye ubicación y categoría
    // ──────────────────────────────────────────────

    public function test_notification_body_contains_location_and_category(): void
    {
        Log::spy();

        $capturedBody = null;

        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyAdmins')
            ->willReturnCallback(function (string $type, string $title, string $body) use (&$capturedBody) {
                $capturedBody = $body;
            });

        $fcm = $this->createMock(FcmNotificationService::class);
        $fcm->method('sendToRoles');

        $ticket = $this->makeTicket(
            title: 'Fuga de agua',
            locationName: 'Edificio A',
            categoryName: 'Infraestructura',
        );

        $listener = new SendPushNotificationOnTicketCreated($fcm, $notif);
        $listener->handle(new TicketCreated($ticket));

        $this->assertStringContainsString('Fuga de agua', $capturedBody ?? '');
        $this->assertStringContainsString('Edificio A', $capturedBody ?? '');
        $this->assertStringContainsString('Infraestructura', $capturedBody ?? '');
    }

    // ──────────────────────────────────────────────
    // Fallback cuando location/category son null
    // ──────────────────────────────────────────────

    public function test_handle_uses_fallback_when_location_and_category_are_null(): void
    {
        Log::spy();

        $capturedBody = null;

        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyAdmins')
            ->willReturnCallback(function (string $type, string $title, string $body) use (&$capturedBody) {
                $capturedBody = $body;
            });

        $fcm = $this->createMock(FcmNotificationService::class);
        $fcm->method('sendToRoles');

        $ticket = $this->makeTicket(title: 'Sin relaciones', locationName: null, categoryName: null);

        $listener = new SendPushNotificationOnTicketCreated($fcm, $notif);
        $listener->handle(new TicketCreated($ticket));

        $this->assertStringContainsString('Ubicación desconocida', $capturedBody ?? '');
        $this->assertStringContainsString('Sin categoría', $capturedBody ?? '');
    }

    // ──────────────────────────────────────────────
    // Excepción interna NO se propaga al caller
    // ──────────────────────────────────────────────

    public function test_exception_inside_handler_is_caught_and_logged(): void
    {
        Log::spy();

        $fcm = $this->createMock(FcmNotificationService::class);
        $fcm->method('sendToRoles')
            ->willThrowException(new \RuntimeException('FCM exploded'));

        $notif = $this->createMock(NotificationService::class);
        $notif->method('notifyAdmins');

        $listener = new SendPushNotificationOnTicketCreated($fcm, $notif);
        $listener->handle(new TicketCreated($this->makeTicket()));

        // La excepción fue capturada internamente
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains($msg, 'notificación'));

        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    // Helper: construye un Ticket falso sin BD
    // ──────────────────────────────────────────────

    private function makeTicket(
        string $title = 'Ticket de prueba',
        ?string $locationName = 'Sala de reuniones',
        ?string $categoryName = 'Eléctrico',
    ): Ticket {
        $ticket = new Ticket;
        $ticket->id    = 'ticket-uuid-001';
        $ticket->title = $title;

        if ($locationName !== null) {
            $ticket->setRelation('location', (object) ['name' => $locationName]);
        } else {
            $ticket->setRelation('location', null);
        }

        if ($categoryName !== null) {
            $ticket->setRelation('category', (object) ['name' => $categoryName]);
        } else {
            $ticket->setRelation('category', null);
        }

        return $ticket;
    }
}
