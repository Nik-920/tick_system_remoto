<?php

namespace Tests\Feature\Web;

use App\Events\TicketAssigned;
use App\Events\TicketStateChanged;
use App\Listeners\SendFcmPushOnTicketAssigned;
use App\Listeners\SendFcmPushOnTicketStateChanged;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakePushNotificationProvider;
use Tests\TestCase;

/**
 * Verifica que maintenance recibe push FCM en los mismos escenarios
 * ya validados para in-app notifications, sin romper reporter ni admin.
 */
class MaintenanceFcmPushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private FakePushNotificationProvider $fcm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
        $this->fcm = new FakePushNotificationProvider;
    }

    // ── Caso A — Asignación ──

    public function test_maintenance_receives_fcm_push_when_admin_assigns_ticket(): void
    {
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicket($this->makeReporter());

        $this->assignmentListener()->handle(
            new TicketAssigned($ticket, $admin, null, $maintenance, 'assigned', 'corr-fcm-01')
        );

        $this->fcm->assertSentToUser($maintenance->id);
    }

    public function test_maintenance_receives_fcm_push_on_reassignment(): void
    {
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $previous = $this->makeMaintenance('prev-fcm');
        $ticket = $this->makeTicket($this->makeReporter());

        $this->assignmentListener()->handle(
            new TicketAssigned($ticket, $admin, $previous, $maintenance, 'reassigned', 'corr-fcm-02')
        );

        $this->fcm->assertSentToUser($maintenance->id);
    }

    public function test_claimed_action_sends_no_fcm_push(): void
    {
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicket($this->makeReporter());

        $this->assignmentListener()->handle(
            new TicketAssigned($ticket, $maintenance, null, $maintenance, 'claimed', 'corr-fcm-03')
        );

        $this->fcm->assertNothingSent();
    }

    // ── Caso B — Cambio de estado por tercero ──

    public function test_maintenance_receives_fcm_push_on_state_change_by_admin(): void
    {
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicketWithAssignment($this->makeReporter(), $maintenance);

        $this->stateListener()->handle(
            new TicketStateChanged($ticket, $admin, 'open', 'in_progress', 'corr-fcm-04')
        );

        $this->fcm->assertSentToUser($maintenance->id);
    }

    public function test_reporter_and_maintenance_both_receive_fcm_push_on_state_change(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->stateListener()->handle(
            new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'corr-fcm-05')
        );

        $this->fcm->assertSentToUser($reporter->id);
        $this->fcm->assertSentToUser($maintenance->id);
        $this->assertCount(2, $this->fcm->sentToUsers);
    }

    // ── Caso C — No self-push ──

    public function test_maintenance_does_not_receive_self_push_on_own_state_change(): void
    {
        $reporter = $this->makeReporter();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->stateListener()->handle(
            new TicketStateChanged($ticket, $maintenance, 'open', 'in_progress', 'corr-fcm-06')
        );

        $this->fcm->assertNotSentToUser($maintenance->id);
        $this->fcm->assertSentToUser($reporter->id);
    }

    // ── Reporter intacto cuando no hay assignee ──

    public function test_reporter_fcm_push_preserved_when_no_assignee(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        $this->stateListener()->handle(
            new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'corr-fcm-07')
        );

        $this->fcm->assertSentToUser($reporter->id);
        $this->assertCount(1, $this->fcm->sentToUsers);
    }

    // ── Edge case: reporter === maintenance assignee ──

    public function test_reporter_equals_assignee_receives_exactly_one_fcm_push(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();
        $user->assignRole('reporter');
        $user->assignRole('maintenance');

        $ticket = $this->makeTicket($user);
        $ticket->assigned_to = $user->id;
        $ticket->save();
        $ticket = $ticket->fresh(['reporter', 'assignee']) ?? $ticket;

        $this->stateListener()->handle(
            new TicketStateChanged($ticket, $admin, 'open', 'in_progress', 'corr-fcm-08')
        );

        $sentToUser = array_filter(
            $this->fcm->sentToUsers,
            fn ($s) => $s['user']->id === $user->id
        );

        $this->assertCount(1, $sentToUser);
    }

    // ── Payload del push maintenance ──

    public function test_fcm_data_for_maintenance_contains_from_state_to_state_and_recipient_role(): void
    {
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicketWithAssignment($this->makeReporter(), $maintenance);

        $this->stateListener()->handle(
            new TicketStateChanged($ticket, $admin, 'in_progress', 'resolved', 'corr-fcm-09')
        );

        $entry = collect($this->fcm->sentToUsers)
            ->firstWhere(fn ($s) => $s['user']->id === $maintenance->id);

        $this->assertNotNull($entry);
        $this->assertSame('in_progress', $entry['data']['from_state']);
        $this->assertSame('resolved', $entry['data']['to_state']);
        $this->assertSame('maintenance', $entry['data']['recipient_role']);
        $this->assertSame('ticket_state_changed', $entry['data']['type']);
    }

    // ── Sin rol maintenance → no push ──

    public function test_no_fcm_push_when_assignee_lacks_maintenance_role(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $nonMaintenance = User::factory()->create();
        $nonMaintenance->assignRole('reporter');

        $ticket = $this->makeTicket($reporter);
        $ticket->assigned_to = $nonMaintenance->id;
        $ticket->save();
        $ticket = $ticket->fresh(['reporter', 'assignee']) ?? $ticket;

        $this->stateListener()->handle(
            new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'corr-fcm-10')
        );

        $this->fcm->assertNotSentToUser($nonMaintenance->id);
        $this->fcm->assertSentToUser($reporter->id);
    }

    // ── Helpers ──

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makeMaintenance(string $suffix = ''): User
    {
        $user = User::factory()->create();
        $user->assignRole('maintenance');

        return $user;
    }

    private function makeReporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeTicket(User $reporter): Ticket
    {
        $location = Location::create([
            'name' => 'Sala FCM '.uniqid(),
            'building' => 'Edificio F',
            'floor' => '1',
            'room_code' => 'F-'.uniqid(),
            'qr_token' => 'qr-f-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat FCM '.uniqid(),
            'icon' => 'bolt',
            'description' => 'Test FCM',
        ]);

        return Ticket::create([
            'title' => 'Ticket FCM prueba',
            'description' => 'Descripcion FCM.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function makeTicketWithAssignment(User $reporter, User $assignee): Ticket
    {
        $ticket = $this->makeTicket($reporter);
        $ticket->assigned_to = $assignee->id;
        $ticket->save();

        return $ticket->fresh(['reporter', 'assignee']) ?? $ticket;
    }

    private function stateListener(): SendFcmPushOnTicketStateChanged
    {
        return new SendFcmPushOnTicketStateChanged($this->fcm);
    }

    private function assignmentListener(): SendFcmPushOnTicketAssigned
    {
        return new SendFcmPushOnTicketAssigned($this->fcm);
    }
}
