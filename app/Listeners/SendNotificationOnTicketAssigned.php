<?php

namespace App\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketAssigned;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationOnTicketAssigned
{
    public function __construct(
        private PushNotificationProvider $fcm,
        private NotificationService $notificationService
    ) {}

    public function handle(TicketAssigned $event): void
    {
        try {
            $ticket = $event->ticket;
            $url = route('tickets.show', $ticket);

            $payload = $this->buildNotificationPayload($event);
            if ($payload === null) {
                return;
            }

            [$target, $type, $title, $body, $icon] = $payload;

            if (! $target instanceof User) {
                return;
            }

            $this->notificationService->notifyUser(
                user: $target,
                type: $type,
                title: $title,
                body: $body,
                url: $url,
                icon: $icon
            );

            $this->fcm->sendToUser(
                user: $target,
                title: $title,
                body: $body,
                data: [
                    'ticket_id' => $ticket->id,
                    'url' => $url,
                    'type' => $type,
                    'action' => $event->action,
                    'correlation_id' => $event->correlationId,
                ]
            );
        } catch (Throwable $e) {
            Log::error('Error enviando notificación de asignación.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{0: ?User, 1: string, 2: string, 3: string, 4: string}|null
     */
    private function buildNotificationPayload(TicketAssigned $event): ?array
    {
        $title = (string) $event->ticket->title;

        return match ($event->action) {
            'assigned' => [
                $event->newAssignee,
                'ticket_assigned',
                'Nuevo ticket asignado',
                'Se te asignó el ticket: '.$title,
                '📌',
            ],
            'reassigned' => [
                $event->newAssignee,
                'ticket_reassigned',
                'Ticket reasignado',
                'Se te reasignó el ticket: '.$title,
                '🔁',
            ],
            'unassigned' => [
                $event->previousAssignee,
                'ticket_unassigned',
                'Ticket desasignado',
                'Ya no tienes asignado el ticket: '.$title,
                '📤',
            ],
            default => null,
        };
    }
}
