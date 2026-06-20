<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\CommunityCommentEditLog;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community Comment Edit Audit Log — append-only history of comment edits.
 *
 * Coverage:
 *   A) Log creation: real edits create logs with previous/new body.
 *   B) Append-only: multiple edits create multiple logs.
 *   C) No-op: identical body creates no log and leaves edited_at untouched.
 *   D) No logs on unauthorized/invalid edits (ownership, hidden, deleted, ticket state).
 *   E) Admin UI: history shown on ticket show, bodies escaped.
 *   F) No-leak: reporter feed never exposes previous_body nor editor PII.
 */
class CommunityCommentEditAuditLogTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Log creation ───────────────────────────────────────────────────────

    public function test_editing_own_comment_creates_an_edit_log(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter, body: 'Cuerpo original LOGCREATE.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Cuerpo editado LOGCREATE.',
            ])
            ->assertRedirect();

        $this->assertSame(1, CommunityCommentEditLog::query()->where('comment_id', $comment->id)->count());
    }

    public function test_edit_log_stores_expected_fields(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter, body: 'Antes FIELDS.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Después FIELDS.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_comment_edit_logs', [
            'comment_id' => $comment->id,
            'ticket_id' => $comment->ticket_id,
            'edited_by' => $reporter->id,
            'previous_body' => 'Antes FIELDS.',
            'new_body' => 'Después FIELDS.',
        ]);
    }

    public function test_editing_still_updates_edited_at_on_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter);

        $this->assertNull($comment->edited_at);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Comentario editado EDITEDAT.',
            ])
            ->assertRedirect();

        $comment->refresh();
        $this->assertNotNull($comment->edited_at);
    }

    // ── B. Append-only ────────────────────────────────────────────────────────

    public function test_multiple_edits_create_multiple_append_only_logs(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter, body: 'Versión 0 APPEND.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), ['body' => 'Versión 1 APPEND.'])
            ->assertRedirect();

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), ['body' => 'Versión 2 APPEND.'])
            ->assertRedirect();

        $logs = CommunityCommentEditLog::query()
            ->where('comment_id', $comment->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $logs);
        $this->assertSame('Versión 0 APPEND.', $logs[0]->previous_body);
        $this->assertSame('Versión 1 APPEND.', $logs[0]->new_body);
        $this->assertSame('Versión 1 APPEND.', $logs[1]->previous_body);
        $this->assertSame('Versión 2 APPEND.', $logs[1]->new_body);
    }

    // ── C. No-op (Option A) ────────────────────────────────────────────────────

    public function test_unchanged_body_creates_no_log_and_leaves_edited_at_null(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter, body: 'Cuerpo sin cambios NOOP.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Cuerpo sin cambios NOOP.',
            ])
            ->assertRedirect();

        $comment->refresh();
        $this->assertNull($comment->edited_at);
        $this->assertSame(0, CommunityCommentEditLog::query()->where('comment_id', $comment->id)->count());
    }

    // ── D. No logs on unauthorized / invalid edits ─────────────────────────────

    public function test_editing_another_users_comment_creates_no_log(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $other, body: 'Comentario ajeno NOLOGOWN.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Intento ajeno NOLOGOWN.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('community_comment_edit_logs', 0);
    }

    public function test_editing_hidden_comment_creates_no_log(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario oculto NOLOGHIDDEN.',
            'status' => CommunityComment::STATUS_HIDDEN,
            'hidden_by' => $admin->id,
            'hidden_at' => now(),
            'hidden_reason' => 'Moderación.',
        ]);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Intento editar oculto NOLOGHIDDEN.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('community_comment_edit_logs', 0);
    }

    public function test_editing_deleted_comment_creates_no_log(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario eliminado NOLOGDEL.',
            'status' => CommunityComment::STATUS_DELETED,
            'deleted_at' => now(),
        ]);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Intento editar eliminado NOLOGDEL.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('community_comment_edit_logs', 0);
    }

    public function test_editing_comment_on_hidden_ticket_creates_no_log(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario en ticket por ocultar NOLOGTHIDDEN.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $ticket->forceFill([
            'community_visible' => false,
            'community_hidden_at' => now(),
        ])->save();

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Editar en ticket oculto NOLOGTHIDDEN.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('community_comment_edit_logs', 0);
    }

    public function test_editing_comment_on_cancelled_ticket_creates_no_log(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(state: Ticket::STATE_CANCELLED);

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario en ticket cancelado NOLOGCANCEL.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Editar en ticket cancelado NOLOGCANCEL.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('community_comment_edit_logs', 0);
    }

    public function test_editing_comment_on_rejected_ticket_creates_no_log(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(state: Ticket::STATE_REJECTED);

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario en ticket rechazado NOLOGREJECT.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Editar en ticket rechazado NOLOGREJECT.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('community_comment_edit_logs', 0);
    }

    public function test_invalid_body_creates_no_log(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter, body: 'Cuerpo válido INVALID.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => '',
            ])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('community_comment_edit_logs', 0);
    }

    // ── E. Admin UI ────────────────────────────────────────────────────────────

    public function test_admin_sees_comment_edit_history_in_ticket_show(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket historial ediciones ADMINUI');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Cuerpo previo visible admin ADMINBEFORE.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Cuerpo nuevo visible admin ADMINAFTER.',
            ]);

        $this->actingAs($admin)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Historial de ediciones de comentarios', false)
            ->assertSee('ADMINBEFORE', false)
            ->assertSee('ADMINAFTER', false);
    }

    public function test_admin_sees_empty_message_when_no_edit_logs(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Sin ediciones de comentarios registradas', false);
    }

    public function test_admin_ui_escapes_previous_body_script(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => '<script>alert("prev")</script>',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Cuerpo nuevo seguro PREVXSS.',
            ]);

        $this->actingAs($admin)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('<script>alert("prev")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;prev&quot;)&lt;/script&gt;', false);
    }

    public function test_admin_ui_escapes_new_body_script(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $comment = $this->makeVisibleComment(user: $reporter, body: 'Cuerpo inicial seguro NEWXSS.', ticketTitle: 'Ticket new xss');
        $ticket = Ticket::find($comment->ticket_id);

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => '<script>alert("new")</script>',
            ]);

        $this->actingAs($admin)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('<script>alert("new")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;new&quot;)&lt;/script&gt;', false);
    }

    // ── F. No-leak (reporter feed) ─────────────────────────────────────────────

    public function test_reporter_feed_does_not_show_edit_history_section(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter, body: 'Cuerpo feed sin historial FEEDNOHIST.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Editado feed sin historial FEEDNOHIST.',
            ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Historial de ediciones de comentarios', false)
            ->assertDontSee('community_comment_edit_logs', false);
    }

    public function test_reporter_feed_shows_current_body_and_editado_badge_only(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment(user: $reporter, body: 'Cuerpo previo NO debe verse FEEDPREV.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Cuerpo actual visible FEEDCURR.',
            ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Cuerpo actual visible FEEDCURR.', false)
            ->assertSee('Editado', false)
            ->assertDontSee('Cuerpo previo NO debe verse FEEDPREV.', false);
    }

    public function test_reporter_feed_does_not_expose_editor_pii(): void
    {
        $this->ensureRolesExist();

        $author = User::factory()->create([
            'name' => 'EditorSecreto AUDITPII',
            'email' => 'auditpii-secret@test.test',
        ]);
        $author->assignRole('reporter');

        $ticket = $this->makeVisibleTicket();
        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'body' => 'Comentario original AUDITPII.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($author)
            ->patch(route('reporter.community.comments.update', $comment), [
                'body' => 'Comentario editado AUDITPII.',
            ]);

        $viewer = $this->createUserWithRole('reporter');

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('EditorSecreto AUDITPII', false)
            ->assertDontSee('auditpii-secret@test.test', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleComment(
        ?User $user = null,
        string $body = 'Comentario de test para auditoría de edición.',
        string $ticketTitle = 'Ticket auditable CAUDIT',
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
        string $title = 'Ticket visible comunidad CAUDIT',
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        $reporter = $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de test para auditoría de edición de comentarios.',
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
            'name' => 'Sala Audit Test',
            'building' => 'Edificio Audit',
            'floor' => '1',
            'room_code' => 'CA-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Categoría Audit Test '.Str::random(4),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de auditoría de edición de comentarios.',
        ]);
    }
}
