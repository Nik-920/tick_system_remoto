<?php

namespace App\Listeners;

use App\Events\TicketStateChanged;
use App\Listeners\Concerns\BuildsTicketStateChangedNotification;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persiste la notificación in-app al reporter cuando cambia el estado del ticket.
 *
 * Queue 'default' con 3 intentos: escritura in-app idempotente (Fase 5.2).
 */
class CreateInAppNotificationOnTicketStateChanged implements ShouldQueue
{
    use BuildsTicketStateChangedNotification;
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $tries = 3;
    public bool $afterCommit = true;

    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(TicketStateChanged $event): void
    {
        try {
            $n = $this->buildTicketStateChangedNotification($event);
            if ($n === null) {
                return;
            }

            $this->notificationService->notifyUser(
                user: $n['user'],
                type: $n['type'],
                title: $n['title'],
                body: $n['body'],
                url: $n['url'],
                icon: $n['icon'],
                ticketId: $n['ticketId'],
                dedupKey: $n['dedupKey'],
            );
        } catch (Throwable $e) {
            Log::error('Error creando notificación in-app en cambio de estado.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
