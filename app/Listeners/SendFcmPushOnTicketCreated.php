<?php

namespace App\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketCreated;
use App\Listeners\Concerns\BuildsTicketCreatedNotification;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía el push FCM per-user a admins y super_admins cuando se crea un ticket.
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
        private PushNotificationProvider $fcm,
        private ?TicketNotificationPreferenceService $preferences = null,
    ) {}

    public function handle(TicketCreated $event): void
    {
        try {
            $n = $this->buildTicketCreatedNotification($event);

            $admins = User::role(['admin', 'super_admin'])->get()->unique('id');

            foreach ($admins as $admin) {
                if ($this->preferences !== null &&
                    ! $this->preferences->isEnabled($admin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_FCM)) {
                    continue;
                }

                $this->fcm->sendToUser(
                    user: $admin,
                    title: $n['title'],
                    body: $n['body'],
                    data: $n['data'],
                );
            }
        } catch (Throwable $e) {
            Log::error('Error enviando push FCM en ticket creado.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
