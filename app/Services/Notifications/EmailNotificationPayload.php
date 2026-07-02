<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * Datos de un email de notificación de ticket, agrupados para no propagar
 * listas largas de parámetros entre listeners y el servicio de email.
 */
final class EmailNotificationPayload
{
    /**
     * @param  list<string>  $bodyLines
     */
    public function __construct(
        public readonly string $type,
        public readonly string $subject,
        public readonly string $title,
        public readonly array $bodyLines,
        public readonly string $ctaLabel,
        public readonly string $ctaUrl,
        public readonly ?string $ticketId = null,
        public readonly ?string $dedupKey = null,
    ) {}
}
