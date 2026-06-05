<?php

namespace App\Listeners;

use App\Events\TicketAssigned;
use App\Listeners\Concerns\BuildsTicketAssignedNotification;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persiste la notificación in-app al destinatario de una asignación.
 *
 * Queue 'default' con 3 intentos: escritura in-app idempotente (Fase 5.2).
 */
class CreateInAppNotificationOnTicketAssigned implements ShouldQueue
{
    use BuildsTicketAssignedNotification;
    use InteractsWithQueue;

    public string $queue = 'default';
    public int $tries = 3;
    public bool $afterCommit = true;

    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(TicketAssigned $event): void
    {
        try {
            $n = $this->buildTicketAssignedNotification($event);
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
            Log::error('Error creando notificación in-app de asignación.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
