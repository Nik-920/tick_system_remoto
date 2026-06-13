<?php

namespace App\Listeners\Concerns;

use App\Events\TicketAssigned;
use App\Models\User;

/**
 * Resolución de destinatario + contenido compartido por los dos listeners de
 * TicketAssigned (in-app y push FCM al destinatario). La decisión por acción
 * (assigned / reassigned / unassigned, con claimed / released explícitamente
 * sin notificación) vive aquí una sola vez. Devuelve null cuando la acción no
 * mapea a ningún destinatario.
 */
trait BuildsTicketAssignedNotification
{
    /**
     * @return array{
     *     user: User,
     *     type: string,
     *     title: string,
     *     body: string,
     *     url: string,
     *     icon: string,
     *     ticketId: string,
     *     dedupKey: string,
     *     data: array<string, mixed>
     * }|null
     */
    protected function buildTicketAssignedNotification(TicketAssigned $event): ?array
    {
        $resolved = $this->resolveAssignmentAction($event);
        if ($resolved === null) {
            return null;
        }

        [$target, $type, $title, $body, $icon] = $resolved;

        if (! $target instanceof User) {
            return null;
        }

        $url = route('tickets.show', $event->ticket);

        return [
            'user' => $target,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'icon' => $icon,
            'ticketId' => (string) $event->ticket->id,
            'dedupKey' => "{$type}:{$event->ticket->id}:{$event->action}",
            'data' => [
                'ticket_id' => $event->ticket->id,
                'url' => $url,
                'type' => $type,
                'action' => $event->action,
                'correlation_id' => $event->correlationId,
            ],
        ];
    }

    /**
     * @return array{0: ?User, 1: string, 2: string, 3: string, 4: string}|null
     */
    private function resolveAssignmentAction(TicketAssigned $event): ?array
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
            'claimed' => null,
            'released' => null,
            default => null,
        };
    }
}
