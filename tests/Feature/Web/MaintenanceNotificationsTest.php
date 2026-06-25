<?php

namespace Tests\Feature\Web;

use App\Events\TicketAssigned;
use App\Events\TicketStateChanged;
use App\Listeners\CreateInAppNotificationOnTicketAssigned;
use App\Listeners\CreateInAppNotificationOnTicketStateChanged;
use App\Models\Category;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verificación integral de que usuarios con rol maintenance reciben
 * notificaciones in-app por asignación y cambio de estado de ticket.
 * Cubre: asignación, reasignación, desasignación, state-changed,
 * anti self-notification, y endpoint de dropdown.
 */
class MaintenanceNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
    }

    // ── Asignación ──

    public function test_maintenance_receives_in_app_notification_when_admin_assigns_ticket(): void
    {
        [$admin, $maintenance, $ticket] = $this->makeAdminMaintenanceTicket();

        $this->assignmentListener()->handle(
            new TicketAssigned($ticket, $admin, null, $maintenance, 'assigned', 'corr-assign-01')
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $maintenance->id,
            'type' => 'ticket_assigned',
        ]);
    }

    public function test_maintenance_receives_in_app_notification_on_reassignment(): void
    {
        [$admin, $maintenance, $ticket] = $this->makeAdminMaintenanceTicket();
        $previousMaintenance = $this->makeMaintenance('prev-m');

        $this->assignmentListener()->handle(
            new TicketAssigned($ticket, $admin, $previousMaintenance, $maintenance, 'reassigned', 'corr-reassign-01')
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $maintenance->id,
            'type' => 'ticket_reassigned',
        ]);
    }

    public function test_previous_maintenance_receives_notification_on_unassignment(): void
    {
        [$admin, , $ticket] = $this->makeAdminMaintenanceTicket();
        $previousMaintenance = $this->makeMaintenance('prev-unassign');

        $this->assignmentListener()->handle(
            new TicketAssigned($ticket, $admin, $previousMaintenance, null, 'unassigned', 'corr-unassign-01')
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $previousMaintenance->id,
            'type' => 'ticket_unassigned',
        ]);
    }

    public function test_claimed_action_does_not_create_notification_for_actor(): void
    {
        [, $maintenance, $ticket] = $this->makeAdminMaintenanceTicket();

        $this->assignmentListener()->handle(
            new TicketAssigned($ticket, $maintenance, null, $maintenance, 'claimed', 'corr-claimed-01')
        );

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_released_action_does_not_create_notification_for_actor(): void
    {
        [, $maintenance, $ticket] = $this->makeAdminMaintenanceTicket();

        $this->assignmentListener()->handle(
            new TicketAssigned($ticket, $maintenance, $maintenance, null, 'released', 'corr-released-01')
        );

        $this->assertDatabaseCount('notifications', 0);
    }

    // ── Estado ──

    public function test_maintenance_receives_in_app_notification_on_state_change(): void
    {
        [$admin, $maintenance, $ticket] = $this->makeAdminMaintenanceTicketWithAssignment();

        $this->stateListener()->handle(
            new TicketStateChanged($ticket, $admin, 'open', 'in_progress', 'corr-state-01')
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $maintenance->id,
            'type' => 'ticket_state_changed',
        ]);
    }

    public function test_reporter_still_receives_notification_on_state_change(): void
    {
        $reporter = $this->makeReporter();
        [$admin, $maintenance, $ticket] = $this->makeAdminMaintenanceTicketWithAssignment($reporter);

        $this->stateListener()->handle(
            new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'corr-state-02')
        );

        $this->assertDatabaseHas('notifications', ['user_id' => $reporter->id, 'type' => 'ticket_state_changed']);
        $this->assertDatabaseHas('notifications', ['user_id' => $maintenance->id, 'type' => 'ticket_state_changed']);
        $this->assertSame(2, Notification::where('ticket_id', $ticket->id)->count());
    }

    public function test_maintenance_does_not_receive_notification_for_own_state_change(): void
    {
        $reporter = $this->makeReporter();
        [, $maintenance, $ticket] = $this->makeAdminMaintenanceTicketWithAssignment($reporter);

        $this->stateListener()->handle(
            new TicketStateChanged($ticket, $maintenance, 'open', 'in_progress', 'corr-state-03')
        );

        $this->assertDatabaseMissing('notifications', ['user_id' => $maintenance->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $reporter->id]);
    }

    public function test_maintenance_receives_notification_for_all_relevant_state_transitions(): void
    {
        $reporter = $this->makeReporter();
        [$admin, $maintenance, $ticket] = $this->makeAdminMaintenanceTicketWithAssignment($reporter);
        $listener = $this->stateListener();

        $listener->handle(new TicketStateChanged($ticket, $admin, 'open', 'in_progress', 'c1'));
        $listener->handle(new TicketStateChanged($ticket, $admin, 'in_progress', 'resolved', 'c2'));

        $maintenanceNotifs = Notification::where('user_id', $maintenance->id)->count();
        $this->assertSame(2, $maintenanceNotifs);
    }

    // ── Endpoint / dropdown ──

    public function test_maintenance_notifications_appear_in_dropdown_endpoint(): void
    {
        $maintenance = $this->makeMaintenance();

        Notification::create([
            'user_id' => $maintenance->id,
            'type' => 'ticket_assigned',
            'title' => 'Nuevo ticket asignado',
            'body' => 'Se te asignó el ticket: Luz rota',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($maintenance)->getJson(route('notifications.index'));

        $response->assertOk();
        $response->assertJsonPath('unread_count', 1);
        $response->assertJsonCount(1, 'notifications');
        $response->assertJsonPath('notifications.0.type', 'ticket_assigned');
    }

    public function test_maintenance_does_not_see_other_users_notifications_in_dropdown(): void
    {
        $maintenance = $this->makeMaintenance();
        $otherMaintenance = $this->makeMaintenance('other');

        Notification::create([
            'user_id' => $otherMaintenance->id,
            'type' => 'ticket_assigned',
            'title' => 'Otro',
            'body' => 'No debe aparecer',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($maintenance)->getJson(route('notifications.index'));

        $response->assertOk();
        $response->assertJsonPath('unread_count', 0);
        $response->assertJsonCount(0, 'notifications');
    }

    // ── Helpers ──

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
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

    /** @return array{0: User, 1: User, 2: Ticket} */
    private function makeAdminMaintenanceTicket(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $maintenance = $this->makeMaintenance();
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        return [$admin, $maintenance, $ticket];
    }

    /** @return array{0: User, 1: User, 2: Ticket} */
    private function makeAdminMaintenanceTicketWithAssignment(?User $reporter = null): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $maintenance = $this->makeMaintenance();
        $reporter ??= $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        $ticket->assigned_to = $maintenance->id;
        $ticket->save();

        return [$admin, $maintenance, $ticket->fresh(['reporter', 'assignee']) ?? $ticket];
    }

    private function makeTicket(User $reporter): Ticket
    {
        $location = Location::create([
            'name' => 'Aula Test '.uniqid(),
            'building' => 'Edificio T',
            'floor' => '1',
            'room_code' => 'T-'.uniqid(),
            'qr_token' => 'qr-t-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat Test '.uniqid(),
            'icon' => 'bolt',
            'description' => 'Test',
        ]);

        return Ticket::create([
            'title' => 'Ticket de prueba maintenance',
            'description' => 'Descripcion de prueba.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function assignmentListener(): CreateInAppNotificationOnTicketAssigned
    {
        return new CreateInAppNotificationOnTicketAssigned(new NotificationService);
    }

    private function stateListener(): CreateInAppNotificationOnTicketStateChanged
    {
        return new CreateInAppNotificationOnTicketStateChanged(new NotificationService);
    }
}
