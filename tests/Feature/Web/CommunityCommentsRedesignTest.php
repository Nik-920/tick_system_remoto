<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community Comments v2 Redesign — visual structure, role badges, avatar,
 * modern report/reply panels, composer, and privacy regression checks.
 *
 * Coverage:
 *   A) Section wrapper: comm-comments-v2, count header.
 *   B) Comment card: avatar, role badge, author label, time, body.
 *   C) Roles: reporter / maintenance / admin differentiation.
 *   D) Replies: comm-comment-v2--reply, comm-replies-v2 rail.
 *   E) Forms: report (preserved fields + v2 classes), reply (parent_id + v2 classes).
 *   F) Composer: comm-comments-composer, action, textarea, submit.
 *   G) Privacy: no PII, no reporter_id, no emails, no TicketComment bleed.
 *   H) XSS: body escaped.
 *   I) CSS: comm-comments-v2 present in compiled asset.
 */
class CommunityCommentsRedesignTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Section wrapper ────────────────────────────────────────────────────

    public function test_section_has_comm_comments_v2_class(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comments-v2', false);
    }

    public function test_count_badge_shown_when_there_are_comments(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comments-summary__count', false);
    }

    public function test_count_badge_not_shown_when_no_comments(): void
    {
        $viewer = $this->reporter();
        $this->visibleTicket();

        $response = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk();

        $this->assertStringNotContainsString('comm-comments-summary__count', $response->getContent());
    }

    // ── B. Comment card structure ─────────────────────────────────────────────

    public function test_comment_renders_comm_comment_v2_class(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comment-v2', false);
    }

    public function test_comment_renders_avatar_element(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comment-v2__avatar', false);
    }

    public function test_comment_renders_main_body_column(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer, 'Cuerpo del comentario v2 FBODY.');

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comment-v2__main', false)
            ->assertSee('Cuerpo del comentario v2 FBODY.', false);
    }

    public function test_comment_renders_role_badge(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comment-v2__role', false);
    }

    public function test_comment_body_is_xss_escaped(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer, '<script>alert("xss-v2")</script>');

        $response = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk();

        $response->assertDontSee('<script>alert("xss-v2")</script>', false);
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_owned_comment_shows_tu_label(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Tú', false);
    }

    public function test_other_comment_shows_real_display_name(): void
    {
        $viewer = $this->reporter();
        $other = $this->reporter();
        $other->update(['name' => 'Nombre', 'last_name' => 'Visible']);
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $other);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Nombre Visible', false);
    }

    // ── C. Role differentiation ───────────────────────────────────────────────

    public function test_reporter_comment_has_reporter_role_tone(): void
    {
        $viewer = $this->reporter();
        $commenter = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $commenter);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comment-v2__role--reporter', false)
            ->assertSee('Reporter', false);
    }

    public function test_maintenance_comment_has_maintenance_role_tone(): void
    {
        $viewer = $this->reporter();
        $tech = $this->createUserWithRole('maintenance');
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $tech);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comment-v2__avatar--maintenance', false)
            ->assertSee('comm-comment-v2__role--maintenance', false)
            ->assertSee('Maintenance', false);
    }

    public function test_admin_comment_has_admin_role_tone(): void
    {
        $viewer = $this->reporter();
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $admin);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comment-v2__avatar--admin', false)
            ->assertSee('comm-comment-v2__role--admin', false)
            ->assertSee('Admin', false);
    }

    public function test_super_admin_comment_maps_to_admin_tone(): void
    {
        $viewer = $this->reporter();
        $sadmin = $this->createUserWithRole('super_admin');
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $sadmin);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comment-v2__avatar--admin', false);
    }

    public function test_viewer_own_comment_shows_tu_initials(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer);

        $response = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk();

        $this->assertStringContainsString('TU', $response->getContent());
    }

    // ── D. Replies ────────────────────────────────────────────────────────────

    public function test_reply_renders_comm_comment_v2_reply_class(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $root = $this->makeComment($ticket, $viewer, 'Raíz v2 FRDROOT.');
        $this->makeReply($ticket, $root, $viewer, 'Respuesta v2 FRDREPLY.');

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comment-v2--reply', false)
            ->assertSee('Respuesta v2 FRDREPLY.', false);
    }

    public function test_replies_list_has_comm_replies_v2_class(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $root = $this->makeComment($ticket, $viewer);
        $this->makeReply($ticket, $root, $viewer);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-replies-v2', false);
    }

    // ── E. Forms: report and reply ────────────────────────────────────────────

    public function test_report_form_has_v2_panel_class(): void
    {
        $viewer = $this->reporter();
        $other = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $other);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comment-report-v2', false);
    }

    public function test_report_form_preserves_reason_select_and_note(): void
    {
        $viewer = $this->reporter();
        $other = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $other);

        $response = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('name="reason"', $html);
        $this->assertStringContainsString('name="note"', $html);
        $this->assertStringContainsString('Selecciona motivo', $html);
        $this->assertStringContainsString('Detalles adicionales', $html);
    }

    public function test_report_form_preserves_csrf_and_correct_action(): void
    {
        $viewer = $this->reporter();
        $other = $this->reporter();
        $ticket = $this->visibleTicket();
        $comment = $this->makeComment($ticket, $other);

        $response = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString(
            route('reporter.community.comment-reports.store', $comment),
            $html
        );
        $this->assertStringContainsString('_token', $html);
    }

    public function test_report_button_label_is_reportar(): void
    {
        $viewer = $this->reporter();
        $other = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $other);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Reportar', false);
    }

    public function test_report_form_has_enviar_reporte_submit(): void
    {
        $viewer = $this->reporter();
        $other = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $other);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Enviar reporte', false);
    }

    public function test_reply_form_preserves_parent_id_hidden_field(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $root = $this->makeComment($ticket, $viewer);

        $response = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('name="parent_id"', $html);
        $this->assertStringContainsString('value="'.$root->id.'"', $html);
    }

    public function test_reply_form_has_responder_label(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Responder', false);
    }

    public function test_reply_form_has_v2_wrap_class(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-reply-form-v2', false);
    }

    // ── F. Composer ───────────────────────────────────────────────────────────

    public function test_composer_has_comm_comments_composer_class(): void
    {
        $viewer = $this->reporter();
        $this->visibleTicket();

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-comments-composer', false);
    }

    public function test_composer_shows_hint_text(): void
    {
        $viewer = $this->reporter();
        $this->visibleTicket();

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Comenta información útil', false);
    }

    public function test_composer_has_comentar_submit_button(): void
    {
        $viewer = $this->reporter();
        $this->visibleTicket();

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Comentar', false);
    }

    public function test_composer_form_has_body_textarea(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();

        $response = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('name="body"', $html);
        $this->assertStringContainsString(
            route('reporter.community.comments.store', $ticket),
            $html
        );
    }

    // ── G. Privacy / no-leak ──────────────────────────────────────────────────

    public function test_does_not_expose_commenter_email(): void
    {
        $viewer = $this->reporter();
        $commenter = User::factory()->create(['email' => 'secret-commenter-v2@test.test']);
        $commenter->assignRole('reporter');
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $commenter);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('secret-commenter-v2@test.test', false);
    }

    public function test_shows_commenter_display_name(): void
    {
        $viewer = $this->reporter();
        $commenter = User::factory()->create(['name' => 'NombreVisibleComentadorV2']);
        $commenter->assignRole('reporter');
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $commenter);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('NombreVisibleComentadorV2', false);
    }

    public function test_does_not_expose_reporter_id_in_html(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $commenter = $this->reporter();
        $this->makeComment($ticket, $commenter);

        $response = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringNotContainsString('"reporter_id"', $html);
        $this->assertStringNotContainsString("'reporter_id'", $html);
    }

    public function test_hidden_comment_body_not_shown_in_redesign(): void
    {
        $viewer = $this->reporter();
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->visibleTicket();

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $viewer->id,
            'body' => 'Cuerpo oculto rediseño FSECRETBODYV2.',
            'status' => CommunityComment::STATUS_HIDDEN,
            'hidden_by' => $admin->id,
            'hidden_at' => now(),
            'hidden_reason' => 'Oculto en test rediseño.',
        ]);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Cuerpo oculto rediseño FSECRETBODYV2.', false);
    }

    // ── H. No @php / no debug markers ─────────────────────────────────────────

    public function test_no_php_echo_in_rendered_comment_html(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer);

        $response = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringNotContainsString('<?php', $html);
        $this->assertStringNotContainsString('<?=', $html);
        $this->assertStringNotContainsString('class="sf-dump"', $html);
        $this->assertStringNotContainsString('Sfdump', $html);
    }

    // ── I. CSS class presence in build ────────────────────────────────────────

    public function test_reporter_community_css_contains_comm_comments_v2(): void
    {
        $cssPath = base_path('resources/css/modules/reporter-community.css');
        $this->assertFileExists($cssPath);
        $this->assertStringContainsString('.comm-comments-v2', file_get_contents($cssPath));
    }

    public function test_reporter_community_css_contains_comm_comment_v2_avatar(): void
    {
        $cssPath = base_path('resources/css/modules/reporter-community.css');
        $this->assertStringContainsString('.comm-comment-v2__avatar', file_get_contents($cssPath));
    }

    public function test_reporter_community_css_contains_comm_comments_composer(): void
    {
        $cssPath = base_path('resources/css/modules/reporter-community.css');
        $this->assertStringContainsString('.comm-comments-composer', file_get_contents($cssPath));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function reporter(): User
    {
        return $this->createUserWithRole('reporter');
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

    private function visibleTicket(string $title = 'Ticket rediseño v2 CRDV2'): Ticket
    {
        $reporter = $this->reporter();

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de test rediseño v2.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeComment(
        Ticket $ticket,
        User $user,
        string $body = 'Comentario de prueba rediseño v2.',
    ): CommunityComment {
        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => $body,
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    private function makeReply(
        Ticket $ticket,
        CommunityComment $parent,
        User $user,
        string $body = 'Respuesta de prueba rediseño v2.',
    ): CommunityComment {
        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'parent_id' => $parent->id,
            'user_id' => $user->id,
            'body' => $body,
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Sala Redesign Test',
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'RD-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de redesign',
        ]);
    }
}
