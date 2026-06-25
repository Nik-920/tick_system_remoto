<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketEvidenceAdded;
use App\Listeners\SendFcmPushOnTicketEvidenceAdded;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakePushNotificationProvider;
use Tests\TestCase;

class SendFcmPushOnTicketEvidenceAddedTest extends TestCase
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

    public function test_has_max_tries_of_one(): void
    {
        $this->assertSame(1, $this->makeListener()->tries);
    }

    public function test_after_commit_is_true(): void
    {
        $this->assertTrue($this->makeListener()->afterCommit);
    }

    // ── Reporter FCM ────────────────────────────────────────────────────────

    public function test_reporter_receives_fcm_when_maintenance_adds_evidence(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $maintenance, 1));

        $this->fcm->assertSentToUser($reporter->id);
    }

    public function test_reporter_does_not_receive_self_notification(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter);

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $reporter, 1));

        $this->fcm->assertNotSentToUser($reporter->id);
    }

    public function test_reporter_opt_out_fcm_prevents_push(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, TicketNotificationPreference::CHANNEL_FCM, false);

        $this->makeListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->fcm->assertNotSentToUser($reporter->id);
    }

    public function test_reporter_opt_out_in_app_but_fcm_enabled_still_receives_fcm(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $this->makeListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->fcm->assertSentToUser($reporter->id);
    }

    // ── Assignee FCM ────────────────────────────────────────────────────────

    public function test_maintenance_assignee_receives_fcm_when_reporter_adds_evidence(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $reporter, 2));

        $this->fcm->assertSentToUser($maintenance->id);
    }

    public function test_maintenance_assignee_does_not_receive_self_notification(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $maintenance, 1));

        $this->fcm->assertNotSentToUser($maintenance->id);
    }

    public function test_assignee_opt_out_fcm_prevents_push(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $admin = $this->makeUser('admin');

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM, false);

        $this->makeListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->fcm->assertNotSentToUser($maintenance->id);
    }

    public function test_assignee_opt_out_fcm_does_not_affect_reporter_fcm(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $admin = $this->makeUser('admin');

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM, false);

        $this->makeListenerWithPrefs()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->fcm->assertSentToUser($reporter->id);
    }

    // ── Dedup: reporter === assignee ────────────────────────────────────────

    public function test_reporter_and_assignee_same_user_receives_only_one_fcm(): void
    {
        Log::spy();

        $user = $this->makeUser('reporter');
        $user->assignRole('maintenance');

        $ticket = $this->makeTicket($user);
        $ticket->assigned_to = $user->id;
        $ticket->save();
        $ticket->setRelation('assignee', $user);

        $admin = $this->makeUser('admin');

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $sentIds = array_column(array_column($this->fcm->sentToUsers, 'user'), 'id');
        $this->assertCount(1, array_filter($sentIds, fn ($id) => $id === $user->id));
    }

    // ── Payload ─────────────────────────────────────────────────────────────

    public function test_reporter_push_data_does_not_contain_file_url(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $admin, 3));

        $data = $this->fcm->sentToUsers[0]['data'] ?? [];
        $this->assertArrayNotHasKey('file_url', $data);
        $this->assertSame('ticket_evidence_added', $data['type'] ?? null);
        $this->assertSame('reporter', $data['recipient_role'] ?? null);
        $this->assertSame('3', $data['count'] ?? null);
    }

    public function test_assignee_push_data_contains_correct_recipient_role(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->makeListener()->handle(new TicketEvidenceAdded($ticket, $reporter, 2));

        $assigneePush = null;
        foreach ($this->fcm->sentToUsers as $sent) {
            if ($sent['user']->id === $maintenance->id) {
                $assigneePush = $sent;
                break;
            }
        }

        $this->assertNotNull($assigneePush);
        $this->assertSame('maintenance', $assigneePush['data']['recipient_role'] ?? null);
        $this->assertArrayNotHasKey('file_url', $assigneePush['data']);
    }

    // ── Exception handling ──────────────────────────────────────────────────

    public function test_exception_in_reporter_path_is_caught_and_logged(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $fcm = $this->createMock(PushNotificationProvider::class);
        $fcm->method('sendToUser')
            ->willThrowException(new \RuntimeException('FCM error'));

        (new SendFcmPushOnTicketEvidenceAdded($fcm))->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'evidencia'));

        $this->addToAssertionCount(1);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function makeListener(): SendFcmPushOnTicketEvidenceAdded
    {
        return new SendFcmPushOnTicketEvidenceAdded($this->fcm);
    }

    private function makeListenerWithPrefs(): SendFcmPushOnTicketEvidenceAdded
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

    private function makeTicket(User $reporter): Ticket
    {
        $location = Location::create([
            'name' => 'Loc fcm-ev '.uniqid(),
            'building' => 'B',
            'floor' => '1',
            'room_code' => 'R-'.uniqid(),
            'qr_token' => 'qr-fcm-ev-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat fcm-ev '.uniqid(),
            'icon' => 'bolt',
            'description' => 'ev',
        ]);

        $ticket = Ticket::create([
            'title' => 'Ticket FCM evidencia',
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
