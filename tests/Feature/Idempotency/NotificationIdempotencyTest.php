<?php

namespace Tests\Feature\Idempotency;

use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketStateChanged;
use App\Listeners\CreateInAppNotificationOnTicketAssigned;
use App\Listeners\CreateInAppNotificationOnTicketCreated;
use App\Listeners\CreateInAppNotificationOnTicketStateChanged;
use App\Models\Category;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Notifications\NotificationPayload;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 5.3 — un reintento de un listener de notificación encolado no debe
 * crear una segunda notificación in-app para el mismo (usuario, tipo, ticket)
 * en el mismo día. Garantizado por la deduplicación en NotificationService.
 */
class NotificationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_of_in_app_listener_does_not_create_second_notification(): void
    {
        [$reporter, $actor, $ticket] = $this->makeReporterActorTicket();

        $listener = new CreateInAppNotificationOnTicketStateChanged(new NotificationService);
        $event = new TicketStateChanged($ticket, $actor, 'open', 'in_progress', 'corr-notif-idem');

        // Ejecución original + reintento del job
        $listener->handle($event);
        $listener->handle($event);

        $count = Notification::query()
            ->where('user_id', $reporter->id)
            ->where('type', 'ticket_state_changed')
            ->where('ticket_id', $ticket->id)
            ->count();

        $this->assertSame(1, $count, 'El reintento NO debe crear una segunda notificación in-app.');
    }

    public function test_notify_user_dedupes_same_ticket_type_and_day(): void
    {
        [$reporter, , $ticket] = $this->makeReporterActorTicket();

        $service = new NotificationService;

        $payload = new NotificationPayload(type: 'ticket_state_changed', title: 'T', body: 'B', url: '/u', icon: '🔔', ticketId: $ticket->id);
        $service->notifyUser($reporter, $payload);
        $service->notifyUser($reporter, $payload);

        $this->assertSame(
            1,
            Notification::where('user_id', $reporter->id)->where('ticket_id', $ticket->id)->count(),
        );
    }

    public function test_notify_user_without_ticket_id_is_not_deduped(): void
    {
        [$reporter] = $this->makeReporterActorTicket();

        $service = new NotificationService;

        // Sin ticketId no hay clave de dedup → ambas se crean (comportamiento previo intacto).
        $noTicketPayload = new NotificationPayload(type: 'system', title: 'T', body: 'B');
        $service->notifyUser($reporter, $noTicketPayload);
        $service->notifyUser($reporter, $noTicketPayload);

        $this->assertSame(
            2,
            Notification::where('user_id', $reporter->id)->where('type', 'system')->count(),
        );
    }

    public function test_different_tickets_are_notified_independently(): void
    {
        [$reporter, , $ticketA] = $this->makeReporterActorTicket();
        $ticketB = $this->makeTicket($reporter);

        $service = new NotificationService;

        $service->notifyUser($reporter, new NotificationPayload(type: 'ticket_state_changed', title: 'T', body: 'B', url: '/u', icon: '🔔', ticketId: $ticketA->id));
        $service->notifyUser($reporter, new NotificationPayload(type: 'ticket_state_changed', title: 'T', body: 'B', url: '/u', icon: '🔔', ticketId: $ticketB->id));

        $this->assertSame(
            2,
            Notification::where('user_id', $reporter->id)->where('type', 'ticket_state_changed')->count(),
        );
    }

    // ──────────────────────────────────────────────────────────
    // Fase 5.4 — dedup_key permite múltiples cambios de estado
    // legítimos del mismo ticket el mismo día, sin perder idempotencia.
    // ──────────────────────────────────────────────────────────

    public function test_state_change_open_to_in_progress_creates_notification(): void
    {
        [$reporter, $actor, $ticket] = $this->makeReporterActorTicket();

        $this->stateListener()->handle($this->stateEvent($ticket, $actor, 'open', 'in_progress'));

        $this->assertSame(1, $this->stateNotifCount($reporter, $ticket));
    }

    public function test_state_change_in_progress_to_resolved_same_day_creates_second_notification(): void
    {
        [$reporter, $actor, $ticket] = $this->makeReporterActorTicket();

        $listener = $this->stateListener();
        $listener->handle($this->stateEvent($ticket, $actor, 'open', 'in_progress'));
        $listener->handle($this->stateEvent($ticket, $actor, 'in_progress', 'resolved'));

        // Dos transiciones distintas el mismo día → dos notificaciones (antes se perdía la segunda).
        $this->assertSame(2, $this->stateNotifCount($reporter, $ticket));
    }

    public function test_retry_of_open_to_in_progress_does_not_duplicate(): void
    {
        [$reporter, $actor, $ticket] = $this->makeReporterActorTicket();

        $listener = $this->stateListener();
        $event = $this->stateEvent($ticket, $actor, 'open', 'in_progress');
        $listener->handle($event);
        $listener->handle($event); // retry del mismo evento

        $this->assertSame(1, $this->stateNotifCount($reporter, $ticket));
    }

    public function test_retry_of_resolved_does_not_duplicate(): void
    {
        [$reporter, $actor, $ticket] = $this->makeReporterActorTicket();

        $listener = $this->stateListener();
        $listener->handle($this->stateEvent($ticket, $actor, 'open', 'in_progress'));

        $resolved = $this->stateEvent($ticket, $actor, 'in_progress', 'resolved');
        $listener->handle($resolved);
        $listener->handle($resolved); // retry del resolved

        // in_progress (1) + resolved (1); el retry de resolved NO añade un tercero.
        $this->assertSame(2, $this->stateNotifCount($reporter, $ticket));
        $this->assertSame(
            1,
            Notification::where('dedup_key', "ticket_state_changed:{$ticket->id}:in_progress:resolved")->count(),
        );
    }

    public function test_ticket_assigned_retry_does_not_duplicate(): void
    {
        $assignee = User::factory()->create();
        $actor = User::factory()->create();
        $ticket = $this->makeTicket($assignee);

        $listener = new CreateInAppNotificationOnTicketAssigned(new NotificationService);
        $event = new TicketAssigned($ticket, $actor, null, $assignee, 'assigned', 'corr-assign');
        $listener->handle($event);
        $listener->handle($event); // retry

        $this->assertSame(
            1,
            Notification::where('user_id', $assignee->id)->where('type', 'ticket_assigned')->count(),
        );
    }

    public function test_ticket_created_retry_does_not_duplicate(): void
    {
        $this->ensureRolesExist();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $reporter = User::factory()->create();
        $ticket = $this->makeTicket($reporter);

        $listener = new CreateInAppNotificationOnTicketCreated(new NotificationService);
        $event = new TicketCreated($ticket, 'corr-created');
        $listener->handle($event);
        $listener->handle($event); // retry

        $this->assertSame(
            1,
            Notification::where('user_id', $admin->id)->where('type', 'ticket_created')->count(),
        );
    }

    // ── Mantenimiento: notificaciones al técnico asignado ──

    public function test_maintenance_assignee_receives_notification_on_state_change(): void
    {
        $this->ensureRolesExist();

        $reporter = User::factory()->create();
        $maintenance = User::factory()->create();
        $maintenance->assignRole('maintenance');

        $actor = User::factory()->create();
        $ticket = $this->makeTicketWithAssignee($reporter, $maintenance);

        $this->stateListener()->handle($this->stateEvent($ticket, $actor, 'open', 'in_progress'));

        $this->assertSame(1, $this->stateNotifCount($maintenance, $ticket));
    }

    public function test_maintenance_state_change_retry_does_not_duplicate(): void
    {
        $this->ensureRolesExist();

        $reporter = User::factory()->create();
        $maintenance = User::factory()->create();
        $maintenance->assignRole('maintenance');

        $actor = User::factory()->create();
        $ticket = $this->makeTicketWithAssignee($reporter, $maintenance);

        $listener = $this->stateListener();
        $event = $this->stateEvent($ticket, $actor, 'open', 'in_progress');
        $listener->handle($event);
        $listener->handle($event); // retry

        $this->assertSame(1, $this->stateNotifCount($maintenance, $ticket));
    }

    public function test_reporter_and_maintenance_receive_independent_notifications(): void
    {
        $this->ensureRolesExist();

        $reporter = User::factory()->create();
        $maintenance = User::factory()->create();
        $maintenance->assignRole('maintenance');

        $actor = User::factory()->create();
        $ticket = $this->makeTicketWithAssignee($reporter, $maintenance);

        $this->stateListener()->handle($this->stateEvent($ticket, $actor, 'open', 'in_progress'));

        $this->assertSame(1, $this->stateNotifCount($reporter, $ticket));
        $this->assertSame(1, $this->stateNotifCount($maintenance, $ticket));
        $this->assertSame(2, Notification::where('ticket_id', $ticket->id)->where('type', 'ticket_state_changed')->count());
    }

    public function test_maintenance_does_not_receive_notification_when_actor_is_self(): void
    {
        $this->ensureRolesExist();

        $reporter = User::factory()->create();
        $maintenance = User::factory()->create();
        $maintenance->assignRole('maintenance');

        $ticket = $this->makeTicketWithAssignee($reporter, $maintenance);

        // Actor es el mismo maintenance
        $this->stateListener()->handle($this->stateEvent($ticket, $maintenance, 'open', 'in_progress'));

        $this->assertSame(0, $this->stateNotifCount($maintenance, $ticket));
        $this->assertSame(1, $this->stateNotifCount($reporter, $ticket));
    }

    public function test_no_duplicate_when_reporter_equals_maintenance_assignee(): void
    {
        $this->ensureRolesExist();

        // Edge case: un usuario es reporter y también maintenance
        $user = User::factory()->create();
        $user->assignRole('reporter');
        $user->assignRole('maintenance');

        $actor = User::factory()->create();
        $ticket = $this->makeTicketWithAssignee($user, $user);

        $this->stateListener()->handle($this->stateEvent($ticket, $actor, 'open', 'in_progress'));

        // Dedup por user_id+dedup_key previene la segunda notificación → solo 1
        $this->assertSame(1, $this->stateNotifCount($user, $ticket));
    }

    // ── Helpers ──

    private function stateListener(): CreateInAppNotificationOnTicketStateChanged
    {
        return new CreateInAppNotificationOnTicketStateChanged(new NotificationService);
    }

    private function stateEvent(Ticket $ticket, User $actor, string $from, string $to): TicketStateChanged
    {
        return new TicketStateChanged($ticket, $actor, $from, $to, "corr-{$from}-{$to}");
    }

    private function stateNotifCount(User $reporter, Ticket $ticket): int
    {
        return Notification::query()
            ->where('user_id', $reporter->id)
            ->where('type', 'ticket_state_changed')
            ->where('ticket_id', $ticket->id)
            ->count();
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }

    /**
     * @return array{0: User, 1: User, 2: Ticket}
     */
    private function makeReporterActorTicket(): array
    {
        $reporter = User::factory()->create();
        $actor = User::factory()->create();
        $ticket = $this->makeTicket($reporter);

        return [$reporter, $actor, $ticket];
    }

    private function makeTicket(User $reporter): Ticket
    {
        $location = Location::create([
            'name' => 'Aula Notif '.uniqid(),
            'building' => 'Edificio N',
            'floor' => '1',
            'room_code' => 'N-'.uniqid(),
            'qr_token' => 'qr-n-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Categoria Notif '.uniqid(),
            'icon' => 'bolt',
            'description' => 'Incidencias',
        ]);

        return Ticket::create([
            'title' => 'Ticket idempotencia notif',
            'description' => 'Ticket para probar idempotencia de notificaciones.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function makeTicketWithAssignee(User $reporter, User $assignee): Ticket
    {
        $ticket = $this->makeTicket($reporter);
        $ticket->assigned_to = $assignee->id;
        $ticket->save();

        return $ticket->fresh(['reporter', 'assignee']) ?? $ticket;
    }
}
