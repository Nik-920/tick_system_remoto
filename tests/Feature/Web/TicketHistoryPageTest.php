<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\ViewModels\Tickets\HistoryBoardViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Historial" is the maintenance-only history board (phase 2: live data).
 *
 * History = the technician's OWN closed-out tickets (assigned_to = them,
 * state in resolved/rejected). These tests pin down the access boundary, the
 * ownership/no-leak scope, the real filters, the real analytics (counts,
 * donut percentages, minute-accurate average, top labs, activity) and that
 * the classic /tickets and /tickets/assignments routes stay intact. Export
 * remains a visual placeholder.
 */
class TicketHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('tickets.history'))->assertRedirect(route('login'));
    }

    public function test_maintenance_can_view_the_history_page(): void
    {
        $me = $this->userWithRole('maintenance');
        $this->ticketFor($me, 'resolved', 'Ticket resuelto visible');

        $response = $this->actingAs($me)->get(route('tickets.history'));

        $response->assertOk();
        $response->assertViewIs('tickets.history');
        $this->assertInstanceOf(HistoryBoardViewModel::class, $response->viewData('board'));
        $response->assertSeeText('Historial de tickets');
        $response->assertSeeText('Ticket resuelto visible');
    }

    public function test_reporter_cannot_view_the_history_page(): void
    {
        $reporter = $this->userWithRole('reporter');

        $this->actingAs($reporter)->get(route('tickets.history'))->assertForbidden();
    }

    public function test_admin_cannot_view_the_history_page(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('tickets.history'))->assertForbidden();
    }

    public function test_history_only_shows_resolved_or_rejected_assigned_to_me(): void
    {
        $me = $this->userWithRole('maintenance');

        $this->ticketFor($me, 'resolved', 'Resuelto mío');
        $this->ticketFor($me, 'rejected', 'Rechazado mío');
        $this->ticketFor($me, 'in_progress', 'En progreso mío');

        $response = $this->actingAs($me)->get(route('tickets.history'));

        $response->assertOk();
        $response->assertSeeText('Resuelto mío');
        $response->assertSeeText('Rechazado mío');
        $response->assertDontSeeText('En progreso mío');
    }

    public function test_history_does_not_show_another_maintenance_ticket(): void
    {
        $me = $this->userWithRole('maintenance');
        $other = $this->userWithRole('maintenance');

        $this->ticketFor($me, 'resolved', 'Resuelto mío');
        $this->ticketFor($other, 'resolved', 'Resuelto ajeno de otro tecnico');

        $response = $this->actingAs($me)->get(route('tickets.history'));

        $response->assertOk();
        $response->assertSeeText('Resuelto mío');
        $response->assertDontSeeText('Resuelto ajeno de otro tecnico');
    }

    public function test_search_filter_does_not_leak_other_maintenance_tickets(): void
    {
        $me = $this->userWithRole('maintenance');
        $other = $this->userWithRole('maintenance');

        $this->ticketFor($me, 'resolved', 'Incidencia buscada propia');
        $this->ticketFor($other, 'resolved', 'Incidencia buscada ajena');

        $response = $this->actingAs($me)->get(route('tickets.history', ['search' => 'buscada']));

        $response->assertOk();
        $response->assertSeeText('Incidencia buscada propia');
        $response->assertDontSeeText('Incidencia buscada ajena');
    }

    public function test_location_filter_does_not_leak(): void
    {
        $me = $this->userWithRole('maintenance');
        $other = $this->userWithRole('maintenance');
        $lab = $this->location('LAB-7', 'Laboratorio 7');

        $this->ticketFor($me, 'resolved', 'En lab 7 propio', ['location_id' => $lab->id]);
        $this->ticketFor($other, 'resolved', 'En lab 7 ajeno', ['location_id' => $lab->id]);

        $response = $this->actingAs($me)->get(route('tickets.history', ['location_id' => $lab->id]));

        $response->assertOk();
        $response->assertSeeText('En lab 7 propio');
        $response->assertDontSeeText('En lab 7 ajeno');
    }

    public function test_category_filter_does_not_leak(): void
    {
        $me = $this->userWithRole('maintenance');
        $other = $this->userWithRole('maintenance');
        $cat = $this->category('Redes');

        $this->ticketFor($me, 'resolved', 'Categoria redes propio', ['category_id' => $cat->id]);
        $this->ticketFor($other, 'resolved', 'Categoria redes ajeno', ['category_id' => $cat->id]);

        $response = $this->actingAs($me)->get(route('tickets.history', ['category_id' => $cat->id]));

        $response->assertOk();
        $response->assertSeeText('Categoria redes propio');
        $response->assertDontSeeText('Categoria redes ajeno');
    }

    public function test_date_range_filter_uses_resolved_at_for_resolved_tickets(): void
    {
        $me = $this->userWithRole('maintenance');

        $this->ticketFor($me, 'resolved', 'Dentro del rango', ['resolved_at' => Carbon::parse('2026-05-25 10:00:00')]);
        $this->ticketFor($me, 'resolved', 'Fuera del rango', ['resolved_at' => Carbon::parse('2026-05-10 10:00:00')]);

        $response = $this->actingAs($me)
            ->get(route('tickets.history', ['from' => '2026-05-20', 'to' => '2026-05-31']));

        $response->assertOk();
        $response->assertSeeText('Dentro del rango');
        $response->assertDontSeeText('Fuera del rango');
    }

    public function test_summary_cards_show_real_counts(): void
    {
        $me = $this->userWithRole('maintenance');

        $this->ticketFor($me, 'resolved', 'R1');
        $this->ticketFor($me, 'resolved', 'R2');
        $this->ticketFor($me, 'rejected', 'X1');

        $board = $this->actingAs($me)->get(route('tickets.history'))->viewData('board');

        $this->assertSame(3, $board->summary['total']);
        $this->assertSame(2, $board->summary['resolved']);
        $this->assertSame(1, $board->summary['rejected']);
    }

    public function test_donut_shows_real_percentages(): void
    {
        $me = $this->userWithRole('maintenance');

        $this->ticketFor($me, 'resolved', 'R1');
        $this->ticketFor($me, 'resolved', 'R2');
        $this->ticketFor($me, 'resolved', 'R3');
        $this->ticketFor($me, 'rejected', 'X1');

        $board = $this->actingAs($me)->get(route('tickets.history'))->viewData('board');

        $segments = collect($board->donut['segments'])->keyBy('key');
        $this->assertSame(4, $board->donut['total']);
        $this->assertSame(75.0, $segments['resolved']['percent']);
        $this->assertSame(25.0, $segments['rejected']['percent']);
    }

    public function test_average_resolution_time_uses_minutes_not_truncated_hours(): void
    {
        $me = $this->userWithRole('maintenance');

        // 2h 30m (150 min) — a truncating implementation would wrongly show "2h".
        $this->ticketFor($me, 'resolved', 'Resuelto en 150 min', [
            'created_at' => Carbon::parse('2026-05-25 09:00:00'),
            'resolved_at' => Carbon::parse('2026-05-25 11:30:00'),
        ]);

        $board = $this->actingAs($me)->get(route('tickets.history'))->viewData('board');

        $this->assertTrue($board->summary['avg_has_data']);
        $this->assertSame('2h 30m', $board->summary['avg_label']);
    }

    public function test_top_labs_only_includes_own_resolved_tickets(): void
    {
        $me = $this->userWithRole('maintenance');
        $other = $this->userWithRole('maintenance');
        $lab1 = $this->location('LAB-1', 'Laboratorio 1');
        $lab2 = $this->location('LAB-2', 'Laboratorio 2');

        $this->ticketFor($me, 'resolved', 'Mío A', ['location_id' => $lab1->id]);
        $this->ticketFor($me, 'resolved', 'Mío B', ['location_id' => $lab1->id]);
        $this->ticketFor($other, 'resolved', 'Ajeno', ['location_id' => $lab2->id]);

        $board = $this->actingAs($me)->get(route('tickets.history'))->viewData('board');

        $names = collect($board->topLabs['items'])->pluck('count', 'name');
        $this->assertSame(2, $names->get('Laboratorio 1'));
        $this->assertNull($names->get('Laboratorio 2'));
    }

    public function test_activity_timeline_uses_state_history_of_own_tickets(): void
    {
        $me = $this->userWithRole('maintenance');
        $other = $this->userWithRole('maintenance');

        $mine = $this->ticketFor($me, 'resolved', 'Cierre propio');
        $this->history($mine, 'in_progress', 'resolved', $me);

        $foreign = $this->ticketFor($other, 'resolved', 'Cierre ajeno');
        $this->history($foreign, 'in_progress', 'resolved', $other);

        $board = $this->actingAs($me)->get(route('tickets.history'))->viewData('board');

        $ids = collect($board->activity)->pluck('id');
        $this->assertTrue($ids->contains((string) $mine->id));
        $this->assertFalse($ids->contains((string) $foreign->id));
    }

    public function test_empty_state_renders_when_no_history(): void
    {
        $me = $this->userWithRole('maintenance');

        $response = $this->actingAs($me)->get(route('tickets.history'));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('board')->summary['total']);
        $response->assertSeeText('Sin tickets en el historial');
    }

    public function test_export_button_removed(): void
    {
        $me = $this->userWithRole('maintenance');

        $response = $this->actingAs($me)->get(route('tickets.history'));

        $response->assertOk();
        $response->assertDontSee('Exportar ticket');
        $response->assertDontSee('Próximamente');
        $response->assertDontSee('tickets.history.export');
    }

    public function test_history_shows_honest_scope_hint(): void
    {
        $me = $this->userWithRole('maintenance');

        $response = $this->actingAs($me)->get(route('tickets.history'));

        $response->assertOk();
        $response->assertSeeText('Este historial muestra tickets resueltos o rechazados que estuvieron asignados a ti.');
    }

    public function test_resolved_chip_empty_state_has_specific_copy(): void
    {
        $me = $this->userWithRole('maintenance');
        $this->ticketFor($me, 'rejected', 'Solo rechazado'); // no resolved tickets

        $response = $this->actingAs($me)->get(route('tickets.history', ['result' => 'resolved']));

        $response->assertOk();
        $response->assertSeeText('Sin tickets resueltos');
        $response->assertSeeText('Volver a Todos');
    }

    public function test_rejected_chip_empty_state_has_specific_copy(): void
    {
        $me = $this->userWithRole('maintenance');
        $this->ticketFor($me, 'resolved', 'Solo resuelto'); // no rejected tickets

        $response = $this->actingAs($me)->get(route('tickets.history', ['result' => 'rejected']));

        $response->assertOk();
        $response->assertSeeText('Sin tickets rechazados');
        $response->assertSeeText('Volver a Todos');
    }

    public function test_filtered_empty_state_offers_clear_filters(): void
    {
        $me = $this->userWithRole('maintenance');
        $this->ticketFor($me, 'resolved', 'Existe pero no coincide');

        $response = $this->actingAs($me)->get(route('tickets.history', ['search' => 'zzz-sin-coincidencia']));

        $response->assertOk();
        $response->assertSeeText('Sin resultados');
    }

    public function test_history_route_is_named_and_pathed_correctly(): void
    {
        $this->assertStringEndsWith('/tickets/history', route('tickets.history'));
    }

    public function test_tickets_and_assignments_routes_remain_intact(): void
    {
        $reporter = $this->userWithRole('reporter');
        $this->actingAs($reporter)
            ->get(route('tickets.index'))
            ->assertRedirect(route('reporter.tickets.index'));

        $maintenance = $this->userWithRole('maintenance');
        $this->actingAs($maintenance)
            ->get(route('tickets.assignments'))
            ->assertOk()
            ->assertViewIs('tickets.assignments');
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
     * Create a ticket assigned to $tech in a final/active state.
     *
     * @param  array<string, mixed>  $attrs  location_id, category_id, priority, created_at, resolved_at
     */
    private function ticketFor(User $tech, string $state, string $title, array $attrs = []): Ticket
    {
        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripción de '.$title,
            'reporter_id' => $this->reporter()->id,
            'location_id' => $attrs['location_id'] ?? $this->location()->id,
            'category_id' => $attrs['category_id'] ?? $this->category()->id,
            'state' => 'open',
            'priority' => $attrs['priority'] ?? 'medium',
            'assignment_locked' => false,
        ]);

        $ticket->forceFill(array_filter([
            'assigned_to' => $tech->id,
            'assigned_by' => $tech->id,
            'assigned_at' => now(),
            'assignment_source' => Ticket::ASSIGNMENT_SOURCE_SELF,
            'state' => $state,
            'resolved_at' => $state === 'resolved' ? ($attrs['resolved_at'] ?? now()) : null,
            'created_at' => $attrs['created_at'] ?? null,
        ], fn ($v): bool => $v !== null))->save();

        return $ticket;
    }

    private function history(Ticket $ticket, string $from, string $to, User $by): void
    {
        StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => $from,
            'to_state' => $to,
            'changed_by' => $by->id,
            'comment' => null,
        ]);
    }

    private ?User $reporterFixture = null;

    private function reporter(): User
    {
        return $this->reporterFixture ??= User::factory()->create();
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
