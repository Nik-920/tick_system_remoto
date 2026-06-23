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
 * Closing-info removal — /tickets/{ticket} show view.
 *
 * Verifies that the redundant "Información final / cierre" section has been
 * removed and that the history timeline continues to surface resolution data.
 *
 * @category ClosingInfoRemovalTest
 */
class TicketShowClosingInfoRemovalTest extends TestCase
{
    use RefreshDatabase;

    // ── Section absence ───────────────────────────────────────────────────────

    public function test_ticket_show_does_not_render_informacion_final_section(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Información final / cierre', $html);
        $this->assertStringNotContainsString('closure-heading', $html);
    }

    public function test_ticket_show_does_not_render_estado_final_label(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->resolvedTicketFor($reporter);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Estado final', $html);
    }

    public function test_ticket_show_does_not_render_fecha_de_cierre_label(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->resolvedTicketFor($reporter);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Fecha de cierre', $html);
    }

    public function test_ticket_show_does_not_render_comentario_de_cierre_label(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->resolvedTicketFor($reporter);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Comentario de cierre', $html);
    }

    public function test_no_closure_css_classes_in_html(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->resolvedTicketFor($reporter);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('ticket-show__closure-comment', $html);
    }

    // ── Timeline preserves resolution data ───────────────────────────────────

    public function test_resolved_ticket_shows_resolution_badge_in_timeline(): void
    {
        $maintenance = $this->userWithRole('maintenance');
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');
        $this->historyFor($ticket, $maintenance, 'in_progress', 'resolved', 'TICKET RESUELTO');

        $admin = $this->userWithRole('admin');

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Resolución', $html);
        $this->assertStringContainsString('ticket-show__history-action-badge--resolve', $html);
    }

    public function test_resolved_ticket_shows_resuelto_transition_in_timeline(): void
    {
        $maintenance = $this->userWithRole('maintenance');
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');
        $this->historyFor($ticket, $maintenance, 'in_progress', 'resolved');

        $admin = $this->userWithRole('admin');

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Resuelto', $html);
        $this->assertStringContainsString('ts-badge--resolved', $html);
    }

    public function test_resolved_ticket_shows_closure_comment_in_timeline(): void
    {
        $maintenance = $this->userWithRole('maintenance');
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');
        $this->historyFor($ticket, $maintenance, 'in_progress', 'resolved', 'TICKET RESUELTO');

        $admin = $this->userWithRole('admin');

        $html = (string) $this->actingAs($admin)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('TICKET RESUELTO', $html);
        $this->assertStringContainsString('ticket-show__history-comment', $html);
    }

    // ── Back link intact ──────────────────────────────────────────────────────

    public function test_volver_link_still_present_after_section_removal(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Volver', $html);
        $this->assertStringContainsString('ticket-show__back-link', $html);
    }

    // ── No orphan include ─────────────────────────────────────────────────────

    public function test_closing_info_partial_is_not_included(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->assertDontSee('Información final / cierre');
    }

    // ── Adjacent sections intact ──────────────────────────────────────────────

    public function test_history_section_still_renders(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $this->historyFor($ticket, $reporter, null, 'open');

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Historial de estados', $html);
        $this->assertStringContainsString('ticket-show__history-timeline', $html);
    }

    public function test_evidence_section_still_renders(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        $html = (string) $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Evidencias', $html);
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
            'title' => 'Ticket cierre '.Str::uuid(),
            'description' => 'Descripción de prueba suficientemente larga para pasar validación.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function resolvedTicketFor(User $reporter): Ticket
    {
        $ticket = $this->openTicketFor($reporter);
        $ticket->update(['state' => 'resolved']);

        return $ticket;
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
            ['room_code' => 'CLSRM-01'],
            [
                'name' => 'Laboratorio Cierre',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-closing-removal-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'ClosingInfoRemovalTest'],
            ['icon' => 'trash', 'description' => 'Categoría para tests de closing-info removal'],
        );
    }
}
