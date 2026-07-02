<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * History timeline refactor — /tickets/{ticket} state history section.
 *
 * Verifies that the history section no longer renders a <table> and instead
 * shows a vertical timeline, while preserving all data: actor, action type,
 * from/to state transitions, comments, and chronological order.
 */
class TicketShowHistoryTimelineTest extends TestCase
{
    use RefreshDatabase;

    // ── Section presence ──────────────────────────────────────────────────────

    public function test_ticket_show_renders_history_section(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $html = $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Historial de estados', (string) $html);
    }

    // ── No legacy table headers ───────────────────────────────────────────────

    public function test_no_table_column_headers_in_history_section(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open', 'Ticket creado');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<th scope="col">Estado anterior</th>', $html);
        $this->assertStringNotContainsString('<th scope="col">Estado nuevo</th>', $html);
        $this->assertStringNotContainsString('<th scope="col">Cambiado por</th>', $html);
        $this->assertStringNotContainsString('<th scope="col">Tipo de acción</th>', $html);
        $this->assertStringNotContainsString('<th scope="col">Comentario</th>', $html);
        $this->assertStringNotContainsString('<th scope="col">Fecha</th>', $html);
    }

    public function test_no_table_element_in_history_section(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        // The whole page should not contain a <table> inside the history section
        // (we verify by ensuring there are no table column headers from history)
        $this->assertStringNotContainsString('ticket-show__table', $html);
        $this->assertStringNotContainsString('ticket-show__table-wrap', $html);
    }

    // ── Timeline markup ───────────────────────────────────────────────────────

    public function test_history_renders_timeline_list(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ticket-show__history-timeline', $html);
        $this->assertStringContainsString('ticket-show__history-event', $html);
    }

    // ── Actor / author ────────────────────────────────────────────────────────

    public function test_history_event_shows_actor_name(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(e($reporter->name), $html);
        $this->assertStringContainsString('ticket-show__history-author', $html);
    }

    public function test_history_event_shows_initials_in_dot(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ticket-show__history-dot', $html);
        $this->assertStringContainsString('ticket-show__history-marker', $html);
    }

    // ── Action type badge ─────────────────────────────────────────────────────

    public function test_creation_event_shows_creacion_badge(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Creación', $html);
        $this->assertStringContainsString('ticket-show__history-action-badge', $html);
        $this->assertStringContainsString('ticket-show__history-action-badge--create', $html);
    }

    public function test_in_progress_event_shows_inicio_atencion_badge(): void
    {
        $maintenance = $this->userWithRole('maintenance');
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');
        $this->historyFor($ticket, $maintenance, 'open', 'in_progress');

        $admin = $this->userWithRole('admin');

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Inicio de atención', $html);
        $this->assertStringContainsString('ticket-show__history-action-badge--start', $html);
    }

    public function test_resolved_event_shows_resolucion_badge(): void
    {
        $maintenance = $this->userWithRole('maintenance');
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');
        $this->historyFor($ticket, $maintenance, 'in_progress', 'resolved', 'Resuelto correctamente');

        $admin = $this->userWithRole('admin');

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Resolución', $html);
        $this->assertStringContainsString('ticket-show__history-action-badge--resolve', $html);
    }

    // ── State transition pills ────────────────────────────────────────────────

    public function test_creation_event_shows_sin_estado_to_abierto(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sin estado', $html);
        $this->assertStringContainsString('Abierto', $html);
        $this->assertStringContainsString('ticket-show__history-transition', $html);
        $this->assertStringContainsString('ticket-show__history-arrow', $html);
    }

    public function test_in_progress_transition_shows_abierto_to_en_progreso(): void
    {
        $maintenance = $this->userWithRole('maintenance');
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');
        $this->historyFor($ticket, $maintenance, 'open', 'in_progress');

        $admin = $this->userWithRole('admin');

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Abierto', $html);
        $this->assertStringContainsString('En progreso', $html);
    }

    public function test_resolved_transition_shows_en_progreso_to_resuelto(): void
    {
        $maintenance = $this->userWithRole('maintenance');
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');
        $this->historyFor($ticket, $maintenance, 'open', 'in_progress');
        $this->historyFor($ticket, $maintenance, 'in_progress', 'resolved');

        $admin = $this->userWithRole('admin');

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('En progreso', $html);
        $this->assertStringContainsString('Resuelto', $html);
    }

    public function test_state_pills_use_ts_badge_classes(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ts-badge--open', $html);
        $this->assertStringContainsString('ts-badge--neutral', $html);
    }

    // ── Comment rendering ─────────────────────────────────────────────────────

    public function test_comment_renders_inside_timeline_event(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open', 'Ticket creado correctamente desde test.');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ticket creado correctamente desde test.', $html);
        $this->assertStringContainsString('ticket-show__history-comment', $html);
    }

    public function test_null_comment_renders_sin_comentario(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open', null);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sin comentario', $html);
        $this->assertStringContainsString('ticket-show__history-empty-comment', $html);
    }

    // ── Date / time ───────────────────────────────────────────────────────────

    public function test_history_event_shows_formatted_date(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        // LocalTime::format renders dd/mm/YYYY HH:ii (Lima timezone)
        $this->assertMatchesRegularExpression('/\d{2}\/\d{2}\/\d{4}/', $html);
        $this->assertStringContainsString('ticket-show__history-time', $html);
    }

    // ── Empty state ───────────────────────────────────────────────────────────

    public function test_empty_state_renders_when_no_history(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sin cambios registrados.', $html);
        $this->assertStringNotContainsString('ticket-show__history-timeline', $html);
    }

    // ── CSS classes ───────────────────────────────────────────────────────────

    public function test_timeline_css_classes_present(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ticket-show__history-timeline', $html);
        $this->assertStringContainsString('ticket-show__history-event', $html);
        $this->assertStringContainsString('ticket-show__history-content', $html);
        $this->assertStringContainsString('ticket-show__history-transition', $html);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function openTicketFor(User $reporter): Ticket
    {
        return Ticket::create([
            'title' => 'Ticket historial '.Str::uuid(),
            'description' => 'Descripción de prueba suficientemente larga para pasar validación.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function historyFor(
        Ticket $ticket,
        User $actor,
        ?string $from,
        string $to,
        ?string $comment = null,
    ): StateHistory {
        return StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => $from,
            'to_state' => $to,
            'changed_by' => $actor->id,
            'comment' => $comment,
        ]);
    }

    private function location(): Location
    {
        return Location::firstOrCreate(
            ['room_code' => 'HISTTL-01'],
            [
                'name' => 'Laboratorio Timeline',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-history-timeline-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'HistoryTimelineTest'],
            ['icon' => 'clock', 'description' => 'Categoría para tests de history timeline'],
        );
    }
}
