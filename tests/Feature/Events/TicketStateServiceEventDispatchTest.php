<?php

namespace Tests\Feature\Events;

use App\Events\TicketResolved;
use App\Events\TicketStateChanged;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 0 — punto 0.6
 *
 * Verifica que TicketStateService::transition() dispara los eventos
 * correctos según la transición de estado:
 *
 *   - resolved: dispara TicketStateChanged + TicketResolved
 *   - otros estados: dispara solo TicketStateChanged
 *   - sin cambio: no dispara ningún evento
 */
class TicketStateServiceEventDispatchTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────
    // Al resolver: dispara TicketStateChanged Y TicketResolved
    // ──────────────────────────────────────────────────────────

    public function test_resolving_ticket_dispatches_both_state_changed_and_resolved_events(): void
    {
        Event::fake([TicketStateChanged::class, TicketResolved::class]);

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket(state: 'in_progress');

        $this->service()->transition(
            ticket: $ticket,
            actor: $admin,
            toState: 'resolved',
            comment: 'Reparacion completada.',
            correlationId: 'corr-resolve-001',
        );

        Event::assertDispatched(TicketStateChanged::class, function (TicketStateChanged $event) use ($ticket, $admin): bool {
            return $event->ticket->id === $ticket->id
                && $event->actor->id === $admin->id
                && $event->fromState === 'in_progress'
                && $event->toState === 'resolved'
                && $event->correlationId === 'corr-resolve-001';
        });

        Event::assertDispatched(TicketResolved::class, function (TicketResolved $event) use ($ticket): bool {
            return $event->ticket->id === $ticket->id
                && $event->correlationId === 'corr-resolve-001';
        });
    }

    // ──────────────────────────────────────────────────────────
    // Al resolver: cada evento se dispara exactamente una vez
    // ──────────────────────────────────────────────────────────

    public function test_resolving_dispatches_each_event_exactly_once(): void
    {
        Event::fake([TicketStateChanged::class, TicketResolved::class]);

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket(state: 'in_progress');

        $this->service()->transition($ticket, $admin, 'resolved', 'OK resuelto.', 'corr-resolve-002');

        Event::assertDispatchedTimes(TicketStateChanged::class, 1);
        Event::assertDispatchedTimes(TicketResolved::class, 1);
    }

    // ──────────────────────────────────────────────────────────
    // Transición open → in_progress: solo TicketStateChanged,
    // NO TicketResolved
    // ──────────────────────────────────────────────────────────

    public function test_open_to_in_progress_dispatches_only_state_changed_not_resolved(): void
    {
        Event::fake([TicketStateChanged::class, TicketResolved::class]);

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket(state: 'open');

        $this->service()->transition(
            ticket: $ticket,
            actor: $admin,
            toState: 'in_progress',
            correlationId: 'corr-inprogress-001',
        );

        Event::assertDispatched(TicketStateChanged::class, function (TicketStateChanged $event) use ($ticket): bool {
            return $event->fromState === 'open'
                && $event->toState === 'in_progress'
                && $event->ticket->id === $ticket->id;
        });

        Event::assertNotDispatched(TicketResolved::class);
    }

    // ──────────────────────────────────────────────────────────
    // Transición in_progress → rejected: solo TicketStateChanged,
    // NO TicketResolved
    // ──────────────────────────────────────────────────────────

    public function test_in_progress_to_rejected_dispatches_only_state_changed_not_resolved(): void
    {
        Event::fake([TicketStateChanged::class, TicketResolved::class]);

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket(state: 'in_progress');

        $this->service()->transition(
            ticket: $ticket,
            actor: $admin,
            toState: 'rejected',
            comment: 'No se puede reproducir.',
            correlationId: 'corr-rejected-001',
        );

        Event::assertDispatched(TicketStateChanged::class, function (TicketStateChanged $event) use ($ticket): bool {
            return $event->fromState === 'in_progress'
                && $event->toState === 'rejected'
                && $event->ticket->id === $ticket->id;
        });

        Event::assertNotDispatched(TicketResolved::class);
    }

    // ──────────────────────────────────────────────────────────
    // TicketStateChanged incluye actor correcto en cada transición
    // ──────────────────────────────────────────────────────────

    public function test_state_changed_event_carries_correct_actor(): void
    {
        Event::fake([TicketStateChanged::class, TicketResolved::class]);

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket(state: 'open');

        $this->service()->transition($ticket, $admin, 'in_progress');

        Event::assertDispatched(TicketStateChanged::class, function (TicketStateChanged $event) use ($admin): bool {
            return $event->actor->id === $admin->id;
        });
    }

    // ──────────────────────────────────────────────────────────
    // Sin cambio de estado: no se dispara ningún evento
    // ──────────────────────────────────────────────────────────

    public function test_no_events_dispatched_when_state_does_not_change(): void
    {
        Event::fake([TicketStateChanged::class, TicketResolved::class]);

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket(state: 'open');

        // Mismo estado → el servicio retorna tempranamente sin disparar nada
        $this->service()->transition($ticket, $admin, 'open');

        Event::assertNothingDispatched();
    }

    // ──────────────────────────────────────────────────────────
    // correlationId del servicio llega al evento
    // ──────────────────────────────────────────────────────────

    public function test_correlation_id_is_propagated_to_both_events_on_resolve(): void
    {
        Event::fake([TicketStateChanged::class, TicketResolved::class]);

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket(state: 'in_progress');
        $correlationId = 'corr-propagate-both-001';

        $this->service()->transition($ticket, $admin, 'resolved', 'Listo.', $correlationId);

        Event::assertDispatched(TicketStateChanged::class, fn (TicketStateChanged $e): bool => $e->correlationId === $correlationId
        );

        Event::assertDispatched(TicketResolved::class, fn (TicketResolved $e): bool => $e->correlationId === $correlationId
        );
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    private function service(): TicketStateService
    {
        return $this->app->make(TicketStateService::class);
    }

    private function createTicket(string $state): Ticket
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        return Ticket::create([
            'title' => 'Ticket estado '.Str::random(6),
            'description' => 'Descripcion de ticket para test de eventos.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => $state,
            'priority' => 'medium',
        ]);
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

    private function createLocation(): Location
    {
        return Location::create([
            'name' => 'Sala Estado '.Str::upper(Str::random(4)),
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'EST-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-est-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Categoria Estado '.Str::lower(Str::random(6)),
            'icon' => 'bolt',
            'description' => 'Categoria de prueba',
        ]);
    }
}
