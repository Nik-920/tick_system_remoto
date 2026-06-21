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
 * Community Comment Editing (v5) — secure editing of own visible comments.
 *
 * Coverage:
 *   A) Access / update: gates, ownership, comment/ticket visibility.
 *   B) Validation: body required, min, max.
 *   C) DB / UI: body updated, edited_at set, feed shows Editado, edit action gated.
 *   D) XSS / no-leak: body escaped, no PII, hidden_reason not exposed.
 *   E) Regression: delete, report, reactions/saves still work.
 */
class CommunityCommentEditingTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Access / update ────────────────────────────────────────────────────

    public function test_guest_cannot_update_comment(): void
    {
        $comment = $this->makeVisibleComment();

        $this->patch(route('reporter.community.comments.update', $comment), [
            'body' => 'Intento de edición anónima.',
        ])->assertRedirect(route('login'));
    }

    public function test_reporter_can_update_own_visible_comment_on_visible_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Comentario editado correctamente.',
            ])
            ->assertRedirect();

        $comment->refresh();
        $this->assertSame('Comentario editado correctamente.', $comment->body);
        $this->assertNotNull($comment->edited_at);
    }

    public function test_reporter_cannot_update_another_users_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $other);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Intento de editar comentario ajeno.',
            ])
            ->assertForbidden();

        $comment->refresh();
        $this->assertNotSame('Intento de editar comentario ajeno.', $comment->body);
        $this->assertNull($comment->edited_at);
    }

    public function test_reporter_cannot_update_hidden_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario oculto por admin EDITOCULTO.',
            'status' => CommunityComment::STATUS_HIDDEN,
            'hidden_by' => $admin->id,
            'hidden_at' => now(),
            'hidden_reason' => 'Moderación.',
        ]);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Intento de editar comentario oculto.',
            ])
            ->assertNotFound();

        $comment->refresh();
        $this->assertSame(CommunityComment::STATUS_HIDDEN, $comment->status);
        $this->assertNull($comment->edited_at);
    }

    public function test_reporter_cannot_update_deleted_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario eliminado EDITDELETE.',
            'status' => CommunityComment::STATUS_DELETED,
            'deleted_at' => now(),
        ]);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Intento de editar comentario eliminado.',
            ])
            ->assertNotFound();

        $comment->refresh();
        $this->assertNull($comment->edited_at);
    }

    public function test_reporter_cannot_update_comment_if_parent_ticket_hidden(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario en ticket que se ocultará EDITTICKHIDDEN.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $ticket->forceFill([
            'community_visible' => false,
            'community_hidden_at' => now(),
        ])->save();

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Editar en ticket oculto.',
            ])
            ->assertNotFound();

        $comment->refresh();
        $this->assertNull($comment->edited_at);
    }

    public function test_reporter_cannot_update_comment_if_parent_ticket_cancelled(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(state: Ticket::STATE_CANCELLED);

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario en ticket cancelado EDITCANCEL.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Editar en ticket cancelado.',
            ])
            ->assertNotFound();

        $comment->refresh();
        $this->assertNull($comment->edited_at);
    }

    public function test_reporter_cannot_update_comment_if_parent_ticket_rejected(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(state: Ticket::STATE_REJECTED);

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario en ticket rechazado EDITREJECT.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Editar en ticket rechazado.',
            ])
            ->assertNotFound();

        $comment->refresh();
        $this->assertNull($comment->edited_at);
    }

    public function test_admin_cannot_use_reporter_update_route(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter);

        $this->actingAs($admin)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Admin intenta editar por ruta reporter.',
            ])
            ->assertForbidden();
    }

    public function test_maintenance_cannot_use_reporter_update_route(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter);

        $this->actingAs($maintenance)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Maintenance intenta editar por ruta reporter.',
            ])
            ->assertForbidden();
    }

    // ── B. Validation ─────────────────────────────────────────────────────────

    public function test_body_required(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => '',
            ])
            ->assertSessionHasErrors('body');

        $comment->refresh();
        $this->assertNull($comment->edited_at);
    }

    public function test_body_min_length_validated(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'x',
            ])
            ->assertSessionHasErrors('body');

        $comment->refresh();
        $this->assertNull($comment->edited_at);
    }

    public function test_body_max_500_validated(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('body');

        $comment->refresh();
        $this->assertNull($comment->edited_at);
    }

    // ── C. DB / UI ────────────────────────────────────────────────────────────

    public function test_updating_comment_changes_body(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter, body: 'Cuerpo original DBEDIT.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Cuerpo actualizado DBEDIT.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_comments', [
            'id' => $comment->id,
            'body' => 'Cuerpo actualizado DBEDIT.',
        ]);
    }

    public function test_updating_comment_sets_edited_at(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter);

        $this->assertNull($comment->edited_at);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Comentario ahora editado EDITTIME.',
            ])
            ->assertRedirect();

        $comment->refresh();
        $this->assertNotNull($comment->edited_at);
    }

    public function test_feed_shows_updated_body(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter, body: 'Cuerpo antes de editar FEEDBODY.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Cuerpo actualizado visible en feed FEEDBODY.',
            ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Cuerpo actualizado visible en feed FEEDBODY.', false);
    }

    public function test_feed_shows_editado_badge_after_edit(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter, body: 'Cuerpo para badge FEEDEDITADO.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Cuerpo editado para mostrar badge FEEDEDITADO.',
            ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Editado', false);
    }

    public function test_feed_shows_edit_action_only_for_own_comments(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeVisibleComment(user: $reporter, body: 'Mi comentario para editar FEEDOWN.');

        // "Guardar cambios" only appears inside the edit form shown for own comments
        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Guardar cambios', false);
    }

    public function test_feed_does_not_show_edit_action_for_others_comments(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $this->makeVisibleComment(user: $other, body: 'Comentario ajeno FEEDOTHER.');

        // "Guardar cambios" must not appear when the viewer doesn't own the comment
        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Guardar cambios', false);
    }

    // ── D. XSS / no-leak ─────────────────────────────────────────────────────

    public function test_edited_body_with_script_is_escaped(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => '<script>alert("xss")</script>',
            ]);

        $response = $this->actingAs($reporter)->get(route('reporter.community'));
        $response->assertOk();
        $response->assertDontSee('<script>alert("xss")</script>', false);
        $response->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    }

    public function test_edited_comment_does_not_expose_user_email_or_name(): void
    {
        $this->ensureRolesExist();

        // Author with a distinctive name edits their comment
        $author = User::factory()->create([
            'name' => 'NombreSecreto XSS EDITPII',
            'email' => 'editpii-secret@test.test',
        ]);
        $author->assignRole('reporter');

        $ticket = $this->makeVisibleTicket();
        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'body' => 'Comentario original PII.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($author)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Comentario editado PII.',
            ]);

        // A different reporter views the feed — must not see the author's PII
        $viewer = $this->createUserWithRole('reporter');

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('NombreSecreto XSS EDITPII', false)
            ->assertDontSee('editpii-secret@test.test', false);
    }

    public function test_hidden_reason_not_exposed_after_edit(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket hidden reason edit EDITREASON');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario con motivo secreto EDITREASON.',
            'status' => CommunityComment::STATUS_HIDDEN,
            'hidden_by' => $admin->id,
            'hidden_at' => now(),
            'hidden_reason' => 'Motivo secreto moderación EDITSECRET.',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Motivo secreto moderación EDITSECRET.', false);
    }

    // ── E. Regression ─────────────────────────────────────────────────────────

    public function test_deleting_own_comment_still_works_after_edit(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter, body: 'Comentario a eliminar tras editar REGDELETE.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Editado antes de eliminar REGDELETE.',
            ]);

        $this->actingAs($reporter)
            ->delete(route('reporter.community.comments.destroy', $comment))
            ->assertRedirect();

        $comment->refresh();
        $this->assertSame(CommunityComment::STATUS_DELETED, $comment->status);
        $this->assertNotNull($comment->deleted_at);
    }

    public function test_reporting_edited_comment_still_works(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $other, body: 'Comentario editable que otro reporta REGREPORT.');

        // Edit by owner
        $this->actingAs($other)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Editado antes de reportar REGREPORT.',
            ]);

        // Report by viewer
        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => 'incorrect_info',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', [
            'comment_id' => $comment->id,
            'reported_by' => $reporter->id,
        ]);
    }

    public function test_reactions_and_saves_unaffected_by_comment_edit(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter);
        $ticket = Ticket::find($comment->ticket_id);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Editado sin afectar reacciones REGRCT.',
            ]);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), [
                'type' => 'interested',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reactions', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleComment(
        ?User $user = null,
        string $body = 'Comentario de test para edición.',
        string $ticketTitle = 'Ticket editable CEDIT',
    ): CommunityComment {
        $user ??= $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: $ticketTitle);

        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => $body,
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    private function makeVisibleTicket(
        string $title = 'Ticket visible comunidad CEDIT',
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        $reporter = $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de test para edición de comentarios.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
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
            'name' => 'Sala Edit Test',
            'building' => 'Edificio Edit',
            'floor' => '1',
            'room_code' => 'CE-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Categoría Edit Test '.Str::random(4),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de edición de comentarios.',
        ]);
    }
}
