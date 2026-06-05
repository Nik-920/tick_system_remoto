<?php

namespace Tests\Unit\Services\Tickets;

use App\Events\TicketResolved;
use App\Events\TicketStateChanged;
use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 0 — Tests de protección para TicketStateService.
 *
 * Cubre:
 *  - Transiciones permitidas y bloqueadas por rol
 *  - Maintenance ownership guard (solo tickets asignados a él)
 *  - Reglas de comment obligatorio
 *  - state_history se crea correctamente
 *  - Eventos se disparan según corresponda
 *  - Parity: availableTransitionsFor coincide con lo que transition permitiría
 */
class TicketStateServiceTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────
    // Transiciones PERMITIDAS por rol
    // ──────────────────────────────────────────────────────────────────

    public function test_maintenance_can_transition_open_to_in_progress_on_own_ticket(): void
    {
        Event::fake();

        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('open', assignedTo: $maintenance);

        $result = $this->service()->transition($ticket, $maintenance, 'in_progress');

        $this->assertSame('in_progress', $result->state);
    }

    public function test_maintenance_can_transition_in_progress_to_resolved_on_own_ticket(): void
    {
        Event::fake();

        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('in_progress', assignedTo: $maintenance);

        $result = $this->service()->transition($ticket, $maintenance, 'resolved', 'Reparación completada.');

        $this->assertSame('resolved', $result->state);
    }

    public function test_admin_can_transition_open_to_in_progress(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('open');

        $result = $this->service()->transition($ticket, $admin, 'in_progress');

        $this->assertSame('in_progress', $result->state);
    }

    public function test_admin_can_transition_in_progress_to_resolved(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('in_progress');

        $result = $this->service()->transition($ticket, $admin, 'resolved', 'Solucionado.');

        $this->assertSame('resolved', $result->state);
    }

    public function test_admin_can_transition_in_progress_to_rejected(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('in_progress');

        $result = $this->service()->transition($ticket, $admin, 'rejected', 'No aplica.');

        $this->assertSame('rejected', $result->state);
    }

    public function test_admin_can_reopen_rejected_ticket(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('rejected');

        $result = $this->service()->transition($ticket, $admin, 'open', 'Reevaluación necesaria.');

        $this->assertSame('open', $result->state);
    }

    public function test_super_admin_can_reopen_resolved_ticket(): void
    {
        Event::fake();

        $superAdmin = $this->createUserWithRole('super_admin');
        $ticket = $this->createTicket('resolved');

        $result = $this->service()->transition($ticket, $superAdmin, 'open', 'Requiere re-inspección.');

        $this->assertSame('open', $result->state);
    }

    public function test_super_admin_can_reopen_rejected_ticket(): void
    {
        Event::fake();

        $superAdmin = $this->createUserWithRole('super_admin');
        $ticket = $this->createTicket('rejected');

        $result = $this->service()->transition($ticket, $superAdmin, 'open', 'Revisar de nuevo.');

        $this->assertSame('open', $result->state);
    }

    // ──────────────────────────────────────────────────────────────────
    // Transiciones BLOQUEADAS por rol
    // ──────────────────────────────────────────────────────────────────

    public function test_reporter_cannot_transition_open_to_in_progress(): void
    {
        Event::fake();

        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket('open');

        $this->expectException(InvalidArgumentException::class);
        $this->service()->transition($ticket, $reporter, 'in_progress');
    }

    public function test_reporter_cannot_resolve_ticket(): void
    {
        Event::fake();

        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket('in_progress');

        $this->expectException(InvalidArgumentException::class);
        $this->service()->transition($ticket, $reporter, 'resolved', 'Intento ilegítimo.');
    }

    public function test_reporter_cannot_reject_ticket(): void
    {
        Event::fake();

        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket('in_progress');

        $this->expectException(InvalidArgumentException::class);
        $this->service()->transition($ticket, $reporter, 'rejected', 'No debería.');
    }

    public function test_maintenance_cannot_reject_ticket(): void
    {
        Event::fake();

        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('in_progress', assignedTo: $maintenance);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->transition($ticket, $maintenance, 'rejected', 'No tiene permiso.');
    }

    public function test_admin_cannot_reopen_resolved_ticket(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('resolved');

        $this->expectException(InvalidArgumentException::class);
        $this->service()->transition($ticket, $admin, 'open', 'No tiene permiso.');
    }

    public function test_maintenance_cannot_reopen_rejected_ticket(): void
    {
        Event::fake();

        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('rejected', assignedTo: $maintenance);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->transition($ticket, $maintenance, 'open', 'No tiene permiso.');
    }

    // ──────────────────────────────────────────────────────────────────
    // Invalid transitions (state machine)
    // ──────────────────────────────────────────────────────────────────

    public function test_cannot_skip_from_open_to_resolved(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('open');

        $this->expectException(InvalidArgumentException::class);
        $this->service()->transition($ticket, $admin, 'resolved', 'Saltar estados.');
    }

    public function test_cannot_skip_from_open_to_rejected(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('open');

        $this->expectException(InvalidArgumentException::class);
        $this->service()->transition($ticket, $admin, 'rejected', 'Saltar estados.');
    }

    // ──────────────────────────────────────────────────────────────────
    // Maintenance ownership guard
    // ──────────────────────────────────────────────────────────────────

    public function test_maintenance_cannot_transition_unassigned_ticket(): void
    {
        Event::fake();

        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('open'); // sin asignar

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('asignados a él');
        $this->service()->transition($ticket, $maintenance, 'in_progress');
    }

    public function test_maintenance_cannot_transition_ticket_assigned_to_other(): void
    {
        Event::fake();

        $maintenance = $this->createUserWithRole('maintenance');
        $otherMaintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('open', assignedTo: $otherMaintenance);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('asignados a él');
        $this->service()->transition($ticket, $maintenance, 'in_progress');
    }

    // ──────────────────────────────────────────────────────────────────
    // Comment rules
    // ──────────────────────────────────────────────────────────────────

    public function test_resolved_requires_comment(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('in_progress');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('comentario es obligatorio');
        $this->service()->transition($ticket, $admin, 'resolved');
    }

    public function test_rejected_requires_comment(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('in_progress');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('comentario es obligatorio');
        $this->service()->transition($ticket, $admin, 'rejected');
    }

    public function test_reopen_from_resolved_requires_comment(): void
    {
        Event::fake();

        $superAdmin = $this->createUserWithRole('super_admin');
        $ticket = $this->createTicket('resolved');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('comentario es obligatorio');
        $this->service()->transition($ticket, $superAdmin, 'open');
    }

    public function test_reopen_from_rejected_requires_comment(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('rejected');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('comentario es obligatorio');
        $this->service()->transition($ticket, $admin, 'open');
    }

    public function test_open_to_in_progress_does_not_require_comment(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('open');

        $result = $this->service()->transition($ticket, $admin, 'in_progress');

        $this->assertSame('in_progress', $result->state);
    }

    // ──────────────────────────────────────────────────────────────────
    // state_history
    // ──────────────────────────────────────────────────────────────────

    public function test_transition_creates_state_history_record(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('open');

        $this->service()->transition($ticket, $admin, 'in_progress');

        $history = StateHistory::where('ticket_id', $ticket->id)
            ->where('from_state', 'open')
            ->where('to_state', 'in_progress')
            ->where('changed_by', $admin->id)
            ->first();

        $this->assertNotNull($history);
    }

    public function test_transition_records_comment_in_state_history(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('in_progress');

        $this->service()->transition($ticket, $admin, 'resolved', 'Trabajo terminado.');

        $history = StateHistory::where('ticket_id', $ticket->id)
            ->where('to_state', 'resolved')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('Trabajo terminado.', $history->comment);
    }

    public function test_no_state_history_when_same_state(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('open');

        $countBefore = StateHistory::where('ticket_id', $ticket->id)->count();

        $this->service()->transition($ticket, $admin, 'open');

        $countAfter = StateHistory::where('ticket_id', $ticket->id)->count();
        $this->assertSame($countBefore, $countAfter);
    }

    // ──────────────────────────────────────────────────────────────────
    // Events
    // ──────────────────────────────────────────────────────────────────

    public function test_ticket_state_changed_event_dispatched(): void
    {
        Event::fake([TicketStateChanged::class, TicketResolved::class]);

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('open');

        $this->service()->transition($ticket, $admin, 'in_progress');

        Event::assertDispatched(TicketStateChanged::class, function (TicketStateChanged $event) use ($ticket): bool {
            return $event->ticket->id === $ticket->id
                && $event->fromState === 'open'
                && $event->toState === 'in_progress';
        });
    }

    public function test_ticket_resolved_event_dispatched_only_on_resolve(): void
    {
        Event::fake([TicketStateChanged::class, TicketResolved::class]);

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('in_progress');

        $this->service()->transition($ticket, $admin, 'resolved', 'Hecho.');

        Event::assertDispatched(TicketResolved::class, function (TicketResolved $event) use ($ticket): bool {
            return $event->ticket->id === $ticket->id;
        });
    }

    public function test_ticket_resolved_event_not_dispatched_on_non_resolve(): void
    {
        Event::fake([TicketStateChanged::class, TicketResolved::class]);

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('in_progress');

        $this->service()->transition($ticket, $admin, 'rejected', 'No aplica.');

        Event::assertNotDispatched(TicketResolved::class);
    }

    // ──────────────────────────────────────────────────────────────────
    // resolved_at management
    // ──────────────────────────────────────────────────────────────────

    public function test_resolved_at_is_set_when_resolving(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('in_progress');

        $result = $this->service()->transition($ticket, $admin, 'resolved', 'Completado.');

        $this->assertNotNull($result->resolved_at);
    }

    public function test_resolved_at_is_cleared_when_reopening_from_resolved(): void
    {
        Event::fake();

        $superAdmin = $this->createUserWithRole('super_admin');
        $ticket = $this->createTicket('resolved');
        $ticket->resolved_at = now();
        $ticket->save();

        $result = $this->service()->transition($ticket, $superAdmin, 'open', 'Re-inspección necesaria.');

        $this->assertNull($result->resolved_at);
    }

    // ──────────────────────────────────────────────────────────────────
    // availableTransitionsFor — parity con transition
    // ──────────────────────────────────────────────────────────────────

    /**
     * Verifica que para cada combinación de (rol, estado), las transiciones
     * devueltas por availableTransitionsFor() sean exactamente las mismas
     * que transition() permitiría sin lanzar excepción.
     *
     * Esta es la prueba de paridad más importante: si divergen, el UI
     * mostrará botones que el backend rechaza (o viceversa).
     */
    public function test_available_transitions_match_what_transition_allows_for_maintenance(): void
    {
        Event::fake();

        $maintenance = $this->createUserWithRole('maintenance');

        foreach (['open', 'in_progress', 'resolved', 'rejected'] as $fromState) {
            $ticket = $this->createTicket($fromState, assignedTo: $maintenance);
            $available = $this->service()->availableTransitionsFor($ticket, $maintenance);

            foreach (['open', 'in_progress', 'resolved', 'rejected'] as $toState) {
                if ($fromState === $toState) {
                    continue;
                }

                $transitionSucceeded = $this->tryTransition($fromState, $toState, $maintenance, assigned: true);

                if (in_array($toState, $available, true)) {
                    $this->assertTrue(
                        $transitionSucceeded,
                        "availableTransitionsFor includes '{$toState}' from '{$fromState}' for maintenance, but transition() rejects it."
                    );
                } else {
                    $this->assertFalse(
                        $transitionSucceeded,
                        "transition() allows '{$fromState}'→'{$toState}' for maintenance, but availableTransitionsFor does not include it."
                    );
                }
            }
        }
    }

    public function test_available_transitions_match_what_transition_allows_for_admin(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');

        foreach (['open', 'in_progress', 'resolved', 'rejected'] as $fromState) {
            $ticket = $this->createTicket($fromState);
            $available = $this->service()->availableTransitionsFor($ticket, $admin);

            foreach (['open', 'in_progress', 'resolved', 'rejected'] as $toState) {
                if ($fromState === $toState) {
                    continue;
                }

                $transitionSucceeded = $this->tryTransition($fromState, $toState, $admin, assigned: false);

                if (in_array($toState, $available, true)) {
                    $this->assertTrue(
                        $transitionSucceeded,
                        "availableTransitionsFor includes '{$toState}' from '{$fromState}' for admin, but transition() rejects it."
                    );
                } else {
                    $this->assertFalse(
                        $transitionSucceeded,
                        "transition() allows '{$fromState}'→'{$toState}' for admin, but availableTransitionsFor does not include it."
                    );
                }
            }
        }
    }

    public function test_available_transitions_match_what_transition_allows_for_super_admin(): void
    {
        Event::fake();

        $superAdmin = $this->createUserWithRole('super_admin');

        foreach (['open', 'in_progress', 'resolved', 'rejected'] as $fromState) {
            $ticket = $this->createTicket($fromState);
            $available = $this->service()->availableTransitionsFor($ticket, $superAdmin);

            foreach (['open', 'in_progress', 'resolved', 'rejected'] as $toState) {
                if ($fromState === $toState) {
                    continue;
                }

                $transitionSucceeded = $this->tryTransition($fromState, $toState, $superAdmin, assigned: false);

                if (in_array($toState, $available, true)) {
                    $this->assertTrue(
                        $transitionSucceeded,
                        "availableTransitionsFor includes '{$toState}' from '{$fromState}' for super_admin, but transition() rejects it."
                    );
                } else {
                    $this->assertFalse(
                        $transitionSucceeded,
                        "transition() allows '{$fromState}'→'{$toState}' for super_admin, but availableTransitionsFor does not include it."
                    );
                }
            }
        }
    }

    public function test_available_transitions_returns_empty_for_reporter(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        foreach (['open', 'in_progress', 'resolved', 'rejected'] as $state) {
            $ticket = $this->createTicket($state);
            $available = $this->service()->availableTransitionsFor($ticket, $reporter);

            $this->assertSame([], $available, "Reporter should have no available transitions from '{$state}'.");
        }
    }

    public function test_available_transitions_empty_for_maintenance_on_unassigned_ticket(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('open'); // sin asignar

        $available = $this->service()->availableTransitionsFor($ticket, $maintenance);

        $this->assertSame([], $available);
    }

    public function test_available_transitions_empty_for_maintenance_on_ticket_assigned_to_other(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $other = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('open', assignedTo: $other);

        $available = $this->service()->availableTransitionsFor($ticket, $maintenance);

        $this->assertSame([], $available);
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    private function service(): TicketStateService
    {
        return $this->app->make(TicketStateService::class);
    }

    /**
     * Attempts a transition and returns true if it succeeded, false if it threw.
     * Provides a comment for transitions that require it.
     */
    private function tryTransition(string $fromState, string $toState, User $actor, bool $assigned): bool
    {
        $ticket = $assigned
            ? $this->createTicket($fromState, assignedTo: $actor)
            : $this->createTicket($fromState);

        $comment = $this->transitionRequiresComment($fromState, $toState) ? 'Test comment.' : null;

        try {
            $this->service()->transition($ticket, $actor, $toState, $comment);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function transitionRequiresComment(string $fromState, string $toState): bool
    {
        return $toState === 'resolved'
            || $toState === 'rejected'
            || ($fromState === 'resolved' && $toState === 'open')
            || ($fromState === 'rejected' && $toState === 'open');
    }

    private function createTicket(string $state, ?User $assignedTo = null): Ticket
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket test '.Str::random(6),
            'description' => 'Descripción de test.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => $state,
            'priority' => 'medium',
        ]);

        if ($assignedTo !== null) {
            $ticket->forceFill(['assigned_to' => $assignedTo->id])->save();
        }

        return $ticket;
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
            'name' => 'Loc '.Str::upper(Str::random(4)),
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'TST-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-tst-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Cat '.Str::lower(Str::random(6)),
            'icon' => 'bolt',
            'description' => 'Categoría de prueba',
        ]);
    }
}
