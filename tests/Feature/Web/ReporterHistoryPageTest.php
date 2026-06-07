<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\ViewModels\Tickets\ReporterTicketHistoryViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Historial de mis tickets" is the reporter-only history board — LIVE DATA.
 *
 * History = the reporter's OWN closed-out tickets (reporter_id = them, state in
 * resolved/rejected). There is no "cancelled" domain state, so the chips are
 * only Todos / Resueltos / Rechazados. These tests pin down the ownership/no-leak
 * scope across every entry point, the real chips/filters/pagination, the honest
 * average + monthly metrics, the empty states, and that NO maintenance action is
 * exposed.
 */
class ReporterHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reporter.tickets.history'))->assertRedirect(route('login'));
    }

    public function test_route_is_named_and_pathed_correctly(): void
    {
        $this->assertStringEndsWith('/reporter/tickets/history', route('reporter.tickets.history'));
    }

    public function test_reporter_can_view_the_history(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'resolved', 'Resuelto visible mío');

        $response = $this->actingAs($me)->get(route('reporter.tickets.history'));

        $response->assertOk();
        $response->assertViewIs('tickets.reporter.history');
        $this->assertInstanceOf(ReporterTicketHistoryViewModel::class, $response->viewData('board'));
        $response->assertSeeText('Historial de mis tickets');
        $response->assertSeeText('Resuelto visible mío');
    }

    public function test_maintenance_and_admin_are_forbidden(): void
    {
        foreach (['maintenance', 'admin', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('reporter.tickets.history'))
                ->assertForbidden();
        }
    }

    public function test_history_only_shows_my_closed_tickets(): void
    {
        $me = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');

        $this->ticketFor($me, 'resolved', 'Resuelto mío');
        $this->ticketFor($me, 'rejected', 'Rechazado mío');
        $this->ticketFor($me, 'open', 'Abierto mío activo');
        $this->ticketFor($me, 'in_progress', 'En progreso mío activo');
        $this->ticketFor($other, 'resolved', 'Resuelto de otro reporter');

        $response = $this->actingAs($me)->get(route('reporter.tickets.history'));

        $response->assertOk();
        $response->assertSeeText('Resuelto mío');
        $response->assertSeeText('Rechazado mío');
        // Active states are not history.
        $response->assertDontSeeText('Abierto mío activo');
        $response->assertDontSeeText('En progreso mío activo');
        // Another reporter's ticket never leaks.
        $response->assertDontSeeText('Resuelto de otro reporter');
    }

    public function test_chips_show_real_counts_and_no_cancelled_chip(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'resolved', 'R1');
        $this->ticketFor($me, 'resolved', 'R2');
        $this->ticketFor($me, 'rejected', 'X1');

        $response = $this->actingAs($me)->get(route('reporter.tickets.history'));
        $chips = collect($response->viewData('board')->chips)->keyBy('key');

        $response->assertOk();
        $this->assertSame(3, $chips['all']['count']);
        $this->assertSame(2, $chips['resolved']['count']);
        $this->assertSame(1, $chips['rejected']['count']);
        // No invented "cancelled" state.
        $this->assertNull($chips->get('cancelled'));
        $response->assertDontSeeText('Cancelados');
    }

    public function test_result_chip_filters_the_list(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'resolved', 'Resuelto para filtro');
        $this->ticketFor($me, 'rejected', 'Rechazado para filtro');

        $response = $this->actingAs($me)->get(route('reporter.tickets.history', ['status' => 'rejected']));

        $response->assertOk();
        $response->assertSeeText('Rechazado para filtro');
        $response->assertDontSeeText('Resuelto para filtro');
    }

    public function test_search_does_not_leak_other_reporters_history(): void
    {
        $me = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');

        $this->ticketFor($me, 'resolved', 'Incidencia buscada propia');
        $this->ticketFor($other, 'resolved', 'Incidencia buscada ajena');

        $response = $this->actingAs($me)->get(route('reporter.tickets.history', ['search' => 'buscada']));

        $response->assertOk();
        $response->assertSeeText('Incidencia buscada propia');
        $response->assertDontSeeText('Incidencia buscada ajena');
    }

    public function test_pagination_caps_at_five(): void
    {
        $me = $this->userWithRole('reporter');
        for ($i = 1; $i <= 6; $i++) {
            $this->ticketFor($me, 'resolved', "Cerrado {$i}");
        }

        $response = $this->actingAs($me)->get(route('reporter.tickets.history'));

        $response->assertOk();
        $response->assertSeeText('Mostrando 1 a 5 de 6 tickets');
        $this->assertCount(5, $response->viewData('board')->tickets);
    }

    public function test_donut_uses_only_my_closed_counts(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'resolved', 'R');
        $this->ticketFor($me, 'resolved', 'R2');
        $this->ticketFor($me, 'rejected', 'X');
        $this->ticketFor($this->userWithRole('reporter'), 'resolved', 'Ajeno');

        $board = $this->actingAs($me)->get(route('reporter.tickets.history'))->viewData('board');
        $segments = collect($board->summary['donut'])->keyBy('key');

        $this->assertSame(3, $board->summary['total']);
        $this->assertSame(2, $segments['resolved']['count']);
        $this->assertSame(1, $segments['rejected']['count']);
        $this->assertSame(67, $segments['resolved']['percent']);
    }

    public function test_average_resolution_uses_minutes_not_truncated_hours(): void
    {
        $me = $this->userWithRole('reporter');
        // 2h 30m (150 min) — a truncating implementation would wrongly show "2h".
        $this->ticketFor($me, 'resolved', 'Resuelto en 150 min', [
            'created_at' => Carbon::parse('2026-06-01 09:00:00'),
            'resolved_at' => Carbon::parse('2026-06-01 11:30:00'),
        ]);

        $board = $this->actingAs($me)->get(route('reporter.tickets.history'))->viewData('board');

        $this->assertTrue($board->summary['avg_has_data']);
        $this->assertSame('2h 30m', $board->summary['avg_value']);
    }

    public function test_average_is_honest_placeholder_without_resolved(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'rejected', 'Solo rechazado');

        $board = $this->actingAs($me)->get(route('reporter.tickets.history'))->viewData('board');

        $this->assertFalse($board->summary['avg_has_data']);
        $this->assertSame('Sin datos', $board->summary['avg_value']);
    }

    public function test_monthly_chart_shows_placeholder_without_resolved(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'rejected', 'Rechazado sin resueltos');

        $response = $this->actingAs($me)->get(route('reporter.tickets.history'));

        $response->assertOk();
        $this->assertFalse($response->viewData('board')->summary['monthly']['has_data']);
        $response->assertSeeText('Aún no hay tickets resueltos para mostrar.');
    }

    public function test_rows_link_to_the_reporter_tracking_screen(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'resolved', 'Cerrado con detalle');

        $response = $this->actingAs($me)->get(route('reporter.tickets.history'));

        $response->assertOk();
        $response->assertSeeText('Ver detalle');
        $response->assertSee(route('reporter.tickets.show', $ticket->id), false);
    }

    public function test_empty_state_when_no_history(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Solo activo, no historial');

        $response = $this->actingAs($me)->get(route('reporter.tickets.history'));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('board')->summary['total']);
        $response->assertSeeText('Aún no tienes historial');
    }

    public function test_filtered_empty_state_offers_clear_filters(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'resolved', 'Existe pero no coincide');

        $response = $this->actingAs($me)->get(route('reporter.tickets.history', ['search' => 'zzz-no-match']));

        $response->assertOk();
        $response->assertSeeText('Sin resultados');
    }

    public function test_export_remains_a_visual_placeholder(): void
    {
        $me = $this->userWithRole('reporter');

        $response = $this->actingAs($me)->get(route('reporter.tickets.history'));

        $response->assertOk();
        $response->assertSeeText('Exportar');
        $response->assertSeeText('Próximamente');
        $response->assertDontSee('reporter.tickets.history.export');
    }

    public function test_does_not_expose_maintenance_actions(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'resolved', 'Cerrado mío');

        $response = $this->actingAs($me)->get(route('reporter.tickets.history'));

        $response->assertOk();
        $response->assertDontSeeText('Tomar');
        $response->assertDontSeeText('Liberar');
        $response->assertDontSeeText('Resolver ticket');
    }

    public function test_other_reporter_routes_remain_intact(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Activo');

        $this->actingAs($me)->get(route('reporter.tickets.index'))->assertOk()->assertViewIs('tickets.reporter.index');
        $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id))->assertOk()->assertViewIs('tickets.reporter.show');
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
     * @param  array<string, mixed>  $attrs  location_id, category_id, priority, created_at, resolved_at
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
            'assignment_locked' => false,
        ]);

        $force = array_filter([
            'resolved_at' => $attrs['resolved_at'] ?? ($state === 'resolved' ? Carbon::now() : null),
            'created_at' => $attrs['created_at'] ?? null,
        ], fn ($v): bool => $v !== null);

        if ($force !== []) {
            $ticket->forceFill($force)->save();
        }

        if ($state === 'rejected') {
            $this->transition($ticket, 'in_progress', 'rejected', $reporter, $attrs['rejected_at'] ?? Carbon::now());
        }

        return $ticket;
    }

    private function transition(Ticket $ticket, string $from, string $to, User $by, Carbon $at): void
    {
        $history = StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => $from,
            'to_state' => $to,
            'changed_by' => $by->id,
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
