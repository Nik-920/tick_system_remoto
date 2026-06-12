<?php

namespace App\Listeners;

use App\Events\TicketStateChanged;
use App\Listeners\Concerns\BuildsTicketStateChangedNotification;
use App\Listeners\Concerns\DeliversTicketInAppNotification;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Persiste la notificación in-app al reporter cuando cambia el estado del ticket.
 *
 * Queue 'default' con 3 intentos: escritura in-app idempotente (Fase 5.2).
 */
class CreateInAppNotificationOnTicketStateChanged implements ShouldQueue
{
    use BuildsTicketStateChangedNotification;
    use DeliversTicketInAppNotification;
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    public bool $afterCommit = true;

    public function __construct(
        private NotificationService $notificationService
    ) {}

    protected function notificationService(): NotificationService
    {
        return $this->notificationService;
    }

    public function handle(TicketStateChanged $event): void
    {
        $this->deliverInAppNotification(
            fn (): ?array => $this->buildTicketStateChangedNotification($event),
            'Error creando notificación in-app en cambio de estado.',
            (string) $event->ticket->id,
        );
    }
}
