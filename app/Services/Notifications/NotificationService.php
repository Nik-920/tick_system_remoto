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
        string $icon = '🔔'
    ): void {
        try {
            Notification::create([
                'user_id' => $user->id,
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
        string $icon = '🔔'
    ): void {
        $adminIds = User::role(['admin', 'super_admin'])
            ->pluck('id')
            ->unique();

        User::whereIn('id', $adminIds)->get()->each(function (User $admin) use ($type, $title, $body, $url, $icon) {
            $this->notifyUser($admin, $type, $title, $body, $url, $icon);
        });
    }
}
