<?php

namespace App\Listeners\Concerns;

use App\Models\User;
use App\Services\Notifications\NotificationPayload;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pipeline de entrega compartido por los listeners de notificaciones in-app:
 * construye el payload, omite resultados nulos (acción sin destinatario) y
 * registra errores sin relanzarlos para no romper la cola.
 */
trait DeliversTicketInAppNotification
{
    abstract protected function notificationService(): NotificationService;

    /**
     * @param  callable(): (array{
     *     user: User,
     *     type: string,
     *     title: string,
     *     body: string,
     *     url: string,
     *     icon: string,
     *     ticketId: string,
     *     dedupKey: string
     * }|null)  $buildPayload
     */
    protected function deliverInAppNotification(callable $buildPayload, string $errorMessage, string $ticketId): void
    {
        try {
            $n = $buildPayload();
            if ($n === null) {
                return;
            }

            $this->notificationService()->notifyUser($n['user'], new NotificationPayload(
                type: $n['type'],
                title: $n['title'],
                body: $n['body'],
                url: $n['url'],
                icon: $n['icon'],
                ticketId: $n['ticketId'],
                dedupKey: $n['dedupKey'],
            ));
        } catch (Throwable $e) {
            Log::error($errorMessage, [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
