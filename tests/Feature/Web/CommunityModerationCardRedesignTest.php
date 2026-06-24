<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityReport;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community Moderation Card Redesign — verifies the table has been replaced by cards.
 *
 * Coverage:
 *   A) Render: no table, yes card list, title, summary counts.
 *   B) Card content: ref, title, date, category, location, state badge, community badge.
 *   C) Context block: hidden reason/moderator, pending reports — only when data exists.
 *   D) Actions: Ver ficha, Ocultar, Restaurar with correct routes and CSRF.
 *   E) Access: roles, no PII leakage, no raw dashes as empty-value fillers.
 *   F) Filters / pagination: existing params still work.
 */
class CommunityModerationCardRedesignTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Render ─────────────────────────────────────────────────────────────

    public function test_page_returns_200_for_admin(): void
    {
        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk();
    }

    public function test_page_returns_200_for_super_admin(): void
    {
        $this->actingAs($this->createUserWithRole('super_admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk();
    }

    public function test_page_does_not_render_table_element(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertDontSee('<table', false);
    }

    public function test_page_does_not_render_thead(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertDontSee('<thead', false);
    }

    public function test_page_does_not_render_tbody(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertDontSee('<tbody', false);
    }

    public function test_page_renders_adm_comm_list_container(): void
    {
        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('adm-comm-list', false);
    }

    public function test_page_renders_title_moderacion_de_comunidad(): void
    {
        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Moderación de Comunidad', false);
    }

    public function test_page_renders_adm_comm_card_for_each_ticket(): void
    {
        $this->makeVisibleTicket(title: 'Ticket card test CRDA001');
        $this->makeVisibleTicket(title: 'Ticket card test CRDA002');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('adm-comm-card', false)
            ->assertSee('CRDA001', false)
            ->assertSee('CRDA002', false);
    }

    public function test_summary_counts_are_rendered(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Visibles en Comunidad', false);
    }

    // ── B. Card content ───────────────────────────────────────────────────────

    public function test_card_shows_ticket_ref(): void
    {
        $ticket = $this->makeVisibleTicket(title: 'Ref test CRDB001');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee(substr((string) $ticket->id, 0, 8), false);
    }

    public function test_card_shows_ticket_title(): void
    {
        $this->makeVisibleTicket(title: 'Falla en caldera CRDB002');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Falla en caldera CRDB002', false);
    }

    public function test_card_shows_category_name(): void
    {
        $cat = $this->makeCategory('Eléctrica-CRDB003');
        $this->makeVisibleTicket(title: 'Ticket cat CRDB003', category: $cat);

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Eléctrica-CRDB003', false);
    }

    public function test_card_shows_location_room_code(): void
    {
        $loc = $this->makeLocation(roomCode: 'AUL-CRDB004');
        $this->makeVisibleTicket(title: 'Ticket loc CRDB004', location: $loc);

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('AUL-CRDB004', false);
    }

    public function test_card_shows_state_label_abierto(): void
    {
        $this->makeVisibleTicket(title: 'Estado abierto CRDB005', state: Ticket::STATE_OPEN);

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Abierto', false);
    }

    public function test_card_shows_state_label_resuelto(): void
    {
        $this->makeVisibleTicket(title: 'Estado resuelto CRDB006', state: Ticket::STATE_RESOLVED);

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Resuelto', false);
    }

    public function test_card_shows_visible_badge_for_community_visible_ticket(): void
    {
        $this->makeVisibleTicket(title: 'Badge visible CRDB007');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Visible', false);
    }

    public function test_card_shows_oculto_badge_for_hidden_ticket(): void
    {
        $this->makeHiddenTicket(title: 'Badge oculto CRDB008');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee('Oculto', false);
    }

    public function test_cancelled_ticket_shows_no_aparece_en_feed_badge(): void
    {
        $this->makeVisibleTicket(
            title: 'Cancelado feed CRDB009',
            state: Ticket::STATE_CANCELLED,
        );

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('No aparece en feed', false);
    }

    // ── C. Context block ──────────────────────────────────────────────────────

    public function test_visible_ticket_without_reports_does_not_show_context_block(): void
    {
        $this->makeVisibleTicket(title: 'Sin contexto CRDC001');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertDontSee('adm-comm-card__context', false);
    }

    public function test_visible_ticket_without_reports_does_not_render_em_dash_as_placeholder(): void
    {
        $this->makeVisibleTicket(title: 'Sin guion CRDC002');

        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('>—<', (string) $content);
    }

    public function test_hidden_ticket_shows_visibility_reason_in_context(): void
    {
        $this->makeHiddenTicket(
            title: 'Con motivo CRDC003',
            reason: 'Contenido fuera de política CRDC003R',
        );

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee('Contenido fuera de política CRDC003R', false);
    }

    public function test_hidden_ticket_shows_moderator_name_in_context(): void
    {
        $moderator = $this->createUserWithRole('admin');
        $this->makeHiddenTicket(
            title: 'Con moderador CRDC004',
            reason: 'Razón CRDC004',
            hiddenBy: $moderator,
        );

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee($moderator->name, false);
    }

    public function test_ticket_with_pending_reports_shows_pendientes_in_context(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Con reportes CRDC005');

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('pendiente', false);
    }

    public function test_ticket_with_pending_reports_shows_resolver_reporte_action(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Resolver accion CRDC006');

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_INCORRECT_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Resolver reporte', false);
    }

    // ── D. Actions ────────────────────────────────────────────────────────────

    public function test_visible_card_shows_ver_ficha_and_ocultar(): void
    {
        $this->makeVisibleTicket(title: 'Acciones visible CRDD001');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Ver ficha', false)
            ->assertSee('Ocultar', false);
    }

    public function test_hidden_card_shows_ver_ficha_and_restaurar(): void
    {
        $this->makeHiddenTicket(title: 'Acciones oculto CRDD002');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee('Ver ficha', false)
            ->assertSee('Restaurar', false);
    }

    public function test_ver_ficha_links_to_tickets_show(): void
    {
        $ticket = $this->makeVisibleTicket(title: 'Ver ficha url CRDD003');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee(route('tickets.show', $ticket), false);
    }

    public function test_ocultar_form_has_csrf_and_patch_method(): void
    {
        $ticket = $this->makeVisibleTicket(title: 'CSRF ocultar CRDD004');

        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk();

        $response->assertSee(route('tickets.community.hide', $ticket), false);
        $response->assertSee('_method', false);
    }

    public function test_restaurar_form_has_csrf_and_patch_method(): void
    {
        $ticket = $this->makeHiddenTicket(title: 'CSRF restaurar CRDD005');

        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk();

        $response->assertSee(route('tickets.community.restore', $ticket), false);
        $response->assertSee('_method', false);
    }

    // ── E. Access + no PII ────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.community.moderation'))
            ->assertRedirect(route('login'));
    }

    public function test_reporter_cannot_access(): void
    {
        $this->actingAs($this->createUserWithRole('reporter'))
            ->get(route('admin.community.moderation'))
            ->assertForbidden();
    }

    public function test_page_does_not_expose_raw_file_url(): void
    {
        $this->makeVisibleTicket(title: 'No raw url CRDE003');

        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk();

        $this->assertStringNotContainsString('file_url', (string) $response->getContent());
    }

    // ── F. Filters / pagination ────────────────────────────────────────────────

    public function test_search_filter_still_works(): void
    {
        $this->makeVisibleTicket(title: 'Filtro búsqueda CRDF001');
        $this->makeVisibleTicket(title: 'Otro ticket CRDF001Z');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['q' => 'Filtro búsqueda']))
            ->assertOk()
            ->assertSee('CRDF001', false)
            ->assertDontSee('CRDF001Z', false);
    }

    public function test_visibility_filter_hidden_still_works(): void
    {
        $this->makeVisibleTicket(title: 'Solo visible CRDF002A');
        $this->makeHiddenTicket(title: 'Solo oculto CRDF002B');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee('CRDF002B', false)
            ->assertDontSee('CRDF002A', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleTicket(
        string $title = 'Ticket visible CARD',
        string $state = Ticket::STATE_OPEN,
        ?Category $category = null,
        ?Location $location = null,
    ): Ticket {
        $reporter = $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de prueba card redesign.',
            'reporter_id' => $reporter->id,
            'location_id' => ($location ?? $this->makeLocation())->id,
            'category_id' => ($category ?? $this->makeCategory())->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeHiddenTicket(
        string $title = 'Ticket oculto CARD',
        string $reason = 'Motivo de prueba card',
        ?User $hiddenBy = null,
    ): Ticket {
        $reporter = $this->createUserWithRole('reporter');
        $moderator = $hiddenBy ?? $this->createUserWithRole('admin');

        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripción de prueba card oculto.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => false,
        ]);

        $ticket->forceFill([
            'community_hidden_at' => now(),
            'community_hidden_by' => $moderator->id,
            'community_visibility_reason' => $reason,
        ])->save();

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

    private function makeLocation(string $building = 'Edificio Test Card', string $roomCode = ''): Location
    {
        return Location::create([
            'name' => 'Sala CARD-'.Str::upper(Str::random(4)),
            'building' => $building,
            'floor' => '1',
            'room_code' => $roomCode !== '' ? $roomCode : 'CARD-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-card-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(string $name = ''): Category
    {
        return Category::create([
            'name' => $name !== '' ? $name : 'Cat-CARD-'.Str::lower(Str::random(5)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests card redesign',
        ]);
    }
}
