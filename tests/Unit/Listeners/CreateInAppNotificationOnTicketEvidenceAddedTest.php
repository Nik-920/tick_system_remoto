<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\TicketEvidenceAdded;
use App\Listeners\CreateInAppNotificationOnTicketEvidenceAdded;
use App\Models\Category;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateInAppNotificationOnTicketEvidenceAddedTest extends TestCase
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

    public function test_reporter_receives_in_app_when_maintenance_adds_evidence(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $maintenance, 1));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'ticket_evidence_reporter',
        ]);
    }

    public function test_reporter_does_not_receive_self_notification(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter);

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $reporter, 2));

        $this->assertDatabaseMissing('notifications', ['user_id' => $reporter->id]);
    }

    public function test_reporter_opt_out_in_app_prevents_notification(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $this->makeListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->assertDatabaseMissing('notifications', ['user_id' => $reporter->id]);
    }

    public function test_reporter_with_default_preference_receives_in_app(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $this->makeListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'ticket_evidence_reporter',
        ]);
    }

    // ── Assignee notifications ──────────────────────────────────────────────

    public function test_maintenance_assignee_receives_in_app_when_reporter_adds_evidence(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $reporter, 1));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $maintenance->id,
            'type' => 'ticket_evidence_assignee',
        ]);
    }

    public function test_maintenance_assignee_does_not_receive_self_notification(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $maintenance, 1));

        $this->assertDatabaseMissing('notifications', ['user_id' => $maintenance->id]);
    }

    public function test_assignee_opt_out_in_app_prevents_notification(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $admin = $this->makeUser('admin');

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $this->makeListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->assertDatabaseMissing('notifications', ['user_id' => $maintenance->id]);
    }

    public function test_non_maintenance_assignee_does_not_receive_assignee_notification(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);
        $ticket->assigned_to = $admin->id;
        $ticket->save();
        $ticket->setRelation('assignee', $admin);

        $actor = $this->makeUser('reporter');

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $actor, 1));

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $admin->id,
            'type' => 'ticket_evidence_assignee',
        ]);
    }

    // ── Dedup: reporter === assignee ────────────────────────────────────────

    public function test_reporter_and_assignee_same_user_receives_only_reporter_notification(): void
    {
        $user = $this->makeUser('reporter');
        $user->assignRole('maintenance');
        $ticket = $this->makeTicket($user);
        $ticket->assigned_to = $user->id;
        $ticket->save();
        $ticket->setRelation('assignee', $user);

        $admin = $this->makeUser('admin');

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $counts = Notification::query()
            ->where('user_id', $user->id)
            ->count();

        $this->assertSame(1, $counts);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'ticket_evidence_reporter',
        ]);
    }

    // ── No assignee ─────────────────────────────────────────────────────────

    public function test_no_assignee_only_reporter_notified(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'ticket_evidence_reporter',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $admin->id,
            'type' => 'ticket_evidence_assignee',
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function makeListener(): CreateInAppNotificationOnTicketEvidenceAdded
    {
        return new CreateInAppNotificationOnTicketEvidenceAdded($this->notifications);
    }

    private function makeListenerWithPrefs(): CreateInAppNotificationOnTicketEvidenceAdded
    {
        return new CreateInAppNotificationOnTicketEvidenceAdded($this->notifications, $this->prefs);
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
            'name' => 'Loc ev '.uniqid(),
            'building' => 'B',
            'floor' => '1',
            'room_code' => 'R-'.uniqid(),
            'qr_token' => 'qr-ev-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat ev '.uniqid(),
            'icon' => 'bolt',
            'description' => 'ev',
        ]);

        $ticket = Ticket::create([
            'title' => 'Ticket evidencia',
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
}
