<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReporterCommunityPageTest extends TestCase
{
    use RefreshDatabase;

    // ── Access gates (skeleton tests preserved) ─────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reporter.community'))
            ->assertRedirect(route('login'));
    }

    public function test_reporter_can_access_community_page(): void
    {
        $user = $this->createUserWithRole('reporter');

        $this->actingAs($user)
            ->get(route('reporter.community'))
            ->assertOk();
    }

    public function test_reporter_sees_comunidad_section(): void
    {
        $user = $this->createUserWithRole('reporter');

        $this->actingAs($user)
            ->get(route('reporter.community'))
            ->assertSee('Comunidad del campus', false)
            ->assertSee('comm-page', false);
    }

    public function test_reporter_sees_comunidad_nav_link(): void
    {
        $user = $this->createUserWithRole('reporter');

        $this->actingAs($user)
            ->get(route('reporter.community'))
            ->assertSee(route('reporter.community'), false)
            ->assertSee('Comunidad', false);
    }

    public function test_page_does_not_contain_tailwind_cdn(): void
    {
        $user = $this->createUserWithRole('reporter');

        $this->actingAs($user)
            ->get(route('reporter.community'))
            ->assertDontSee('cdn.tailwindcss.com', false);
    }

    public function test_admin_cannot_access_reporter_community(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->get(route('reporter.community'))
            ->assertForbidden();
    }

    public function test_maintenance_cannot_access_reporter_community(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $this->actingAs($maintenance)
            ->get(route('reporter.community'))
            ->assertForbidden();
    }

    // ── Real data feed ───────────────────────────────────────────────────────

    public function test_reporter_sees_real_ticket_in_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation();
        $category = $this->makeCategory('Equipos');

        Ticket::create([
            'title' => 'Proyector dañado sala A101',
            'description' => 'El proyector no enciende desde ayer por la mañana.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'high',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Proyector dañado sala A101', false)
            ->assertSee('Equipos', false)
            ->assertSee('Abierto', false)
            ->assertSee('Alta', false);
    }

    public function test_feed_shows_location_data(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation('LAB-101', 'Edificio C', '3');
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'PC sin arranque',
            'description' => 'La PC no responde al botón de encendido.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('LAB-101', false)
            ->assertSee('Edificio C', false);
    }

    public function test_in_progress_tickets_appear_in_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Red caída en laboratorio',
            'description' => 'Sin conexión a internet desde las 8am.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_IN_PROGRESS,
            'priority' => 'high',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Red caída en laboratorio', false)
            ->assertSee('En progreso', false);
    }

    public function test_resolved_tickets_appear_in_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Falla de luz resuelta',
            'description' => 'La luz del aula ya fue reparada.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_RESOLVED,
            'priority' => 'low',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Falla de luz resuelta', false)
            ->assertSee('Resuelto', false);
    }

    // ── Visibility rules: hidden states ─────────────────────────────────────

    public function test_cancelled_tickets_do_not_appear_in_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Ticket cancelado unico XYZ999',
            'description' => 'Reporte cancelado por el reporter.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_CANCELLED,
            'priority' => 'low',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Ticket cancelado unico XYZ999', false);
    }

    public function test_rejected_tickets_do_not_appear_in_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Ticket rechazado unico ABC888',
            'description' => 'Reporte rechazado por mantenimiento.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_REJECTED,
            'priority' => 'low',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Ticket rechazado unico ABC888', false);
    }

    // ── Media ────────────────────────────────────────────────────────────────

    public function test_feed_shows_media_tag_when_ticket_has_media(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        $ticket = Ticket::create([
            'title' => 'Silla rota con foto adjunta',
            'description' => 'La silla tiene la pata rota.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);

        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://demo.incidex.test/evidencias/silla.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Con evidencia', false);
    }

    // ── No-leak: personal data must not appear ───────────────────────────────

    public function test_page_does_not_expose_other_users_personal_data(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = User::factory()->create([
            'name' => 'OtherUserUnique',
            'last_name' => 'SecretSurnameXYZ',
            'email' => 'other-unique@example.test',
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk();
        $response->assertDontSee($other->email, false);
        $response->assertDontSee('OtherUserUnique', false);
        $response->assertDontSee('SecretSurnameXYZ', false);
    }

    /**
     * A ticket filed by reporter A must not expose A's personal data
     * when reporter B is viewing the community feed.
     */
    public function test_feed_does_not_expose_reporter_email_or_name_from_ticket(): void
    {
        // $ticketOwner's data should never appear in the feed (the viewer is different).
        $ticketOwner = $this->createUserWithRole('reporter');
        $ticketOwner->update(['name' => 'SecretReporterName', 'email' => 'secret-reporter@test.test']);

        $viewer = $this->createUserWithRole('reporter');

        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Ticket visible con datos ocultos',
            'description' => 'Descripción pública sin datos personales.',
            'reporter_id' => $ticketOwner->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);

        // $viewer (not $ticketOwner) views the feed.
        $response = $this->actingAs($viewer)
            ->get(route('reporter.community'));

        $response->assertOk();
        $response->assertSee('Ticket visible con datos ocultos', false);
        $response->assertDontSee('secret-reporter@test.test', false);
        $response->assertDontSee('SecretReporterName', false);
    }

    public function test_feed_does_not_expose_assigned_technician(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $technician = $this->createUserWithRole('maintenance');
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        $technician->update(['name' => 'TechnicianNamePrivate', 'email' => 'tech-private@test.test']);

        Ticket::create([
            'title' => 'Ticket en progreso asignado',
            'description' => 'Incidencia asignada a técnico.',
            'reporter_id' => $reporter->id,
            'assigned_to' => $technician->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_IN_PROGRESS,
            'priority' => 'high',
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk();
        $response->assertSee('Ticket en progreso asignado', false);
        $response->assertDontSee('TechnicianNamePrivate', false);
        $response->assertDontSee('tech-private@test.test', false);
    }

    // ── Filters ──────────────────────────────────────────────────────────────

    public function test_filter_by_search_q_returns_matching_tickets(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Computadora sin encender AulaZ',
            'description' => 'El equipo no responde.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);
        Ticket::create([
            'title' => 'Silla rota completamente diferente',
            'description' => 'La silla tiene una pata rota.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'low',
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community').'?q=AulaZ');

        $response->assertOk()
            ->assertSee('Computadora sin encender AulaZ', false)
            ->assertDontSee('Silla rota completamente diferente', false);
    }

    public function test_filter_by_category_returns_matching_tickets(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation();
        $catA = $this->makeCategory('Hardware-UniqueX');
        $catB = $this->makeCategory('Software-UniqueY');

        Ticket::create([
            'title' => 'Falla hardware unica FHXXX',
            'description' => 'El hardware falló.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $catA->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);
        Ticket::create([
            'title' => 'Error software unico FSYYY',
            'description' => 'El software tuvo un error.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $catB->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'low',
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community').'?category='.$catA->id);

        $response->assertOk()
            ->assertSee('Falla hardware unica FHXXX', false)
            ->assertDontSee('Error software unico FSYYY', false);
    }

    public function test_filter_by_building_returns_matching_tickets(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $locA = $this->makeLocation('A101', 'Edificio Alpha');
        $locB = $this->makeLocation('B202', 'Edificio Beta');
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Problema en Edificio Alpha XPAZ',
            'description' => 'Incidencia detectada.',
            'reporter_id' => $reporter->id,
            'location_id' => $locA->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);
        Ticket::create([
            'title' => 'Problema en Edificio Beta XPBZ',
            'description' => 'Incidencia diferente.',
            'reporter_id' => $reporter->id,
            'location_id' => $locB->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community').'?building='.urlencode('Edificio Alpha'));

        $response->assertOk()
            ->assertSee('Problema en Edificio Alpha XPAZ', false)
            ->assertDontSee('Problema en Edificio Beta XPBZ', false);
    }

    public function test_filter_by_state_returns_only_that_state(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Ticket abierto para filtro QQQ',
            'description' => 'Ticket aún abierto.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);
        Ticket::create([
            'title' => 'Ticket resuelto para filtro PPP',
            'description' => 'Ticket ya resuelto.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_RESOLVED,
            'priority' => 'low',
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community').'?state=resolved');

        $response->assertOk()
            ->assertSee('Ticket resuelto para filtro PPP', false)
            ->assertDontSee('Ticket abierto para filtro QQQ', false);
    }

    public function test_filter_has_media_returns_only_tickets_with_media(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        $ticketWithMedia = Ticket::create([
            'title' => 'Ticket con evidencia adjunta MMM',
            'description' => 'Tiene foto adjunta.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);
        TicketMedia::create([
            'ticket_id' => $ticketWithMedia->id,
            'file_url' => 'https://demo.incidex.test/e/mmm.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_by' => $reporter->id,
        ]);

        Ticket::create([
            'title' => 'Ticket sin evidencia NNN',
            'description' => 'Sin archivos adjuntos.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'low',
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community').'?has_media=1');

        $response->assertOk()
            ->assertSee('Ticket con evidencia adjunta MMM', false)
            ->assertDontSee('Ticket sin evidencia NNN', false);
    }

    // ── Empty state ──────────────────────────────────────────────────────────

    public function test_empty_state_appears_when_no_results(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('reporter.community').'?q=cadena-que-no-existe-jamas-xyz9999')
            ->assertOk()
            ->assertSee('Sin reportes públicos', false);
    }

    // ── Social placeholders ──────────────────────────────────────────────────

    public function test_social_actions_are_disabled_placeholders(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        Ticket::create([
            'title' => 'Ticket para verificar acciones sociales',
            'description' => 'Descripción de prueba para acciones sociales.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk()
            ->assertSee('Me interesa', false)
            ->assertSee('Comentar', false)
            ->assertSee('Guardar', false);

        $this->assertStringContainsString(
            'comm-action-btn--disabled',
            $response->getContent(),
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

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

    private function makeLocation(
        string $roomCode = '',
        string $building = 'Edificio A',
        string $floor = '1',
    ): Location {
        return Location::create([
            'name' => 'Aula Comunidad Test',
            'building' => $building,
            'floor' => $floor,
            'room_code' => $roomCode !== '' ? $roomCode : 'CM-'.Str::upper(Str::random(5)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(string $name = ''): Category
    {
        return Category::create([
            'name' => $name !== '' ? $name : 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de comunidad',
        ]);
    }
}
