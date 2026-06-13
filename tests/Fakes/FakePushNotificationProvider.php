<?php

namespace Tests\Fakes;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Models\User;
use PHPUnit\Framework\Assert;

class FakePushNotificationProvider implements PushNotificationProvider
{
    /** @var array<int, array<string, mixed>> */
    public array $sentToUsers = [];

    /** @var array<int, array<string, mixed>> */
    public array $sentToRoles = [];

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $this->sentToUsers[] = compact('user', 'title', 'body', 'data');
    }

    public function sendToRole(string $role, string $title, string $body, array $data = []): void
    {
        $this->sentToRoles[] = compact('role', 'title', 'body', 'data');
    }

    public function sendToRoles(array $roles, string $title, string $body, array $data = []): void
    {
        foreach ($roles as $role) {
            $this->sendToRole($role, $title, $body, $data);
        }
    }

    public function assertSentToUser(string $userId): void
    {
        $ids = array_column(array_column($this->sentToUsers, 'user'), 'id');
        Assert::assertContains($userId, $ids, "Expected push notification to user {$userId}");
    }

    public function assertNotSentToUser(string $userId): void
    {
        $ids = array_column(array_column($this->sentToUsers, 'user'), 'id');
        Assert::assertNotContains($userId, $ids, "Expected no push notification to user {$userId}");
    }

    public function assertSentToRole(string $role): void
    {
        $roles = array_column($this->sentToRoles, 'role');
        Assert::assertContains($role, $roles, "Expected push notification to role {$role}");
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->sentToUsers, 'Expected no push notifications to users');
        Assert::assertEmpty($this->sentToRoles, 'Expected no push notifications to roles');
    }
}
