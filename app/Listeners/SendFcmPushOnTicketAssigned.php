<?php

namespace App\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketAssigned;
use App\Listeners\Concerns\BuildsTicketAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía el push FCM al destinatario de una asignación.
 *
 * Queue 'notifications' con 1 intento: push FCM no reintenta sin dedup key.
 */
class SendFcmPushOnTicketAssigned implements ShouldQueue
{
    use BuildsTicketAssignedNotification;
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int $tries = 1;
    public bool $afterCommit = true;

    public function __construct(
        private PushNotificationProvider $fcm
    ) {}

    public function handle(TicketAssigned $event): void
    {
        try {
            $n = $this->buildTicketAssignedNotification($event);
            if ($n === null) {
                return;
            }

            $this->fcm->sendToUser(
                user: $n['user'],
                title: $n['title'],
                body: $n['body'],
                data: $n['data'],
            );
        } catch (Throwable $e) {
            Log::error('Error enviando push FCM de asignación.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
