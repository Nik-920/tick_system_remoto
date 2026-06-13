<?php

namespace Tests\Feature\Web;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_notifications_and_unread_count(): void
    {
        $user = $this->createUserWithRole('reporter');
        $otherUser = $this->createUserWithRole('reporter');

        $readNotification = Notification::create([
            'user_id' => $user->id,
            'type' => 'ticket',
            'title' => 'Ticket updated',
            'body' => 'State changed',
            'read_at' => now(),
            'created_at' => now(),
        ]);

        $unreadNotification = Notification::create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'System alert',
            'body' => 'Message',
            'created_at' => now(),
        ]);

        $foreignNotification = Notification::create([
            'user_id' => $otherUser->id,
            'type' => 'system',
            'title' => 'Other user',
            'body' => 'Ignore',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('notifications.index'));

        $response->assertOk();
        $response->assertJsonPath('unread_count', 1);
        $response->assertJsonCount(2, 'notifications');
        $response->assertJsonFragment(['id' => $readNotification->id]);
        $response->assertJsonFragment(['id' => $unreadNotification->id]);
        $response->assertJsonMissing(['id' => $foreignNotification->id]);
        $response->assertJsonStructure([
            'notifications' => [
                [
                    'id',
                    'type',
                    'title',
                    'body',
                    'url',
                    'icon',
                    'read_at',
                    'time',
                ],
            ],
            'unread_count',
        ]);
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = $this->createUserWithRole('reporter');
        $otherUser = $this->createUserWithRole('reporter');

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'ticket',
            'title' => 'Pending',
            'body' => 'Body',
            'created_at' => now(),
        ]);

        $foreignNotification = Notification::create([
            'user_id' => $otherUser->id,
            'type' => 'ticket',
            'title' => 'Foreign',
            'body' => 'Body',
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson(route('notifications.read', $notification->id));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertNull($foreignNotification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = $this->createUserWithRole('reporter');
        $otherUser = $this->createUserWithRole('reporter');

        Notification::create([
            'user_id' => $user->id,
            'type' => 'ticket',
            'title' => 'One',
            'body' => 'Body',
            'created_at' => now(),
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Two',
            'body' => 'Body',
            'created_at' => now(),
        ]);

        $foreignNotification = Notification::create([
            'user_id' => $otherUser->id,
            'type' => 'system',
            'title' => 'Other',
            'body' => 'Body',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson(route('notifications.readAll'));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertSame(0, Notification::where('user_id', $user->id)->whereNull('read_at')->count());
        $this->assertNull($foreignNotification->fresh()->read_at);
    }

    private function createUserWithRole(string $role): User
    {
        $this->ensureRolesExist();

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }
}
