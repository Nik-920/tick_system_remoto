<?php

namespace App\Listeners;

use App\Events\TicketStateChanged;
use App\Models\User;
use App\Services\Firebase\FcmNotificationService;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPushNotificationOnTicketStateChanged
{
    public function __construct(
        private FcmNotificationService $fcm,
        private NotificationService $notificationService
    ) {}

    public function handle(TicketStateChanged $event): void
    {
        try {
            $ticket = $event->ticket;
            $reporter = $ticket->reporter;

            if (! $reporter instanceof User) {
                return;
            }

            if ($reporter->id === $event->actor->id) {
                return;
            }

            $stateLabels = [
                'open' => '🔔 Reabierto',
                'in_progress' => '🔧 En progreso',
                'resolved' => '✅ Resuelto',
                'rejected' => '❌ Rechazado',
            ];

            $label = $stateLabels[$event->toState] ?? '📋 Actualizado';
            $url = route('tickets.show', $ticket);

            $title = "{$label}: {$ticket->title}";
            $body = "Tu ticket ha sido actualizado a: {$event->toState}";

            $this->notificationService->notifyUser(
                user: $reporter,
                type: 'ticket_state_changed',
                title: $title,
                body: $body,
                url: $url,
                icon: $label
            );

            $this->fcm->sendToUser(
                user: $reporter,
                title: $title,
                body: $body,
                data: [
                    'ticket_id' => $ticket->id,
                    'url' => $url,
                    'type' => 'ticket_state_changed',
                    'state' => $event->toState,
                ]
            );
        } catch (Throwable $e) {
            Log::error('Error enviando notificación en cambio de estado.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
