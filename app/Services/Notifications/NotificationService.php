<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Support\Cache\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function notifyUser(User $user, NotificationPayload $payload): void
    {
        try {
            // Idempotencia (Fases 5.2 / 5.4): evitar duplicar la notificación in-app
            // ante reintentos de listeners encolados.
            if ($this->isDuplicate($user->id, $payload->type, $payload->ticketId, $payload->dedupKey)) {
                return;
            }

            Notification::create([
                'user_id' => $user->id,
                'ticket_id' => $payload->ticketId,
                'dedup_key' => $payload->dedupKey,
                'type' => $payload->type,
                'title' => $payload->title,
                'body' => $payload->body,
                'url' => $payload->url,
                'icon' => $payload->icon,
            ]);

            Cache::forget(CacheKeys::notificationsUnreadCount($user->id));
            Cache::forget(CacheKeys::notificationsRecent($user->id));
        } catch (Throwable $e) {
            Log::error('Error guardando notificación interna.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyAdmins(NotificationPayload $payload): void
    {
        $adminIds = User::role(['admin', 'super_admin'])
            ->pluck('id')
            ->unique();

        User::whereIn('id', $adminIds)->get()->each(function (User $admin) use ($payload) {
            $this->notifyUser($admin, $payload);
        });
    }

    /**
     * Notifica al usuario asignado (maintenance) descartando: assignee nulo,
     * assignee sin rol maintenance, y auto-notificación cuando actor === assignee.
     */
    public function notifyAssignee(?User $assignee, NotificationPayload $payload, ?User $actor = null): void
    {
        if (! $assignee instanceof User) {
            return;
        }
        if (! $assignee->hasRole('maintenance')) {
            return;
        }
        if ($actor instanceof User && $actor->id === $assignee->id) {
            return;
        }
        $this->notifyUser($assignee, $payload);
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
