<?php

namespace App\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketStateChanged;
use App\Listeners\Concerns\BuildsTicketStateChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía el push FCM al reporter cuando cambia el estado del ticket.
 *
 * Queue 'notifications' con 1 intento: push FCM no reintenta sin dedup key.
 */
class SendFcmPushOnTicketStateChanged implements ShouldQueue
{
    use BuildsTicketStateChangedNotification;
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 1;

    public bool $afterCommit = true;

    public function __construct(
        private PushNotificationProvider $fcm
    ) {}

    public function handle(TicketStateChanged $event): void
    {
        try {
            $n = $this->buildTicketStateChangedNotification($event);
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
            Log::error('Error enviando push FCM en cambio de estado.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
