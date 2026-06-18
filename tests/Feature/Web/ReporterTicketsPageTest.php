<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketEmbedding;
use App\Models\User;
use App\ViewModels\Tickets\ReporterTicketsBoardViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Mis tickets" is the reporter-only board — LIVE DATA (phase 2).
 *
 * The board only ever shows the authenticated reporter's OWN tickets
 * (reporter_id = them). These tests pin down the access boundary, the
 * ownership/no-leak scope across every entry point (list, search, status chip,
 * location filter, duplicates, donut/labs), real chips/pagination, the honest
 * average-response metric, that the row CTA targets the real guarded detail
 * (tickets.show) with NO maintenance action, and that /tickets stays intact.
 */
class ReporterTicketsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reporter.tickets.index'))->assertRedirect(route('login'));
    }

    public function test_route_is_named_and_pathed_correctly(): void
    {
        $this->assertStringEndsWith('/reporter/tickets', route('reporter.tickets.index'));
    }

    public function test_reporter_can_view_the_board(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Mi incidencia visible');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertViewIs('tickets.reporter.index');
        $this->assertInstanceOf(ReporterTicketsBoardViewModel::class, $response->viewData('board'));
        $response->assertSeeText('Mis tickets');
        $response->assertSeeText('Mi incidencia visible');
    }

    public function test_maintenance_admin_super_admin_are_forbidden(): void
    {
        foreach (['maintenance', 'admin', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('reporter.tickets.index'))
                ->assertForbidden();
        }
    }

    public function test_board_shows_only_my_tickets(): void
    {
        $me = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');

        $this->ticketFor($me, 'open', 'Ticket propio del reporter');
        $this->ticketFor($other, 'open', 'Ticket de otro reporter');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertSeeText('Ticket propio del reporter');
        $response->assertDontSeeText('Ticket de otro reporter');
    }

    public function test_chips_show_real_counts_of_my_tickets(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'A');
        $this->ticketFor($me, 'open', 'B');
        $this->ticketFor($me, 'in_progress', 'C');
        $this->ticketFor($me, 'resolved', 'D');

        // Another reporter's tickets must never inflate my chips.
        $this->ticketFor($this->userWithRole('reporter'), 'open', 'Ajeno');

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');
        $chips = collect($board->chips)->keyBy('key');

        $this->assertSame(4, $chips['all']['count']);
        $this->assertSame(2, $chips['open']['count']);
        $this->assertSame(1, $chips['in_progress']['count']);
        $this->assertSame(1, $chips['resolved']['count']);
        $this->assertSame(0, $chips['rejected']['count']);
    }

    public function test_status_chip_filters_the_list(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Abierto mío');
        $this->ticketFor($me, 'resolved', 'Resuelto mío');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index', ['status' => 'resolved']));

        $response->assertOk();
        $response->assertSeeText('Resuelto mío');
        $response->assertDontSeeText('Abierto mío');
    }

    public function test_search_does_not_leak_other_reporters_tickets(): void
    {
        $me = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');

        $this->ticketFor($me, 'open', 'Incidencia buscada propia');
        $this->ticketFor($other, 'open', 'Incidencia buscada ajena');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index', ['search' => 'buscada']));

        $response->assertOk();
        $response->assertSeeText('Incidencia buscada propia');
        $response->assertDontSeeText('Incidencia buscada ajena');
    }

    public function test_location_filter_does_not_leak(): void
    {
        $me = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');
        $lab = $this->location('LAB-7', 'Laboratorio 7');

        $this->ticketFor($me, 'open', 'En lab 7 propio', ['location_id' => $lab->id]);
        $this->ticketFor($other, 'open', 'En lab 7 ajeno', ['location_id' => $lab->id]);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index', ['location_id' => $lab->id]));

        $response->assertOk();
        $response->assertSeeText('En lab 7 propio');
        $response->assertDontSeeText('En lab 7 ajeno');
    }

    public function test_duplicate_chip_counts_and_scopes_to_my_tickets(): void
    {
        $me = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');

        $mine = $this->ticketFor($me, 'open', 'Posible duplicado propio');
        $this->markDuplicate($mine);

        $foreign = $this->ticketFor($other, 'open', 'Posible duplicado ajeno');
        $this->markDuplicate($foreign);

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');
        $this->assertSame(1, collect($board->chips)->firstWhere('key', 'duplicate')['count']);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index', ['status' => 'duplicate']));
        $response->assertSeeText('Posible duplicado propio');
        $response->assertDontSeeText('Posible duplicado ajeno');
    }

    public function test_duplicate_badge_appears_in_card_when_ticket_is_flagged(): void
    {
        $me = $this->userWithRole('reporter');
        $mine = $this->ticketFor($me, 'open', 'Mi ticket posible duplicado');
        $this->markDuplicate($mine);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertSeeText('Posible duplicado');
        $this->assertTrue($response->viewData('board')->tickets[0]['is_duplicate']);
    }

    public function test_duplicate_badge_not_shown_for_non_duplicate_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Ticket normal sin señal IA');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $this->assertFalse($response->viewData('board')->tickets[0]['is_duplicate']);
    }

    public function test_duplicate_badge_not_shown_for_dismissed_duplicate(): void
    {
        $me = $this->userWithRole('reporter');
        $mine = $this->ticketFor($me, 'open', 'Duplicado humano descartado');
        TicketEmbedding::create([
            'ticket_id' => $mine->id,
            'embedding_vector' => [0.1, 0.2, 0.3],
            'description_hash' => Str::uuid()->toString(),
            'is_duplicate' => true,
            'review_status' => TicketEmbedding::REVIEW_DISMISSED,
        ]);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $this->assertFalse($response->viewData('board')->tickets[0]['is_duplicate']);
    }

    public function test_pagination_caps_at_five_and_counts_my_tickets(): void
    {
        $me = $this->userWithRole('reporter');
        for ($i = 1; $i <= 7; $i++) {
            $this->ticketFor($me, 'open', "Ticket {$i}");
        }

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertSeeText('Mostrando 1 a 5 de 7 tickets');
        $this->assertSame(7, $response->viewData('board')->pagination['total']);
        $this->assertCount(5, $response->viewData('board')->tickets);
    }

    public function test_donut_uses_only_my_ticket_counts(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'O1');
        $this->ticketFor($me, 'resolved', 'R1');
        $this->ticketFor($this->userWithRole('reporter'), 'open', 'Ajeno');

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');
        $segments = collect($board->summary['donut'])->keyBy('key');

        $this->assertSame(2, $board->summary['total']);
        $this->assertSame(1, $segments['open']['count']);
        $this->assertSame(1, $segments['resolved']['count']);
        $this->assertSame(0, $segments['in_progress']['count']);
    }

    public function test_average_response_is_honest_placeholder_without_history(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Sin atención aún');

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');

        $this->assertFalse($board->summary['avg_has_data']);
        $this->assertSame('Sin datos', $board->summary['avg_value']);
    }

    public function test_average_response_computes_from_first_in_progress_transition(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'in_progress', 'Atendido', [
            'created_at' => Carbon::parse('2026-06-01 09:00:00'),
        ]);
        $this->transition($ticket, 'open', 'in_progress', Carbon::parse('2026-06-01 11:30:00'));

        $board = $this->actingAs($me)->get(route('reporter.tickets.index'))->viewData('board');

        $this->assertTrue($board->summary['avg_has_data']);
        $this->assertSame('2h 30m', $board->summary['avg_value']);
    }

    public function test_row_cta_links_to_the_reporter_tracking_screen(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Con detalle');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertSeeText('Ver seguimiento');
        $response->assertSee(route('reporter.tickets.show', $ticket->id), false);
    }

    public function test_empty_state_when_no_tickets(): void
    {
        $me = $this->userWithRole('reporter');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('board')->totalReported);
        $response->assertSeeText('Aún no has reportado incidencias');
    }

    public function test_filtered_empty_state_offers_clear_filters(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Existe pero no coincide');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index', ['search' => 'zzz-no-match']));

        $response->assertOk();
        $response->assertSeeText('Sin resultados');
        $response->assertSeeText('Limpiar filtros');
    }

    public function test_does_not_expose_maintenance_actions(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Mi ticket');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertDontSeeText('Tomar');
        $response->assertDontSeeText('Liberar');
        $response->assertDontSeeText('Resolver ticket');
    }

    // ── Row actions kebab: Editar / Cancelar solicitud gating ────

    public function test_kebab_shows_edit_and_cancel_for_own_open_unassigned_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Abierto editable');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();

        $row = $response->viewData('board')->tickets[0];
        $this->assertTrue($row['can_edit']);
        $this->assertTrue($row['can_cancel']);
        $this->assertTrue($row['show_actions_menu']);

        $response->assertSee('Más acciones para');
        $response->assertSeeText('Editar');
        $response->assertSeeText('Cancelar solicitud');
        // "Editar" is REAL now: it links to the reporter edit route.
        $response->assertSee(route('reporter.tickets.edit', $ticket->id), false);
    }

    public function test_cancel_is_a_real_patch_form_for_cancellable_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Abierto cancelable');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        // Cancelar is REAL now: a PATCH form to the cancel route, no placeholder.
        $response->assertSee(route('reporter.tickets.cancel', $ticket->id), false);
        $response->assertSee('value="PATCH"', false);
        $response->assertSeeText('Cancelar solicitud');
        $response->assertDontSee('Cancelar solicitud estará disponible en la siguiente fase', false);
        // Still no destructive delete anywhere.
        $response->assertDontSee('value="DELETE"', false);
        $response->assertDontSeeText('Eliminar');
    }

    public function test_cancelled_ticket_has_no_kebab_and_uses_ver_detalle(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'cancelled', 'Cancelado mío');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $row = $response->viewData('board')->tickets[0];
        $this->assertSame('cancelled', $row['status']);
        $this->assertSame('Cancelado', $row['status_label']);
        $this->assertFalse($row['show_actions_menu']);
        $this->assertSame('Ver detalle', $row['action_label']);
        $response->assertDontSee('Más acciones para', false);
    }

    public function test_cancelados_chip_counts_only_my_cancelled_tickets(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'cancelled', 'C1');
        $this->ticketFor($me, 'open', 'O1');
        $this->ticketFor($this->userWithRole('reporter'), 'cancelled', 'Ajeno cancelado');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));
        $chips = collect($response->viewData('board')->chips)->keyBy('key');

        $response->assertOk();
        $response->assertSeeText('Cancelados');
        $this->assertSame(1, $chips['cancelled']['count']);
    }

    public function test_no_kebab_for_own_in_progress_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'in_progress', 'En progreso mío');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $row = $response->viewData('board')->tickets[0];
        $this->assertFalse($row['can_edit']);
        $this->assertFalse($row['can_cancel']);
        $this->assertFalse($row['show_actions_menu']);
        $response->assertDontSeeText('Cancelar solicitud');
        $response->assertDontSee('Más acciones para', false);
    }

    public function test_no_kebab_for_own_resolved_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'resolved', 'Resuelto mío');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $this->assertFalse($response->viewData('board')->tickets[0]['show_actions_menu']);
        $response->assertDontSeeText('Cancelar solicitud');
    }

    public function test_no_kebab_for_own_rejected_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'rejected', 'Rechazado mío');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $this->assertFalse($response->viewData('board')->tickets[0]['show_actions_menu']);
        $response->assertDontSeeText('Cancelar solicitud');
    }

    public function test_no_kebab_for_own_open_ticket_assigned_to_maintenance(): void
    {
        $me = $this->userWithRole('reporter');
        $tech = $this->userWithRole('maintenance');
        $this->ticketFor($me, 'open', 'Abierto pero tomado', ['assigned_to' => $tech->id]);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $row = $response->viewData('board')->tickets[0];
        $this->assertFalse($row['can_edit']);
        $this->assertFalse($row['can_cancel']);
        $this->assertFalse($row['show_actions_menu']);
        $response->assertDontSeeText('Cancelar solicitud');
    }

    public function test_no_kebab_for_own_open_locked_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Abierto bloqueado', ['assignment_locked' => true]);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $this->assertFalse($response->viewData('board')->tickets[0]['show_actions_menu']);
        $response->assertDontSeeText('Cancelar solicitud');
    }

    public function test_kebab_no_longer_renders_ver_cronologia(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Abierto sin cronología');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertDontSeeText('Ver cronología');
    }

    public function test_ver_seguimiento_stays_as_main_cta_and_is_not_repeated_in_kebab(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Abierto único CTA');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertSeeText('Ver seguimiento');
        // Exactly once: the main blue CTA only. The kebab no longer repeats it.
        $this->assertSame(1, substr_count($response->getContent(), 'Ver seguimiento'));
    }

    public function test_board_never_renders_eliminar_or_destructive_delete(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Abierto sin eliminar');

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertDontSeeText('Eliminar');
        // No method-spoofed DELETE form anywhere on the reporter board.
        $response->assertDontSee('value="DELETE"', false);
    }

    public function test_classic_tickets_route_remains_intact(): void
    {
        $reporter = $this->userWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.index'))
            ->assertRedirect(route('reporter.tickets.index'));
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
     * @param  array<string, mixed>  $attrs  location_id, category_id, priority, created_at, assigned_to, assignment_locked
     */
    private function ticketFor(User $reporter, string $state, string $title, array $attrs = []): Ticket
    {
        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripción de '.$title,
            'reporter_id' => $reporter->id,
            'location_id' => $attrs['location_id'] ?? $this->location()->id,
            'category_id' => $attrs['category_id'] ?? $this->category()->id,
            'state' => $state,
            'priority' => $attrs['priority'] ?? 'medium',
            'assignment_locked' => $attrs['assignment_locked'] ?? false,
        ]);

        // assigned_to is not mass-assignable (no HTTP vector); set it directly
        // when a test needs a ticket already taken by maintenance.
        if (! empty($attrs['assigned_to'])) {
            $ticket->forceFill(['assigned_to' => $attrs['assigned_to']])->save();
        }

        if (! empty($attrs['created_at'])) {
            $ticket->forceFill(['created_at' => $attrs['created_at']])->save();
        }

        return $ticket;
    }

    private function markDuplicate(Ticket $ticket): void
    {
        TicketEmbedding::create([
            'ticket_id' => $ticket->id,
            'embedding_vector' => [0.1, 0.2, 0.3],
            'description_hash' => Str::uuid()->toString(),
            'is_duplicate' => true,
        ]);
    }

    private function transition(Ticket $ticket, string $from, string $to, Carbon $at): void
    {
        $history = StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => $from,
            'to_state' => $to,
            'changed_by' => $ticket->reporter_id,
            'comment' => null,
        ]);

        $history->forceFill(['created_at' => $at])->save();
    }

    private function location(string $code = 'LAB-1', string $name = 'Laboratorio 1'): Location
    {
        return Location::firstOrCreate(
            ['room_code' => $code],
            [
                'name' => $name,
                'building' => 'Edificio A',
                'floor' => '1',
                'qr_token' => 'qr-'.Str::uuid()->toString(),
                'is_active' => true,
            ],
        );
    }

    private function category(string $name = 'Hardware'): Category
    {
        return Category::firstOrCreate(
            ['name' => $name],
            ['icon' => 'monitor', 'description' => 'Categoría '.$name],
        );
    }
}
