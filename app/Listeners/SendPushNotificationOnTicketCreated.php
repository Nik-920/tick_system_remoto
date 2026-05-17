<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Services\Firebase\FcmNotificationService;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPushNotificationOnTicketCreated
{
    public function __construct(
        private FcmNotificationService $fcm,
        private NotificationService $notificationService
    ) {}

    public function handle(TicketCreated $event): void
    {
        Log::info('LISTENER EJECUTADO', ['ticket_id' => $event->ticket->id, 'time' => now()->toDateTimeString()]);
        try {
            $ticket = $event->ticket;
            $location = $ticket->location?->name ?? 'Ubicación desconocida';
            $category = $ticket->category?->name ?? 'Sin categoría';
            $url = route('tickets.show', $ticket);

            $title = '🎫 Nuevo ticket reportado';
            $body = "{$ticket->title} – {$location} · {$category}";

            $this->notificationService->notifyAdmins(
                type: 'ticket_created',
                title: $title,
                body: $body,
                url: $url,
                icon: '🎫'
            );

            $this->fcm->sendToRoles(
                roles: ['admin', 'super_admin'],
                title: $title,
                body: $body,
                data: [
                    'ticket_id' => $ticket->id,
                    'url' => $url,
                    'type' => 'ticket_created',
                ]
            );
        } catch (Throwable $e) {
            Log::error('Error enviando notificación en ticket creado.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
