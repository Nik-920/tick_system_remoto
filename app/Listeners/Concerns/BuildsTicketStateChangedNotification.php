<?php

namespace App\Listeners\Concerns;

use App\Events\TicketStateChanged;
use App\Models\User;

/**
 * Resolución de destinatario + contenido compartido por los dos listeners de
 * TicketStateChanged (in-app y push FCM al reporter). Devuelve null cuando no
 * hay nada que notificar (sin reporter válido, o el reporter es el propio actor),
 * de modo que ambos listeners apliquen el mismo guard sin duplicarlo.
 */
trait BuildsTicketStateChangedNotification
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
    protected function buildTicketStateChangedNotification(TicketStateChanged $event): ?array
    {
        $ticket = $event->ticket;
        $reporter = $ticket->reporter;

        if (! $reporter instanceof User) {
            return null;
        }

        if ($reporter->id === $event->actor->id) {
            return null;
        }

        $stateLabels = [
            'open' => '🔔 Reabierto',
            'in_progress' => '🔧 En progreso',
            'resolved' => '✅ Resuelto',
            'rejected' => '❌ Rechazado',
        ];

        $label = $stateLabels[$event->toState] ?? '📋 Actualizado';
        $url = route('tickets.show', $ticket);

        return [
            'user' => $reporter,
            'type' => 'ticket_state_changed',
            'title' => "{$label}: {$ticket->title}",
            'body' => "Tu ticket ha sido actualizado a: {$event->toState}",
            'url' => $url,
            'icon' => $label,
            'ticketId' => (string) $ticket->id,
            'dedupKey' => "ticket_state_changed:{$ticket->id}:{$event->fromState}:{$event->toState}",
            'data' => [
                'ticket_id' => $ticket->id,
                'url' => $url,
                'type' => 'ticket_state_changed',
                'state' => $event->toState,
            ],
        ];
    }
}
