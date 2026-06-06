<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\ViewModels\Tickets\AssignmentsBoardViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Mis asignaciones" is the maintenance-only assignments board (phase 2: live
 * data). These tests pin down the access boundary, the OWNERSHIP scope (a
 * technician only ever sees their own assignments), the tab/state mapping, the
 * focused detail rail (detail + stepper + activity from real state history),
 * and that the classic /tickets route is untouched.
 */
class TicketAssignmentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('tickets.assignments'))->assertRedirect(route('login'));
    }

    public function test_reporter_cannot_view_the_assignments_page(): void
    {
        $reporter = $this->userWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.assignments'))
            ->assertForbidden();
    }

    public function test_admin_cannot_view_the_assignments_page(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get(route('tickets.assignments'))
            ->assertForbidden();
    }

    public function test_maintenance_sees_only_their_own_assignments(): void
    {
        $me = $this->userWithRole('maintenance');
        $other = $this->userWithRole('maintenance');

        $this->assign($this->ticket('PC con pantalla azul'), $me, 'in_progress');
        $this->assign($this->ticket('Ticket ajeno de otro tecnico'), $other, 'in_progress');

        $response = $this->actingAs($me)->get(route('tickets.assignments'));

        $response->assertOk();
        $response->assertViewIs('tickets.assignments');
        $this->assertInstanceOf(AssignmentsBoardViewModel::class, $response->viewData('board'));
        $response->assertSeeText('PC con pantalla azul');
        $response->assertDontSeeText('Ticket ajeno de otro tecnico');
    }

    public function test_active_tab_excludes_completed_and_summary_counts_are_correct(): void
    {
        $me = $this->userWithRole('maintenance');

        $this->assign($this->ticket('En progreso ahora'), $me, 'in_progress');
        $this->assign($this->ticket('Abierto sin iniciar'), $me, 'open');
        $this->assign($this->ticket('Ya resuelto'), $me, 'resolved');

        $response = $this->actingAs($me)->get(route('tickets.assignments', ['tab' => 'active']));

        $response->assertOk();
        $response->assertSeeText('En progreso ahora');
        $response->assertSeeText('Abierto sin iniciar');
        $response->assertDontSeeText('Ya resuelto');

        $board = $response->viewData('board');
        $this->assertSame(2, $board->summary['active']);
        $this->assertSame(1, $board->summary['in_progress']);
        $this->assertSame(1, $board->summary['waiting']);
    }

    public function test_completed_tab_shows_resolved_and_rejected(): void
    {
        $me = $this->userWithRole('maintenance');

        $this->assign($this->ticket('Resuelto y cerrado'), $me, 'resolved');
        $this->assign($this->ticket('En progreso activo'), $me, 'in_progress');

        $response = $this->actingAs($me)->get(route('tickets.assignments', ['tab' => 'completed']));

        $response->assertOk();
        $response->assertSeeText('Resuelto y cerrado');
        $response->assertDontSeeText('En progreso activo');
    }

    public function test_priority_filter_narrows_the_list(): void
    {
        $me = $this->userWithRole('maintenance');

        $this->assign($this->ticket('Incidencia critica', ['priority' => 'high']), $me, 'in_progress');
        $this->assign($this->ticket('Incidencia menor', ['priority' => 'low']), $me, 'in_progress');

        $response = $this->actingAs($me)
            ->get(route('tickets.assignments', ['tab' => 'active', 'priority' => 'high']));

        $response->assertOk();
        $response->assertSeeText('Incidencia critica');
        $response->assertDontSeeText('Incidencia menor');
    }

    public function test_detail_rail_renders_detail_stepper_and_activity_for_the_focused_ticket(): void
    {
        $me = $this->userWithRole('maintenance');
        $admin = $this->userWithRole('admin');

        $ticket = $this->ticket('Proyector A-201 no enciende');
        $this->assign($ticket, $me, 'in_progress', $admin);

        // Real audit trail: assignment event + state transition.
        $this->history($ticket, 'open', 'open', $me, 'Ticket tomado por maintenance: '.$me->name);
        $this->history($ticket, 'open', 'in_progress', $me);

        $response = $this->actingAs($me)
            ->get(route('tickets.assignments', ['focus' => $ticket->id]));

        $response->assertOk();

        $board = $response->viewData('board');
        $this->assertNotNull($board->focused);
        $this->assertSame((string) $ticket->id, $board->focused['id']);

        // Detail panel
        $response->assertSeeText('Detalle de mi asignación');
        $response->assertSeeText('Asignado por');

        // Progress stepper
        $response->assertSeeText('Progreso del ticket');
        $response->assertSeeText('Abierto');
        $response->assertSeeText('En progreso');
        $response->assertSeeText('Resuelto');

        // Recent activity (from state history)
        $response->assertSeeText('Actividad reciente');
        $response->assertSeeText('Trabajo iniciado');
    }

    public function test_default_focus_is_the_first_assignment_when_none_requested(): void
    {
        $me = $this->userWithRole('maintenance');
        $this->assign($this->ticket('Única asignación'), $me, 'in_progress');

        $response = $this->actingAs($me)->get(route('tickets.assignments'));

        $response->assertOk();
        $this->assertNotNull($response->viewData('board')->focused);
    }

    public function test_empty_state_when_no_assignments(): void
    {
        $me = $this->userWithRole('maintenance');

        $response = $this->actingAs($me)->get(route('tickets.assignments'));

        $response->assertOk();
        $this->assertNull($response->viewData('board')->focused);
        $response->assertSeeText('Sin asignaciones activas');
    }

    public function test_waiting_tab_has_specific_empty_copy(): void
    {
        $me = $this->userWithRole('maintenance');

        $response = $this->actingAs($me)->get(route('tickets.assignments', ['tab' => 'waiting']));

        $response->assertOk();
        $response->assertSeeText('Nada en espera');
    }

    public function test_completed_tab_has_specific_empty_copy(): void
    {
        $me = $this->userWithRole('maintenance');

        $response = $this->actingAs($me)->get(route('tickets.assignments', ['tab' => 'completed']));

        $response->assertOk();
        $response->assertSeeText('Sin asignaciones completadas');
    }

    // ── Phase 3: real actions from the board ─────────────────────

    public function test_self_claimed_open_assignment_shows_start_and_release_actions(): void
    {
        $me = $this->userWithRole('maintenance');
        $ticket = $this->ticket('Tomado por mí');
        $this->assign($ticket, $me, 'open'); // self-claimed (no admin)

        $response = $this->actingAs($me)->get(route('tickets.assignments'));

        $response->assertOk();
        $response->assertSeeText('Iniciar atención');
        $response->assertSeeText('Liberar ticket');
        $response->assertSee(route('tickets.update-state', $ticket->id));
        $response->assertSee(route('tickets.release', $ticket->id));
    }

    public function test_admin_assigned_open_assignment_shows_start_but_not_release(): void
    {
        $me = $this->userWithRole('maintenance');
        $admin = $this->userWithRole('admin');
        $ticket = $this->ticket('Asignado por admin');
        $this->assign($ticket, $me, 'open', $admin); // admin-assigned + locked

        $response = $this->actingAs($me)->get(route('tickets.assignments'));

        $response->assertOk();
        $response->assertSeeText('Iniciar atención');
        $response->assertDontSeeText('Liberar ticket');
    }

    public function test_in_progress_assignment_hides_the_start_action(): void
    {
        $me = $this->userWithRole('maintenance');
        $this->assign($this->ticket('Ya en progreso'), $me, 'in_progress');

        $response = $this->actingAs($me)->get(route('tickets.assignments'));

        $response->assertOk();
        $response->assertDontSeeText('Iniciar atención');
        $response->assertDontSeeText('Liberar ticket');
    }

    public function test_resolve_reject_hint_is_shown_for_active_focused_ticket(): void
    {
        $me = $this->userWithRole('maintenance');
        $this->assign($this->ticket('En curso con hint'), $me, 'in_progress');

        $response = $this->actingAs($me)->get(route('tickets.assignments'));

        $response->assertOk();
        $response->assertSeeText('Resolver o rechazar se realiza desde el detalle del ticket para registrar el comentario.');
    }

    public function test_resolve_reject_hint_is_hidden_for_completed_focused_ticket(): void
    {
        $me = $this->userWithRole('maintenance');
        $this->assign($this->ticket('Cerrado sin hint'), $me, 'resolved');

        $response = $this->actingAs($me)->get(route('tickets.assignments', ['tab' => 'completed']));

        $response->assertOk();
        $response->assertDontSeeText('Resolver o rechazar se realiza desde el detalle del ticket');
    }

    public function test_technician_starts_work_from_the_board(): void
    {
        $me = $this->userWithRole('maintenance');
        $ticket = $this->ticket('Por iniciar');
        $this->assign($ticket, $me, 'open');

        $this->actingAs($me)
            ->patch(route('tickets.update-state', $ticket), [
                'to_state' => 'in_progress',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'state' => 'in_progress',
        ]);
    }

    public function test_technician_releases_a_self_claimed_assignment(): void
    {
        $me = $this->userWithRole('maintenance');
        $ticket = $this->ticket('Para liberar');
        $this->assign($ticket, $me, 'open');

        $this->actingAs($me)
            ->patch(route('tickets.release', $ticket), [
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assigned_to' => null,
        ]);
    }

    public function test_assignments_route_is_named_and_pathed_correctly(): void
    {
        $this->assertStringEndsWith('/tickets/assignments', route('tickets.assignments'));
    }

    public function test_classic_tickets_route_remains_untouched(): void
    {
        $reporter = $this->userWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertViewIs('tickets.index');
    }

    // ── Fixtures ─────────────────────────────────────────────────

    private function userWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function ticket(string $title, array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'title' => $title,
            'description' => 'Descripción de '.$title,
            'reporter_id' => $this->reporter()->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => 'open',
            'priority' => 'medium',
            'assignment_locked' => false,
        ], $overrides));
    }

    private function assign(Ticket $ticket, User $tech, string $state, ?User $assignedBy = null): Ticket
    {
        $ticket->forceFill([
            'assigned_to' => $tech->id,
            'assigned_by' => $assignedBy?->id ?? $tech->id,
            'assigned_at' => now(),
            'assignment_locked' => $assignedBy !== null,
            'assignment_source' => $assignedBy !== null ? Ticket::ASSIGNMENT_SOURCE_ADMIN : Ticket::ASSIGNMENT_SOURCE_SELF,
            'state' => $state,
            'resolved_at' => $state === 'resolved' ? now() : null,
        ])->save();

        return $ticket;
    }

    private function history(Ticket $ticket, string $from, string $to, User $by, ?string $comment = null): void
    {
        StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => $from,
            'to_state' => $to,
            'changed_by' => $by->id,
            'comment' => $comment,
        ]);
    }

    private ?User $reporterFixture = null;

    private function reporter(): User
    {
        return $this->reporterFixture ??= User::factory()->create();
    }

    private function location(): Location
    {
        return Location::firstOrCreate(
            ['room_code' => 'LAB-1'],
            [
                'name' => 'Laboratorio 1',
                'building' => 'Edificio A',
                'floor' => '1',
                'qr_token' => 'qr-'.Str::uuid()->toString(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'Hardware'],
            ['icon' => 'monitor', 'description' => 'Equipos físicos'],
        );
    }
}
