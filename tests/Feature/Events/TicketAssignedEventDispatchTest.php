<?php

namespace Tests\Feature\Events;

use App\Events\TicketAssigned;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Observer — Brecha 2
 *
 * Verifica que TicketAssignmentService despacha TicketAssigned a través de
 * dispatchAssignmentEvent / finalizeAssignment, sin conocer los listeners
 * concretos que reaccionan al evento.
 *
 * Cubre:
 *  - TicketAssigned se despacha exactamente una vez.
 *  - El payload contiene ticket, actor, previousAssignee, newAssignee,
 *    action y correlationId.
 *  - El servicio no importa ni llama listeners concretos (garantizado por
 *    ObserverDecouplingTest; aquí sólo verificamos el contrato del evento).
 */
final class TicketAssignedEventDispatchTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────
    // assignByAdmin → TicketAssigned dispatched once
    // ──────────────────────────────────────────────────────────

    public function test_assignment_service_dispatches_ticket_assigned_event_once(): void
    {
        Event::fake([TicketAssigned::class]);

        $admin = $this->createUserWithRole('admin');
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        /** @var TicketAssignmentService $service */
        $service = app(TicketAssignmentService::class);

        // assignByAdmin internamente llama applyAdminAssignment → finalizeAssignment
        // → dispatchAssignmentEvent → event(new TicketAssigned(...))
        $service->assignByAdmin(
            ticket: $ticket,
            actor: $admin,
            target: $maintenance,
        );

        Event::assertDispatchedTimes(TicketAssigned::class, 1);
    }

    public function test_assignment_service_dispatches_ticket_assigned_with_correct_payload(): void
    {
        Event::fake([TicketAssigned::class]);

        $admin = $this->createUserWithRole('admin');
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        /** @var TicketAssignmentService $service */
        $service = app(TicketAssignmentService::class);

        $service->assignByAdmin(
            ticket: $ticket,
            actor: $admin,
            target: $maintenance,
        );

        Event::assertDispatched(TicketAssigned::class, function (TicketAssigned $event) use (
            $ticket,
            $admin,
            $maintenance
        ): bool {
            return $event->ticket->id === $ticket->id
                && $event->actor->id === $admin->id
                && $event->newAssignee?->id === $maintenance->id
                && $event->previousAssignee === null          // ticket sin asignación previa
                && in_array($event->action, ['assigned', 'reassigned'], true)
                && $event->correlationId !== '';
        });
    }

    // ──────────────────────────────────────────────────────────
    // reassignByAdmin → TicketAssigned dispatched with previousAssignee
    // ──────────────────────────────────────────────────────────

    public function test_reassign_dispatches_ticket_assigned_with_previous_assignee(): void
    {
        $admin = $this->createUserWithRole('admin');
        $first = $this->createUserWithRole('maintenance');
        $second = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        /** @var TicketAssignmentService $service */
        $service = app(TicketAssignmentService::class);

        // Primera asignación sin fake para no interferir
        $service->assignByAdmin(ticket: $ticket, actor: $admin, target: $first);

        // Reasignación — aquí verificamos el evento
        Event::fake([TicketAssigned::class]);

        // Refrescamos el ticket para tener el estado actual de la BD
        $ticket = $ticket->fresh() ?? $ticket;

        $service->reassignByAdmin(ticket: $ticket, actor: $admin, target: $second);

        Event::assertDispatchedTimes(TicketAssigned::class, 1);

        Event::assertDispatched(TicketAssigned::class, function (TicketAssigned $event) use (
            $ticket,
            $admin,
            $first,
            $second
        ): bool {
            return $event->ticket->id === $ticket->id
                && $event->actor->id === $admin->id
                && $event->newAssignee?->id === $second->id
                && $event->previousAssignee?->id === $first->id
                && $event->action === 'reassigned'
                && $event->correlationId !== '';
        });
    }

    // ──────────────────────────────────────────────────────────
    // claimByMaintenance → TicketAssigned dispatched once
    // ──────────────────────────────────────────────────────────

    public function test_claim_by_maintenance_dispatches_ticket_assigned_event(): void
    {
        Event::fake([TicketAssigned::class]);

        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        /** @var TicketAssignmentService $service */
        $service = app(TicketAssignmentService::class);

        $service->claimByMaintenance(ticket: $ticket, actor: $maintenance);

        Event::assertDispatchedTimes(TicketAssigned::class, 1);

        Event::assertDispatched(TicketAssigned::class, function (TicketAssigned $event) use (
            $ticket,
            $maintenance
        ): bool {
            return $event->ticket->id === $ticket->id
                && $event->actor->id === $maintenance->id
                && $event->newAssignee?->id === $maintenance->id
                && $event->action === 'claimed'
                && $event->correlationId !== '';
        });
    }

    // ──────────────────────────────────────────────────────────
    // Helpers — patrón real del proyecto (sin factories para Location/Category)
    // ──────────────────────────────────────────────────────────

    private function createUserWithRole(string $role): User
    {
        $this->ensureRolesExist();

        /** @var User $user */
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

    private function openTicketFor(User $reporter): Ticket
    {
        $location = Location::create([
            'name' => 'Sala Assign '.Str::upper(Str::random(4)),
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'ASN-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-asn-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Categoria Assign '.Str::lower(Str::random(6)),
            'icon' => 'bolt',
            'description' => 'Categoria de prueba para Observer',
        ]);

        return Ticket::create([
            'title' => 'Ticket asignacion '.Str::random(6),
            'description' => 'Descripcion de ticket para test de asignacion.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
            'assigned_to' => null,
            'assignment_locked' => false,
        ]);
    }
}
