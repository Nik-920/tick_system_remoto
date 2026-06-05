<?php

namespace App\Contracts\Notifications;

use App\Models\User;

interface PushNotificationProvider
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): void;

    public function sendToRole(string $role, string $title, string $body, array $data = []): void;

    public function sendToRoles(array $roles, string $title, string $body, array $data = []): void;
}
