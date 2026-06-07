<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The redesigned reporter home dashboard at /reporter/dashboard (parallel to
 * /dashboard, which it does NOT replace).
 *
 * MIXED PHASE: the greeting and the "Mis reportes recientes" list are LIVE —
 * the recent list reuses ReporterTicketsBoardQuery (reporter_id scope), so its
 * links resolve to real tickets and never produce a fictitious 404. The KPI
 * strip / donut / average / activity remain curated placeholders. These tests
 * pin down the reporter-only boundary, the real recent list + no-leak + empty
 * state, that no fictitious id is rendered, and that the reporter ticket routes
 * stay intact.
 */
class ReporterDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reporter.dashboard'))->assertRedirect(route('login'));
    }

    public function test_route_is_named_and_pathed_correctly(): void
    {
        $this->assertStringEndsWith('/reporter/dashboard', route('reporter.dashboard'));
    }

    public function test_reporter_can_view_the_dashboard(): void
    {
        $reporter = $this->userWithRole('reporter', 'Reportero Demo');

        $response = $this->actingAs($reporter)->get(route('reporter.dashboard'));

        $response->assertOk();
        $response->assertViewIs('reporter.dashboard');
        // The hero greets the real user by name.
        $response->assertSeeText('¡Hola, Reportero Demo!');
        $response->assertSeeText('Crear nuevo ticket');
    }

    public function test_shows_kpi_strip(): void
    {
        $response = $this->actingAs($this->userWithRole('reporter'))->get(route('reporter.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Tickets totales');
        $response->assertSeeText('Abiertos');
        $response->assertSeeText('En progreso');
        $response->assertSeeText('Posible duplicado');
    }

    public function test_recent_reports_link_to_real_reporter_tickets(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Mi reporte reciente real');

        $response = $this->actingAs($me)->get(route('reporter.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Mis reportes recientes');
        $response->assertSeeText('Mi reporte reciente real');
        // The "Ver" action lands on the real tracking screen with the REAL id.
        $response->assertSee(route('reporter.tickets.show', $ticket->id), false);
    }

    public function test_recent_reports_do_not_link_to_another_reporters_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');
        $foreign = $this->ticketFor($other, 'open', 'Reporte de otro usuario');

        $response = $this->actingAs($me)->get(route('reporter.dashboard'));

        $response->assertOk();
        $response->assertDontSeeText('Reporte de otro usuario');
        $response->assertDontSee(route('reporter.tickets.show', $foreign->id), false);
    }

    public function test_recent_reports_empty_state(): void
    {
        $me = $this->userWithRole('reporter');

        $response = $this->actingAs($me)->get(route('reporter.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Aún no has reportado incidencias');
        $response->assertSee(route('tickets.create'), false);
    }

    public function test_no_fictitious_ticket_ids_are_rendered(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Real');

        $response = $this->actingAs($me)->get(route('reporter.dashboard'));

        $response->assertOk();
        // The old static placeholder refs/ids must be gone for good.
        $response->assertDontSee('TIC-2026-0012', false);
        $response->assertDontSee(route('reporter.tickets.show', 'TIC-2026-0012'), false);
    }

    public function test_shows_quick_actions_and_insights(): void
    {
        $response = $this->actingAs($this->userWithRole('reporter'))->get(route('reporter.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Acciones rápidas');
        $response->assertSee(route('tickets.create'), false);
        $response->assertSee(route('reporter.tickets.history'), false);
        $response->assertSeeText('Estado de mis tickets');
        $response->assertSeeText('Tiempo promedio de respuesta');
        $response->assertSeeText('Actividad reciente');
    }

    public function test_insights_are_live_not_fabricated(): void
    {
        $me = $this->userWithRole('reporter');
        $this->ticketFor($me, 'open', 'Solo abierto');

        $response = $this->actingAs($me)->get(route('reporter.dashboard'));

        $response->assertOk();
        // The old fabricated numbers / non-existent donut band must be gone.
        $response->assertDontSee('4h 20m', false);
        $response->assertDontSeeText('En revisión');
        // The average is the real metric — honest placeholder when there is no data.
        $response->assertSeeText('Sin datos');
    }

    public function test_activity_is_an_honest_placeholder(): void
    {
        $response = $this->actingAs($this->userWithRole('reporter'))->get(route('reporter.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Actividad reciente');
        $response->assertSeeText('Próximamente');
        // No fabricated activity events.
        $response->assertDontSeeText('Un reporte tuyo pasó a En progreso.');
    }

    public function test_maintenance_cannot_view_the_dashboard(): void
    {
        $this->actingAs($this->userWithRole('maintenance'))
            ->get(route('reporter.dashboard'))->assertForbidden();
    }

    public function test_admin_cannot_view_the_dashboard(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('reporter.dashboard'))->assertForbidden();
    }

    public function test_does_not_expose_maintenance_actions(): void
    {
        $response = $this->actingAs($this->userWithRole('reporter'))->get(route('reporter.dashboard'));

        $response->assertOk();
        $response->assertDontSeeText('Tomar');
        $response->assertDontSeeText('Liberar');
        $response->assertDontSeeText('Resolver ticket');
    }

    public function test_reporter_ticket_routes_remain_intact(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Para rutas');

        $this->actingAs($me)->get(route('reporter.tickets.index'))->assertOk()->assertViewIs('tickets.reporter.index');
        $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id))->assertOk()->assertViewIs('tickets.reporter.show');
    }

    public function test_classic_dashboard_route_remains_intact(): void
    {
        $reporter = $this->userWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertViewIs('dashboard.reporter');
    }

    // ── Fixtures ─────────────────────────────────────────────────

    private function userWithRole(string $role, ?string $name = null): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::factory()->create($name !== null ? ['name' => $name] : []);
        $user->assignRole($role);

        return $user;
    }

    private function ticketFor(User $reporter, string $state, string $title): Ticket
    {
        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de '.$title,
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => $state,
            'priority' => 'medium',
            'assignment_locked' => false,
        ]);
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
            ['icon' => 'monitor', 'description' => 'Categoría Hardware'],
        );
    }
}
