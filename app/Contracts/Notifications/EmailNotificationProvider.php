<?php

declare(strict_types=1);

namespace App\Contracts\Notifications;

/**
 * Target del patrón Adapter — Email transaccional.
 *
 * Define el contrato que el dominio usa para enviar correos, sin filtrar
 * el SDK de Resend al resto de la aplicación (mismo patrón que
 * PushNotificationProvider para FCM).
 */
interface EmailNotificationProvider
{
    /**
     * Envía un email y devuelve el identificador del proveedor (usado para
     * correlacionar los eventos de webhook con el registro de entrega).
     *
     * @param  array<string, mixed>  $tags
     */
    public function send(string $toEmail, string $subject, string $html, array $tags = []): string;
}
