<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Events\TicketEvidenceAdded;
use App\Listeners\CreateInAppNotificationOnTicketEvidenceAdded;
use App\Listeners\SendFcmPushOnTicketEvidenceAdded;
use App\Models\Category;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakePushNotificationProvider;
use Tests\TestCase;

/**
 * Integration tests for evidence notifications (TicketEvidenceAdded event).
 *
 * A) In-app: reporter path
 * B) In-app: maintenance assignee path
 * C) FCM: reporter path
 * D) FCM: maintenance assignee path
 * E) Preference interactions
 * F) No self-notification / dedup reporter===assignee
 * G) Payload safety (no file_url)
 */
class TicketEvidenceNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notifications;

    private TicketNotificationPreferenceService $prefs;

    private FakePushNotificationProvider $fcm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
        $this->notifications = app(NotificationService::class);
        $this->prefs = app(TicketNotificationPreferenceService::class);
        $this->fcm = new FakePushNotificationProvider;
    }

    // ── A. In-app: reporter path ────────────────────────────────────────────

    public function test_reporter_receives_in_app_when_admin_adds_evidence(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $this->inAppListener()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'ticket_evidence_reporter',
        ]);
    }

    public function test_reporter_does_not_receive_in_app_for_own_evidence(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter);

        $this->inAppListener()->handle(new TicketEvidenceAdded($ticket, $reporter, 1));

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $reporter->id,
            'type' => 'ticket_evidence_reporter',
        ]);
    }

    public function test_reporter_notification_title_contains_ticket_title(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter, 'Fuga de agua');

        $this->inAppListener()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'title' => 'Nueva evidencia: Fuga de agua',
        ]);
    }

    // ── B. In-app: maintenance assignee path ────────────────────────────────

    public function test_maintenance_assignee_receives_in_app_when_reporter_adds_evidence(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->inAppListener()->handle(new TicketEvidenceAdded($ticket, $reporter, 2));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $maintenance->id,
            'type' => 'ticket_evidence_assignee',
        ]);
    }

    public function test_maintenance_assignee_receives_in_app_when_admin_adds_evidence(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->inAppListener()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $maintenance->id,
            'type' => 'ticket_evidence_assignee',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'ticket_evidence_reporter',
        ]);
    }

    public function test_maintenance_does_not_receive_in_app_for_own_evidence(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->inAppListener()->handle(new TicketEvidenceAdded($ticket, $maintenance, 1));

        $this->assertDatabaseMissing('notifications', ['user_id' => $maintenance->id]);
    }

    // ── C. FCM: reporter path ───────────────────────────────────────────────

    public function test_reporter_receives_fcm_when_maintenance_adds_evidence(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->fcmListener()->handle(new TicketEvidenceAdded($ticket, $maintenance, 1));

        $this->fcm->assertSentToUser($reporter->id);
    }

    public function test_reporter_does_not_receive_fcm_for_own_evidence(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter);

        $this->fcmListener()->handle(new TicketEvidenceAdded($ticket, $reporter, 1));

        $this->fcm->assertNotSentToUser($reporter->id);
    }

    // ── D. FCM: maintenance assignee path ───────────────────────────────────

    public function test_maintenance_assignee_receives_fcm_when_reporter_adds_evidence(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->fcmListener()->handle(new TicketEvidenceAdded($ticket, $reporter, 3));

        $this->fcm->assertSentToUser($maintenance->id);
    }

    // ── E. Preference interactions ──────────────────────────────────────────

    public function test_reporter_opt_out_in_app_evidence_blocks_in_app_not_fcm(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $this->inAppListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));
        $this->fcmListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->assertDatabaseMissing('notifications', ['user_id' => $reporter->id]);
        $this->fcm->assertSentToUser($reporter->id);
    }

    public function test_reporter_opt_out_fcm_evidence_blocks_fcm_not_in_app(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, TicketNotificationPreference::CHANNEL_FCM, false);

        $this->inAppListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));
        $this->fcmListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'ticket_evidence_reporter',
        ]);
        $this->fcm->assertNotSentToUser($reporter->id);
    }

    public function test_assignee_opt_out_fcm_evidence_does_not_affect_reporter_fcm(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM, false);

        $this->fcmListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->fcm->assertSentToUser($reporter->id);
        $this->fcm->assertNotSentToUser($maintenance->id);
    }

    // ── F. Self-notification / dedup reporter===assignee ────────────────────

    public function test_reporter_equals_assignee_only_one_in_app_notification(): void
    {
        $user = $this->makeUser('reporter');
        $user->assignRole('maintenance');

        $ticket = $this->makeTicket($user);
        $ticket->assigned_to = $user->id;
        $ticket->save();
        $ticket->setRelation('assignee', $user);

        $admin = $this->makeUser('admin');

        $this->inAppListener()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $count = Notification::query()->where('user_id', $user->id)->count();
        $this->assertSame(1, $count);
    }

    // ── G. Payload safety (no file_url) ────────────────────────────────────

    public function test_fcm_data_does_not_expose_file_url(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $this->fcmListener()->handle(new TicketEvidenceAdded($ticket, $admin, 2));

        foreach ($this->fcm->sentToUsers as $sent) {
            $this->assertArrayNotHasKey('file_url', $sent['data'] ?? []);
            $this->assertArrayNotHasKey('file_path', $sent['data'] ?? []);
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function inAppListener(): CreateInAppNotificationOnTicketEvidenceAdded
    {
        return new CreateInAppNotificationOnTicketEvidenceAdded($this->notifications);
    }

    private function inAppListenerWithPrefs(): CreateInAppNotificationOnTicketEvidenceAdded
    {
        return new CreateInAppNotificationOnTicketEvidenceAdded($this->notifications, $this->prefs);
    }

    private function fcmListener(): SendFcmPushOnTicketEvidenceAdded
    {
        return new SendFcmPushOnTicketEvidenceAdded($this->fcm);
    }

    private function fcmListenerWithPrefs(): SendFcmPushOnTicketEvidenceAdded
    {
        return new SendFcmPushOnTicketEvidenceAdded($this->fcm, $this->prefs);
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

    private function makeTicket(User $reporter, string $title = 'Ticket evidencia test'): Ticket
    {
        $location = Location::create([
            'name' => 'Loc feat-ev '.uniqid(),
            'building' => 'B',
            'floor' => '1',
            'room_code' => 'R-'.uniqid(),
            'qr_token' => 'qr-feat-ev-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat feat-ev '.uniqid(),
            'icon' => 'bolt',
            'description' => 'ev',
        ]);

        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripcion.',
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
