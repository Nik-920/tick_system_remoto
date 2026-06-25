<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\TicketCommentCreated;
use App\Listeners\CreateInAppNotificationOnTicketCommentCreated;
use App\Models\Category;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateInAppNotificationOnTicketCommentCreatedTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notifications;

    private TicketNotificationPreferenceService $prefs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
        $this->notifications = app(NotificationService::class);
        $this->prefs = app(TicketNotificationPreferenceService::class);
    }

    // ── Queue contract ──────────────────────────────────────────────────────

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->makeListener());
    }

    public function test_uses_default_queue(): void
    {
        $this->assertSame('default', $this->makeListener()->queue);
    }

    public function test_has_three_tries(): void
    {
        $this->assertSame(3, $this->makeListener()->tries);
    }

    public function test_after_commit_is_true(): void
    {
        $this->assertTrue($this->makeListener()->afterCommit);
    }

    // ── Reporter notifications ──────────────────────────────────────────────

    public function test_reporter_receives_in_app_when_maintenance_comments(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $comment = $this->makeComment($ticket, $maintenance);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $maintenance, $comment));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'ticket_comment_reporter',
        ]);
    }

    public function test_reporter_receives_in_app_when_admin_comments(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);
        $comment = $this->makeComment($ticket, $admin);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $admin, $comment));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'ticket_comment_reporter',
        ]);
    }

    public function test_reporter_does_not_receive_self_notification(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter);
        $comment = $this->makeComment($ticket, $reporter);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $reporter, $comment));

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $reporter->id,
            'type' => 'ticket_comment_reporter',
        ]);
    }

    // ── Assignee notifications ──────────────────────────────────────────────

    public function test_maintenance_assignee_receives_in_app_when_reporter_comments(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $comment = $this->makeComment($ticket, $reporter);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $reporter, $comment));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $maintenance->id,
            'type' => 'ticket_comment_assignee',
        ]);
    }

    public function test_maintenance_assignee_receives_in_app_when_admin_comments(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $comment = $this->makeComment($ticket, $admin);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $admin, $comment));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $maintenance->id,
            'type' => 'ticket_comment_assignee',
        ]);
    }

    public function test_maintenance_assignee_does_not_receive_self_notification(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $comment = $this->makeComment($ticket, $maintenance);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $maintenance, $comment));

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $maintenance->id,
            'type' => 'ticket_comment_assignee',
        ]);
    }

    public function test_no_assignee_notification_when_assignee_lacks_maintenance_role(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);
        $ticket->assigned_to = $admin->id;
        $ticket->save();
        $ticket->setRelation('assignee', $admin);
        $comment = $this->makeComment($ticket, $reporter);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $reporter, $comment));

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $admin->id,
            'type' => 'ticket_comment_assignee',
        ]);
    }

    public function test_reporter_equals_assignee_generates_single_notification(): void
    {
        $user = $this->makeUser('reporter');
        $user->assignRole('maintenance');

        $ticket = $this->makeTicket($user);
        $ticket->assigned_to = $user->id;
        $ticket->save();
        $ticket->setRelation('assignee', $user);

        $admin = $this->makeUser('admin');
        $comment = $this->makeComment($ticket, $admin);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $admin, $comment));

        $count = Notification::query()->where('user_id', $user->id)->count();
        $this->assertSame(1, $count);
    }

    // ── Preference gates ────────────────────────────────────────────────────

    public function test_reporter_preference_disabled_blocks_in_app(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);
        $comment = $this->makeComment($ticket, $admin);

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $this->makeListenerWithPrefs()->handle(new TicketCommentCreated($ticket, $admin, $comment));

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $reporter->id,
            'type' => 'ticket_comment_reporter',
        ]);
    }

    public function test_assignee_preference_disabled_blocks_in_app(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $comment = $this->makeComment($ticket, $reporter);

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $this->makeListenerWithPrefs()->handle(new TicketCommentCreated($ticket, $reporter, $comment));

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $maintenance->id,
            'type' => 'ticket_comment_assignee',
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function makeListener(): CreateInAppNotificationOnTicketCommentCreated
    {
        return new CreateInAppNotificationOnTicketCommentCreated($this->notifications);
    }

    private function makeListenerWithPrefs(): CreateInAppNotificationOnTicketCommentCreated
    {
        return new CreateInAppNotificationOnTicketCommentCreated($this->notifications, $this->prefs);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeTicket(User $reporter): Ticket
    {
        $location = Location::create([
            'name' => 'Loc cmt '.uniqid(),
            'building' => 'B',
            'floor' => '1',
            'room_code' => 'R-'.uniqid(),
            'qr_token' => 'qr-cmt-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat cmt '.uniqid(),
            'icon' => 'bolt',
            'description' => 'cmt',
        ]);

        $ticket = Ticket::create([
            'title' => 'Ticket comentario',
            'description' => 'Desc.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        return $ticket->fresh(['reporter', 'assignee']) ?? $ticket;
    }

    private function makeTicketWithAssignment(User $reporter, User $assignee): Ticket
    {
        $ticket = $this->makeTicket($reporter);
        $ticket->assigned_to = $assignee->id;
        $ticket->save();

        return $ticket->fresh(['reporter', 'assignee']) ?? $ticket;
    }

    private function makeComment(Ticket $ticket, User $author): TicketComment
    {
        return TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'body' => 'Comentario de prueba.',
        ]);
    }
}
