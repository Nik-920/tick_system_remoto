<?php

namespace App\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketCreated;
use App\Listeners\Concerns\BuildsTicketCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía el push FCM a los roles admin cuando se crea un ticket.
 *
 * Queue 'notifications' con 1 intento: un push FCM no tiene dedup key y no es
 * seguro reintentarlo (podría duplicar la notificación del dispositivo).
 */
class SendFcmPushOnTicketCreated implements ShouldQueue
{
    use BuildsTicketCreatedNotification;
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 1;

    public bool $afterCommit = true;

    public function __construct(
        private PushNotificationProvider $fcm
    ) {}

    public function handle(TicketCreated $event): void
    {
        try {
            $n = $this->buildTicketCreatedNotification($event);

            $this->fcm->sendToRoles(
                roles: $n['roles'],
                title: $n['title'],
                body: $n['body'],
                data: $n['data'],
            );
        } catch (Throwable $e) {
            Log::error('Error enviando push FCM en ticket creado.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
