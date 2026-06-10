<?php

namespace App\Contracts\Notifications;

use App\Models\User;

/**
 * Target del patrón Adapter — Notificaciones Push.
 *
 * Define el contrato que el dominio usa para enviar push notifications.
 * La implementación concreta (FirebasePushNotificationAdapter) encapsula
 * el SDK Kreait Firebase sin filtrarlo al dominio.
 */
interface PushNotificationProvider
{
    /**
     * Envía una notificación push a un usuario específico.
     *
     * @param  array<string, mixed>  $data  Datos adicionales del payload FCM
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void;

    /**
     * Envía una notificación push a todos los usuarios con un rol específico.
     *
     * @param  array<string, mixed>  $data  Datos adicionales del payload FCM
     */
    public function sendToRole(string $role, string $title, string $body, array $data = []): void;

    /**
     * Envía una notificación push a todos los usuarios con cualquiera de los roles dados.
     *
     * @param  array<int, string>  $roles  Roles a notificar
     * @param  array<string, mixed>  $data  Datos adicionales del payload FCM
     */
    public function sendToRoles(array $roles, string $title, string $body, array $data = []): void;
}
