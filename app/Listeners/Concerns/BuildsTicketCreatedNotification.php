<?php

namespace App\Listeners\Concerns;

use App\Events\TicketCreated;

/**
 * Contenido de notificación compartido por los dos listeners de TicketCreated
 * (in-app a admins y push FCM a roles). Centralizar la construcción evita que
 * el texto in-app y el push diverjan al editarse por separado.
 */
trait BuildsTicketCreatedNotification
{
    /**
     * @return array{
     *     roles: list<string>,
     *     type: string,
     *     title: string,
     *     body: string,
     *     url: string,
     *     icon: string,
     *     ticketId: string,
     *     dedupKey: string,
     *     data: array<string, mixed>
     * }
     */
    protected function buildTicketCreatedNotification(TicketCreated $event): array
    {
        $ticket = $event->ticket;
        $location = $ticket->location?->name ?? 'Ubicación desconocida';
        $category = $ticket->category?->name ?? 'Sin categoría';
        $url = route('tickets.show', $ticket);

        $title = '🎫 Nuevo ticket reportado';
        $body = "{$ticket->title} – {$location} · {$category}";

        return [
            'roles' => ['admin', 'super_admin'],
            'type' => 'ticket_created',
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'icon' => '🎫',
            'ticketId' => (string) $ticket->id,
            'dedupKey' => "ticket_created:{$ticket->id}",
            'data' => [
                'ticket_id' => $ticket->id,
                'url' => $url,
                'type' => 'ticket_created',
            ],
        ];
    }
}
