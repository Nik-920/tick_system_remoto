<?php

namespace Tests\Unit\Services\Notifications;

use App\Models\User;
use App\Services\Notifications\NotificationPayload;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_user_creates_notification(): void
    {
        $user = User::factory()->create();

        $service = new NotificationService;
        $service->notifyUser($user, new NotificationPayload(
            type: 'ticket',
            title: 'Ticket updated',
            body: 'Body',
            url: '/tickets/1',
            icon: 'bell',
        ));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'ticket',
            'title' => 'Ticket updated',
            'body' => 'Body',
            'url' => '/tickets/1',
            'icon' => 'bell',
        ]);
    }

    public function test_notify_admins_creates_notifications_for_admin_roles_only(): void
    {
        $this->ensureRolesExist();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $reporter = User::factory()->create();
        $reporter->assignRole('reporter');

        $service = new NotificationService;
        $service->notifyAdmins(new NotificationPayload(
            type: 'system',
            title: 'System alert',
            body: 'Body',
        ));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'system',
            'title' => 'System alert',
            'body' => 'Body',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'type' => 'system',
            'title' => 'System alert',
            'body' => 'Body',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $reporter->id,
            'type' => 'system',
        ]);
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }
}
