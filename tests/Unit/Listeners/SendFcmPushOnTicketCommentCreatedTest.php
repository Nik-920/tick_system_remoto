<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\TicketCommentCreated;
use App\Listeners\SendFcmPushOnTicketCommentCreated;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakePushNotificationProvider;
use Tests\TestCase;

class SendFcmPushOnTicketCommentCreatedTest extends TestCase
{
    use RefreshDatabase;

    private FakePushNotificationProvider $fcm;

    private TicketNotificationPreferenceService $prefs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
        $this->fcm = new FakePushNotificationProvider;
        $this->prefs = app(TicketNotificationPreferenceService::class);
    }

    // ── Queue contract ──────────────────────────────────────────────────────

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->makeListener());
    }

    public function test_uses_notifications_queue(): void
    {
        $this->assertSame('notifications', $this->makeListener()->queue);
    }

    public function test_has_one_try(): void
    {
        $this->assertSame(1, $this->makeListener()->tries);
    }

    public function test_after_commit_is_true(): void
    {
        $this->assertTrue($this->makeListener()->afterCommit);
    }

    // ── Reporter FCM ────────────────────────────────────────────────────────

    public function test_reporter_receives_fcm_when_maintenance_comments(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $comment = $this->makeComment($ticket, $maintenance);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $maintenance, $comment));

        $this->fcm->assertSentToUser($reporter->id);
    }

    public function test_reporter_does_not_receive_self_push(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter);
        $comment = $this->makeComment($ticket, $reporter);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $reporter, $comment));

        $this->fcm->assertNotSentToUser($reporter->id);
    }

    // ── Assignee FCM ────────────────────────────────────────────────────────

    public function test_maintenance_receives_fcm_when_reporter_comments(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $comment = $this->makeComment($ticket, $reporter);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $reporter, $comment));

        $this->fcm->assertSentToUser($maintenance->id);
    }

    public function test_maintenance_does_not_receive_self_push(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $comment = $this->makeComment($ticket, $maintenance);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $maintenance, $comment));

        $this->fcm->assertNotSentToUser($maintenance->id);
    }

    // ── Preference gates ────────────────────────────────────────────────────

    public function test_reporter_fcm_disabled_blocks_push(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);
        $comment = $this->makeComment($ticket, $admin);

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER, TicketNotificationPreference::CHANNEL_FCM, false);

        $this->makeListenerWithPrefs()->handle(new TicketCommentCreated($ticket, $admin, $comment));

        $this->fcm->assertNotSentToUser($reporter->id);
    }

    public function test_assignee_fcm_disabled_does_not_affect_reporter_fcm(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $comment = $this->makeComment($ticket, $admin);

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM, false);

        $this->makeListenerWithPrefs()->handle(new TicketCommentCreated($ticket, $admin, $comment));

        $this->fcm->assertSentToUser($reporter->id);
        $this->fcm->assertNotSentToUser($maintenance->id);
    }

    // ── Payload safety ──────────────────────────────────────────────────────

    public function test_fcm_data_does_not_expose_comment_body(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);
        $comment = $this->makeComment($ticket, $admin);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $admin, $comment));

        foreach ($this->fcm->sentToUsers as $sent) {
            $this->assertArrayNotHasKey('body', $sent['data'] ?? []);
            $this->assertArrayNotHasKey('comment_body', $sent['data'] ?? []);
        }
    }

    public function test_fcm_data_includes_required_safe_fields(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);
        $comment = $this->makeComment($ticket, $admin);

        $this->makeListener()->handle(new TicketCommentCreated($ticket, $admin, $comment));

        $this->assertNotEmpty($this->fcm->sentToUsers);
        $data = $this->fcm->sentToUsers[0]['data'] ?? [];
        $this->assertArrayHasKey('ticket_id', $data);
        $this->assertArrayHasKey('action', $data);
        $this->assertSame('comment_created', $data['action']);
        $this->assertArrayHasKey('recipient_role', $data);
    }

    public function test_reporter_equals_assignee_does_not_duplicate_push(): void
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

        $sentToUser = array_filter($this->fcm->sentToUsers, fn ($s) => $s['user']->id === $user->id);
        $this->assertCount(1, $sentToUser);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function makeListener(): SendFcmPushOnTicketCommentCreated
    {
        return new SendFcmPushOnTicketCommentCreated($this->fcm);
    }

    private function makeListenerWithPrefs(): SendFcmPushOnTicketCommentCreated
    {
        return new SendFcmPushOnTicketCommentCreated($this->fcm, $this->prefs);
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
            'name' => 'Loc fcm-cmt '.uniqid(),
            'building' => 'B',
            'floor' => '1',
            'room_code' => 'R-'.uniqid(),
            'qr_token' => 'qr-fcm-cmt-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat fcm-cmt '.uniqid(),
            'icon' => 'bolt',
            'description' => 'fcm-cmt',
        ]);

        $ticket = Ticket::create([
            'title' => 'Ticket fcm comment',
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
            'body' => 'Comentario FCM test.',
        ]);
    }
}
