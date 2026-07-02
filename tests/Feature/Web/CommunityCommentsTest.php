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
 * Community Comments (v3) — secure comments on community-visible tickets.
 *
 * Coverage:
 *   A) Access / creation: gates, visibility, validation.
 *   B) Feed UI: count, latest comments, form, generic author, XSS escaping.
 *   C) Deletion by reporter: own vs others.
 *   D) Admin moderation: hide/restore, role gates, hidden reason not in feed.
 *   E) Integration: hidden ticket, reactions/saves, reports still work.
 *   F) No-leak: no PII, no hidden body, no admin reason in reporter feed.
 */
class CommunityCommentsTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Access / creation ──────────────────────────────────────────────────

    public function test_guest_cannot_comment(): void
    {
        $ticket = $this->makeVisibleTicket();

        $this->post(route('reporter.community.comments.store', $ticket), [
            'body' => 'Comentario de invitado.',
        ])->assertRedirect(route('login'));
    }

    public function test_reporter_can_comment_on_visible_community_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario de prueba válido.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario de prueba válido.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    public function test_reporter_cannot_comment_on_hidden_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeHiddenTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario en ticket oculto.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_comments', ['ticket_id' => $ticket->id]);
    }

    public function test_reporter_cannot_comment_on_cancelled_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(state: Ticket::STATE_CANCELLED);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario en ticket cancelado.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_comments', ['ticket_id' => $ticket->id]);
    }

    public function test_reporter_cannot_comment_on_rejected_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(state: Ticket::STATE_REJECTED);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario en ticket rechazado.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_comments', ['ticket_id' => $ticket->id]);
    }

    public function test_maintenance_cannot_use_reporter_comment_route(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($maintenance)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario de maintenance.',
            ])
            ->assertForbidden();
    }

    public function test_admin_cannot_use_reporter_comment_route(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario de admin por ruta reporter.',
            ])
            ->assertForbidden();
    }

    public function test_body_is_required(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseMissing('community_comments', ['ticket_id' => $ticket->id]);
    }

    public function test_body_max_500_validated(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_body_min_2_validated(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'x',
            ])
            ->assertSessionHasErrors('body');
    }

    // ── B. Feed UI ────────────────────────────────────────────────────────────

    public function test_feed_shows_comment_count(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket con comentarios FCOUNT');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Primer comentario visible.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Segundo comentario visible.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Comentarios', false);
    }

    public function test_feed_shows_latest_visible_comments(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket con comentarios visibles FLATEST');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Aporte visible de la comunidad FVISIBLE.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Aporte visible de la comunidad FVISIBLE.', false);
    }

    public function test_feed_shows_comment_form(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeVisibleTicket(title: 'Ticket form comentario FFORM');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Comentar', false);
    }

    public function test_feed_shows_delete_control_for_own_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket delete control FDELETE');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Mi comentario propio FOWN.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Eliminar', false);
    }

    public function test_feed_does_not_show_hidden_comments(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket hidden comment FHIDDEN');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Este comentario está oculto FHIDDENBODY.',
            'status' => CommunityComment::STATUS_HIDDEN,
            'hidden_by' => $admin->id,
            'hidden_at' => now(),
            'hidden_reason' => 'Contenido inapropiado.',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Este comentario está oculto FHIDDENBODY.', false);
    }

    public function test_feed_does_not_show_deleted_comments(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket deleted comment FDELETED');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario eliminado por reporter FDELBODY.',
            'status' => CommunityComment::STATUS_DELETED,
            'deleted_at' => now(),
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Comentario eliminado por reporter FDELBODY.', false);
    }

    public function test_feed_shows_commenter_display_name_and_role(): void
    {
        $this->ensureRolesExist();
        $author = User::factory()->create([
            'name' => 'NombreVisibleAutor',
            'email' => 'autor-visible@test.test',
        ]);
        $author->assignRole('reporter');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket con nombre visible FNAME');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'body' => 'Comentario de autor visible FAUTHOR.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('NombreVisibleAutor', false)
            ->assertDontSee('autor-visible@test.test', false)
            ->assertSee('Reporter', false);
    }

    public function test_xss_comment_body_is_escaped(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket XSS FXSS');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => '<script>alert(1)</script>',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    // ── C. Deletion by reporter ───────────────────────────────────────────────

    public function test_reporter_can_delete_own_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario a eliminar propio.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->delete(route('reporter.community.comments.destroy', $comment))
            ->assertRedirect();

        $comment->refresh();
        $this->assertSame(CommunityComment::STATUS_DELETED, $comment->status);
        $this->assertNotNull($comment->deleted_at);
    }

    public function test_reporter_cannot_delete_another_users_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $other->id,
            'body' => 'Comentario de otro reporter.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->delete(route('reporter.community.comments.destroy', $comment))
            ->assertForbidden();

        $comment->refresh();
        $this->assertSame(CommunityComment::STATUS_VISIBLE, $comment->status);
    }

    public function test_deleted_comment_disappears_from_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket comentario desaparece CDISAPPEAR');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario que desaparecerá CDISAPPEAR.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->delete(route('reporter.community.comments.destroy', $comment))
            ->assertRedirect();

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Comentario que desaparecerá CDISAPPEAR.', false);
    }

    // ── D. Admin moderation ───────────────────────────────────────────────────

    public function test_admin_can_hide_a_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario a ocultar por admin.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.comments.hide', $comment), [
                'reason' => 'Contenido inapropiado para la comunidad.',
            ])
            ->assertRedirect();

        $comment->refresh();
        $this->assertSame(CommunityComment::STATUS_HIDDEN, $comment->status);
        $this->assertSame($admin->id, $comment->hidden_by);
        $this->assertNotNull($comment->hidden_at);
        $this->assertSame('Contenido inapropiado para la comunidad.', $comment->hidden_reason);
    }

    public function test_admin_can_restore_a_hidden_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario a restaurar.',
            'status' => CommunityComment::STATUS_HIDDEN,
            'hidden_by' => $admin->id,
            'hidden_at' => now(),
            'hidden_reason' => 'Ocultado temporalmente.',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.comments.restore', $comment))
            ->assertRedirect();

        $comment->refresh();
        $this->assertSame(CommunityComment::STATUS_VISIBLE, $comment->status);
        $this->assertNull($comment->hidden_by);
        $this->assertNull($comment->hidden_at);
        $this->assertNull($comment->hidden_reason);
    }

    public function test_reporter_cannot_hide_comment_via_admin_route(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario que no puede ocultar reporter.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->patch(route('admin.community.comments.hide', $comment), [
                'reason' => 'Intento de ocultar ajeno.',
            ])
            ->assertForbidden();

        $comment->refresh();
        $this->assertSame(CommunityComment::STATUS_VISIBLE, $comment->status);
    }

    public function test_maintenance_cannot_hide_comment_via_admin_route(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario que maintenance no puede ocultar.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($maintenance)
            ->patch(route('admin.community.comments.hide', $comment), [
                'reason' => 'Intento de ocultar por maintenance.',
            ])
            ->assertForbidden();
    }

    public function test_hidden_reason_is_not_visible_in_reporter_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket hidden reason FADMINREASON');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Contenido del comentario oculto FADMINREASON.',
            'status' => CommunityComment::STATUS_HIDDEN,
            'hidden_by' => $admin->id,
            'hidden_at' => now(),
            'hidden_reason' => 'Motivo secreto de moderación de comentario SECRETREASON.',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Motivo secreto de moderación de comentario SECRETREASON.', false);
    }

    // ── E. Integration ────────────────────────────────────────────────────────

    public function test_hidden_ticket_stops_accepting_new_comments(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket se oculta luego EINTEGRATE');

        $ticket->forceFill([
            'community_visible' => false,
            'community_hidden_at' => now(),
        ])->save();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario en ticket recién ocultado.',
            ])
            ->assertNotFound();
    }

    public function test_comments_remain_stored_after_ticket_hidden(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket ocultar con comentarios ESTORE');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario antes de ocultar.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), [
                'reason' => 'Ocultado con comentarios existentes.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_comments', [
            'id' => $comment->id,
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    public function test_reactions_still_work_with_comments_present(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket reacciones con comentarios EREACT');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario coexistente con reacción.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), [
                'type' => 'interested',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reactions', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'interested',
        ]);
    }

    public function test_reports_still_work_with_comments_present(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket reportes con comentarios EREPORT');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario coexistente con reporte.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => 'other',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', [
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
        ]);
    }

    // ── F. No-leak ────────────────────────────────────────────────────────────

    public function test_reporter_community_does_not_expose_ticket_reporter_pii(): void
    {
        $this->ensureRolesExist();
        $secret = User::factory()->create([
            'name' => 'ReporterOriginalSecretoPII',
            'email' => 'secret-pii@test.test',
        ]);
        $secret->assignRole('reporter');
        $ticket = Ticket::create([
            'title' => 'Ticket PII con comentarios FPII',
            'description' => 'Descripción segura.',
            'reporter_id' => $secret->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);

        $viewer = $this->createUserWithRole('reporter');

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('ReporterOriginalSecretoPII', false)
            ->assertDontSee('secret-pii@test.test', false);
    }

    public function test_hidden_comment_body_not_visible_in_reporter_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket hidden body FHIDBODY');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Cuerpo del comentario oculto SECRETBODY.',
            'status' => CommunityComment::STATUS_HIDDEN,
            'hidden_by' => $admin->id,
            'hidden_at' => now(),
            'hidden_reason' => 'Inapropiado.',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Cuerpo del comentario oculto SECRETBODY.', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleTicket(
        string $title = 'Ticket visible comunidad COMMENT',
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        $reporter = $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de test para comentarios comunitarios.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeHiddenTicket(): Ticket
    {
        $reporter = $this->createUserWithRole('reporter');

        $ticket = Ticket::create([
            'title' => 'Ticket oculto COMMENT',
            'description' => 'Ticket no visible en comunidad.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => false,
        ]);

        $ticket->forceFill([
            'community_hidden_at' => now(),
            'community_hidden_by' => $reporter->id,
            'community_visibility_reason' => 'Ocultado para test de comentarios.',
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

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Sala Comments Test',
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'CM-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de comentarios comunitarios',
        ]);
    }
}
