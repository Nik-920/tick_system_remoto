<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Guía del reporter" is the reporter-only static help page (FIRST VISUAL
 * PHASE). These tests pin down the access boundary, the static content the
 * mockup requires (steps, best practices, good/poor examples, categories,
 * important info), the "Crear nuevo ticket" CTA, and that no maintenance action
 * leaks. They also assert the previously-placeholder "Ver guía" links across the
 * reporter surfaces now point here.
 */
class ReporterGuidePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reporter.guide'))->assertRedirect(route('login'));
    }

    public function test_route_is_named_and_pathed_correctly(): void
    {
        $this->assertStringEndsWith('/reporter/guide', route('reporter.guide'));
    }

    public function test_reporter_can_view_the_guide(): void
    {
        $response = $this->actingAs($this->userWithRole('reporter'))->get(route('reporter.guide'));

        $response->assertOk();
        $response->assertViewIs('reporter.guide');
        $response->assertSeeText('Guía del reporter');
        $response->assertSeeText('Pasos para reportar una incidencia');
    }

    public function test_shows_steps_practices_examples(): void
    {
        $response = $this->actingAs($this->userWithRole('reporter'))->get(route('reporter.guide'));

        $response->assertOk();
        $response->assertSeeText('Describe el problema');
        $response->assertSeeText('Envía tu reporte');
        $response->assertSeeText('Buenas prácticas para un mejor reporte');
        $response->assertSeeText('Ejemplos de buenos reportes');
        $response->assertSeeText('Buen reporte');
        $response->assertSeeText('Reporte poco claro');
    }

    public function test_shows_categories_and_info(): void
    {
        $response = $this->actingAs($this->userWithRole('reporter'))->get(route('reporter.guide'));

        $response->assertOk();
        $response->assertSeeText('Categorías disponibles');
        $response->assertSeeText('Hardware');
        $response->assertSeeText('Servicios');
        $response->assertSeeText('Información importante');
    }

    public function test_header_cta_links_to_create_ticket(): void
    {
        $response = $this->actingAs($this->userWithRole('reporter'))->get(route('reporter.guide'));

        $response->assertOk();
        $response->assertSeeText('Crear nuevo ticket');
        $response->assertSee(route('tickets.create'), false);
    }

    public function test_maintenance_cannot_view_the_guide(): void
    {
        $this->actingAs($this->userWithRole('maintenance'))
            ->get(route('reporter.guide'))->assertForbidden();
    }

    public function test_admin_cannot_view_the_guide(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('reporter.guide'))->assertForbidden();
    }

    public function test_does_not_expose_maintenance_actions(): void
    {
        $response = $this->actingAs($this->userWithRole('reporter'))->get(route('reporter.guide'));

        $response->assertOk();
        $response->assertDontSeeText('Tomar');
        $response->assertDontSeeText('Liberar');
        $response->assertDontSeeText('Resolver ticket');
    }

    public function test_guide_links_are_wired_across_reporter_surfaces(): void
    {
        $reporter = $this->userWithRole('reporter');
        $guideUrl = route('reporter.guide');

        // Board, history and dashboard all link to the guide now.
        $this->actingAs($reporter)->get(route('reporter.tickets.index'))->assertSee($guideUrl, false);
        $this->actingAs($reporter)->get(route('reporter.tickets.history'))->assertSee($guideUrl, false);
        $this->actingAs($reporter)->get(route('reporter.dashboard'))->assertSee($guideUrl, false);
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
}
