<?php

namespace App\Services\Notifications;

/**
 * Datos de una notificación in-app, agrupados para no propagar
 * listas largas de parámetros entre listeners y el servicio.
 */
final class NotificationPayload
{
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly string $icon = '🔔',
        public readonly ?string $ticketId = null,
        public readonly ?string $dedupKey = null,
    ) {}
}
