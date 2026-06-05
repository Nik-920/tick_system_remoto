<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function notifyUser(
        User $user,
        string $type,
        string $title,
        string $body,
        ?string $url = null,
        string $icon = '🔔',
        ?string $ticketId = null,
        ?string $dedupKey = null
    ): void {
        try {
            // Idempotencia (Fases 5.2 / 5.4): evitar duplicar la notificación in-app
            // ante reintentos de listeners encolados.
            if ($this->isDuplicate($user->id, $type, $ticketId, $dedupKey)) {
                return;
            }

            Notification::create([
                'user_id' => $user->id,
                'ticket_id' => $ticketId,
                'dedup_key' => $dedupKey,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'icon' => $icon,
            ]);
        } catch (Throwable $e) {
            Log::error('Error guardando notificación interna.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyAdmins(
        string $type,
        string $title,
        string $body,
        ?string $url = null,
        string $icon = '🔔',
        ?string $ticketId = null,
        ?string $dedupKey = null
    ): void {
        $adminIds = User::role(['admin', 'super_admin'])
            ->pluck('id')
            ->unique();

        User::whereIn('id', $adminIds)->get()->each(function (User $admin) use ($type, $title, $body, $url, $icon, $ticketId, $dedupKey) {
            $this->notifyUser($admin, $type, $title, $body, $url, $icon, $ticketId, $dedupKey);
        });
    }

    /**
     * ¿Ya existe hoy una notificación in-app equivalente para este usuario?
     *
     * - Si viene dedup_key (Fase 5.4): identidad precisa del evento lógico
     *   (incluye from/to state o action), permitiendo múltiples notificaciones
     *   legítimas del mismo ticket el mismo día y bloqueando solo reintentos.
     * - Si no viene: fallback (Fase 5.2) por user_id + type + ticket_id + día.
     * - Sin ticket_id ni dedup_key: no hay clave → no se deduplica.
     */
    private function isDuplicate(string $userId, string $type, ?string $ticketId, ?string $dedupKey): bool
    {
        if ($dedupKey !== null) {
            return Notification::query()
                ->where('user_id', $userId)
                ->where('dedup_key', $dedupKey)
                ->whereDate('created_at', now()->toDateString())
                ->exists();
        }

        if ($ticketId !== null) {
            return Notification::query()
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('ticket_id', $ticketId)
                ->whereDate('created_at', now()->toDateString())
                ->exists();
        }

        return false;
    }
}
