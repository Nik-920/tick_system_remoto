<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Listeners\Concerns\BuildsTicketCreatedNotification;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\NotificationPayload;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persiste la notificación in-app a los administradores cuando se crea un ticket.
 *
 * Queue 'default' con 3 intentos: la escritura in-app es una operación de BD
 * idempotente (Fase 5.2) y segura de reintentar.
 */
class CreateInAppNotificationOnTicketCreated implements ShouldQueue
{
    use BuildsTicketCreatedNotification;
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    public bool $afterCommit = true;

    public function __construct(
        private NotificationService $notificationService,
        private ?TicketNotificationPreferenceService $preferences = null,
    ) {}

    public function handle(TicketCreated $event): void
    {
        try {
            $n = $this->buildTicketCreatedNotification($event);

            $payload = new NotificationPayload(
                type: $n['type'],
                title: $n['title'],
                body: $n['body'],
                url: $n['url'],
                icon: $n['icon'],
                ticketId: $n['ticketId'],
                dedupKey: $n['dedupKey'],
            );

            if ($this->preferences !== null) {
                $prefs = $this->preferences;
                User::role(['admin', 'super_admin'])->get()->each(function (User $admin) use ($payload, $prefs) {
                    if ($prefs->isEnabled($admin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_IN_APP)) {
                        $this->notificationService->notifyUser($admin, $payload);
                    }
                });
            } else {
                $this->notificationService->notifyAdmins($payload);
            }
        } catch (Throwable $e) {
            Log::error('Error creando notificación in-app en ticket creado.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
