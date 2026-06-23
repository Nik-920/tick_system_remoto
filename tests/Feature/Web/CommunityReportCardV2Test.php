<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\CommunityReaction;
use App\Models\CommunitySave;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community Report Card v2 — visual restructure regression suite.
 *
 * Verifies the feed-style card layout (topbar, 2-column body, evidence panel,
 * coloured action bar, collapsible comments, footer) while guaranteeing that
 * the social actions, media proxy, comments and no-PII contracts are preserved.
 */
class CommunityReportCardV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
    }

    // ── 1. Card shell ─────────────────────────────────────────────────────────

    public function test_feed_renders_v2_card_layout(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket v2 layout CARDV2');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2', (string) $html);
        $this->assertStringContainsString('comm-post-v2__topbar', (string) $html);
        $this->assertStringContainsString('comm-post-v2__body', (string) $html);
        $this->assertStringContainsString('comm-post-v2__actions', (string) $html);
        $this->assertStringContainsString('comm-post-v2__footer', (string) $html);
    }

    // ── 2. Topbar: category + Reporte público ─────────────────────────────────

    public function test_card_shows_category_and_public_chip(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket categoria CARDCAT', category: $this->makeCategory('Equipos'));

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Equipos', false)
            ->assertSee('Reporte público', false);
    }

    // ── 3. Topbar: time + ID reference ────────────────────────────────────────

    public function test_card_shows_time_and_reference_id(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket ref CARDREF');

        $reference = '#'.strtoupper(substr((string) $ticket->id, 0, 8));

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($reference, (string) $html);
        $this->assertStringContainsString('comm-post-v2__meta', (string) $html);
    }

    // ── 4. State + priority badges ────────────────────────────────────────────

    public function test_card_shows_state_and_priority_badges(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket critico CARDPRIO', priority: 'critical');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Abierto', false)
            ->assertSee('Crítica', false);
    }

    // ── 5. Title ──────────────────────────────────────────────────────────────

    public function test_card_shows_title(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Verificar si se sube una imagen CARDTITLE');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Verificar si se sube una imagen CARDTITLE', false)
            ->assertSee('comm-post-v2__title', false);
    }

    // ── 6. Location chips ─────────────────────────────────────────────────────

    public function test_card_shows_location_pills(): void
    {
        $reporter = $this->makeReporter();
        $location = Location::create([
            'name' => 'Aula CardV2',
            'building' => 'Edificio 1',
            'floor' => '1',
            'room_code' => 'AUL-002',
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
        $this->makeVisibleTicket($reporter, 'Ticket ubicacion CARDLOC', location: $location);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('AUL-002', false)
            ->assertSee('Edificio 1', false)
            ->assertSee('Piso 1', false);
    }

    // ── 7. Description ────────────────────────────────────────────────────────

    public function test_card_shows_description_panel(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket desc CARDDESC');
        $ticket->update(['description' => 'El sistema no confirma si la imagen fue cargada correctamente CARDDESCBODY.']);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-post-v2__desc', false)
            ->assertSee('CARDDESCBODY', false);
    }

    // ── 8. Media uses proxy URL ───────────────────────────────────────────────

    public function test_card_with_media_uses_proxy_thumbnail_url(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket media CARDMEDIA');
        $media = TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://demo.incidex.test/e/card-v2.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee(route('reporter.community.media.thumbnail', $media->id), false)
            ->assertSee('archivo', false);
    }

    // ── 9. Raw file_url is never exposed ──────────────────────────────────────

    public function test_card_does_not_expose_raw_file_url(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket raw CARDRAW');
        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://raw-storage.example.com/secret-card-v2.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('secret-card-v2.jpg', false)
            ->assertSee('community/media', false);
    }

    // ── 10. No media → placeholder ────────────────────────────────────────────

    public function test_card_without_media_shows_placeholder(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket sin media CARDNOMEDIA');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-post-v2__media-placeholder', false)
            ->assertSee('Sin evidencia', false);
    }

    // ── 11. Social reaction + save actions preserved ──────────────────────────

    public function test_card_keeps_all_social_actions(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket acciones CARDSOC');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Me interesa', false)
            ->assertSee('También me pasa', false)
            ->assertSee('Lo vi', false)
            ->assertSee('Guardar', false)
            ->getContent();

        $this->assertStringContainsString('comm-action-btn', (string) $html);
        $this->assertStringNotContainsString('comm-action-btn--disabled', (string) $html);
        $this->assertStringContainsString('Marcar como Me interesa', (string) $html);
        $this->assertStringContainsString('Guardar reporte', (string) $html);
    }

    // ── 12. Reportar present ──────────────────────────────────────────────────

    public function test_card_keeps_report_action(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket reportar CARDREP');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Reportar', false)
            ->getContent();

        $this->assertStringContainsString('comm-report-summary--danger', (string) $html);
        $this->assertStringContainsString(
            route('reporter.community.reports.store', $ticket->id),
            (string) $html
        );
    }

    // ── 13. Progressive-enhancement data attributes preserved ─────────────────

    public function test_card_preserves_js_data_attributes(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket attrs CARDATTR');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('data-community-social-form', false)
            ->assertSee('data-community-action-button', false)
            ->assertSee('data-store-url', false)
            ->assertSee('data-destroy-url', false)
            ->assertSee('data-community-action="save"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('data-community-social-status', false)
            ->assertSee('aria-live="polite"', false);
    }

    // ── 14. Comments section present with count ───────────────────────────────

    public function test_card_shows_comments_section_with_count(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket comentarios CARDCOM');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario util para la comunidad CARDCOMBODY.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Comentarios', false)
            ->getContent();

        $this->assertStringContainsString('comm-comments-summary__count', (string) $html);
        $this->assertStringContainsString('CARDCOMBODY', (string) $html);
        $this->assertStringContainsString(
            route('reporter.community.comments.store', $ticket->id),
            (string) $html
        );
    }

    // ── 15. Footer + no-PII ───────────────────────────────────────────────────

    public function test_card_footer_is_anonymous_and_pii_free(): void
    {
        $ticketOwner = $this->makeReporter();
        $ticketOwner->update(['name' => 'SecretCardOwner', 'email' => 'secret-card@test.test']);

        $viewer = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($ticketOwner, 'Ticket footer CARDFOOT');

        $secretReactor = User::factory()->create([
            'name' => 'SecretCardReactor',
            'email' => 'secret-card-reactor@test.test',
        ]);
        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $secretReactor->id,
            'type' => 'interested',
        ]);
        CommunitySave::create(['ticket_id' => $ticket->id, 'user_id' => $secretReactor->id]);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Verificación activa', false)
            ->assertSee('Creado por usuario anónimo', false)
            ->assertDontSee('SecretCardOwner', false)
            ->assertDontSee('secret-card@test.test', false)
            ->assertDontSee('SecretCardReactor', false)
            ->assertDontSee('secret-card-reactor@test.test', false);
    }

    // ── 16. No legacy sidebar / tabs / sort bar ───────────────────────────────

    public function test_card_page_has_no_legacy_sidebar_tabs_or_sortbar(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket no legacy CARDLEG');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('comm-tabs', (string) $html);
        $this->assertStringNotContainsString('comm-sort-bar', (string) $html);
        $this->assertStringNotContainsString('class="comm-sidebar"', (string) $html);
        $this->assertStringNotContainsString('comm-saves-link', (string) $html);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeReporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeVisibleTicket(
        User $reporter,
        string $title,
        string $priority = 'medium',
        ?Location $location = null,
        ?Category $category = null,
    ): Ticket {
        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción pública del reporte para card v2.',
            'reporter_id' => $reporter->id,
            'location_id' => ($location ?? $this->makeLocation())->id,
            'category_id' => ($category ?? $this->makeCategory())->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => $priority,
            'community_visible' => true,
        ]);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Aula CardV2 '.Str::upper(Str::random(4)),
            'building' => 'Edificio CV',
            'floor' => '1',
            'room_code' => 'CV-'.Str::upper(Str::random(5)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(string $name = ''): Category
    {
        return Category::create([
            'name' => $name !== '' ? $name : 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de card v2',
        ]);
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
