<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\CommunityCommentEditLog;
use App\Models\CommunityReport;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community Replies — one-level nested replies on community comments.
 *
 * Reuses the existing CommunityComment model/routes via a nullable parent_id.
 *
 * Coverage:
 *   A) Creation: gates, parent validation, depth cap, ticket/visibility rules.
 *   B) Feed UI: replies rendered under root, generic author, count, XSS, no PII.
 *   C) Existing actions on replies: edit/delete/report/hide/restore.
 *   D) Regression: root comments, edit audit log, notifications, reactions/saves/reports.
 */
class CommunityRepliesTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Creation ───────────────────────────────────────────────────────────

    public function test_guest_cannot_reply(): void
    {
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);

        $this->post(route('reporter.community.comments.store', $ticket), [
            'body' => 'Respuesta de invitado.',
            'parent_id' => $root->id,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('community_comments', ['parent_id' => $root->id]);
    }

    public function test_reporter_can_reply_to_visible_root_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Respuesta útil y válida.',
                'parent_id' => $root->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_comments', [
            'ticket_id' => $ticket->id,
            'parent_id' => $root->id,
            'user_id' => $reporter->id,
            'body' => 'Respuesta útil y válida.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    public function test_reporter_cannot_reply_to_hidden_root_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket, status: CommunityComment::STATUS_HIDDEN, hiddenBy: $admin);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Respuesta a comentario oculto.',
                'parent_id' => $root->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_comments', ['parent_id' => $root->id]);
    }

    public function test_reporter_cannot_reply_to_deleted_root_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket, status: CommunityComment::STATUS_DELETED);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Respuesta a comentario eliminado.',
                'parent_id' => $root->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_comments', ['parent_id' => $root->id]);
    }

    public function test_reporter_cannot_reply_using_mismatched_route_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticketA = $this->makeVisibleTicket(title: 'Ticket A REPLYMISMATCH');
        $ticketB = $this->makeVisibleTicket(title: 'Ticket B REPLYMISMATCH');
        $rootOnA = $this->makeRootComment($ticketA);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticketB), [
                'body' => 'Respuesta con ticket equivocado.',
                'parent_id' => $rootOnA->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_comments', [
            'body' => 'Respuesta con ticket equivocado.',
        ]);
    }

    public function test_reporter_cannot_reply_to_a_reply(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);
        $reply = $this->makeReply($ticket, $root);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Respuesta a una respuesta (no permitido).',
                'parent_id' => $reply->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_comments', ['parent_id' => $reply->id]);
    }

    public function test_reporter_cannot_reply_when_ticket_hidden(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);

        $ticket->forceFill([
            'community_visible' => false,
            'community_hidden_at' => now(),
        ])->save();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Respuesta en ticket oculto.',
                'parent_id' => $root->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_comments', ['parent_id' => $root->id]);
    }

    public function test_reporter_cannot_reply_when_ticket_cancelled(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);

        $ticket->forceFill(['state' => Ticket::STATE_CANCELLED])->save();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Respuesta en ticket cancelado.',
                'parent_id' => $root->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_comments', ['parent_id' => $root->id]);
    }

    public function test_reporter_cannot_reply_when_ticket_rejected(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);

        $ticket->forceFill(['state' => Ticket::STATE_REJECTED])->save();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Respuesta en ticket rechazado.',
                'parent_id' => $root->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_comments', ['parent_id' => $root->id]);
    }

    public function test_body_validation_applies_to_replies(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => '',
                'parent_id' => $root->id,
            ])
            ->assertSessionHasErrors('body');

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => str_repeat('a', 501),
                'parent_id' => $root->id,
            ])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseMissing('community_comments', ['parent_id' => $root->id]);
    }

    public function test_nonexistent_parent_id_is_rejected(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Respuesta a padre inexistente.',
                'parent_id' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('community_comments', [
            'body' => 'Respuesta a padre inexistente.',
        ]);
    }

    // ── B. Feed UI ────────────────────────────────────────────────────────────

    public function test_feed_shows_replies_under_root_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket replies feed FREPLIES');
        $root = $this->makeRootComment($ticket, body: 'Comentario raíz visible FROOT.');
        $this->makeReply($ticket, $root, body: 'Respuesta visible bajo raíz FREPLY.');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Comentario raíz visible FROOT.', false)
            ->assertSee('Respuesta visible bajo raíz FREPLY.', false);
    }

    public function test_feed_replies_use_generic_author_labels(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket reply labels FLABEL');
        $root = $this->makeRootComment($ticket, user: $other);
        $this->makeReply($ticket, $root, user: $viewer, body: 'Mi respuesta propia FMINE.');
        $this->makeReply($ticket, $root, user: $other, body: 'Respuesta de otro FOTHER.');

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Tú', false)
            ->assertSee('Reporter de la comunidad', false);
    }

    public function test_feed_does_not_show_replies_for_hidden_parent(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket hidden parent FHIDPARENT');
        $root = $this->makeRootComment($ticket, status: CommunityComment::STATUS_HIDDEN, hiddenBy: $admin);
        $this->makeReply($ticket, $root, body: 'Respuesta de padre oculto FHIDREPLY.');

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Respuesta de padre oculto FHIDREPLY.', false);
    }

    public function test_feed_does_not_show_replies_for_deleted_parent(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket deleted parent FDELPARENT');
        $root = $this->makeRootComment($ticket, status: CommunityComment::STATUS_DELETED);
        $this->makeReply($ticket, $root, body: 'Respuesta de padre eliminado FDELREPLY.');

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Respuesta de padre eliminado FDELREPLY.', false);
    }

    public function test_feed_count_includes_replies(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket count includes replies FCOUNT');
        $root = $this->makeRootComment($ticket);
        $this->makeReply($ticket, $root);
        $this->makeReply($ticket, $root);

        // 1 root + 2 replies = 3 visible comments.
        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('>3</span>', false);
    }

    public function test_feed_reply_body_is_escaped_against_xss(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket reply xss FRXSS');
        $root = $this->makeRootComment($ticket);
        $this->makeReply($ticket, $root, body: '<script>alert("reply")</script>');

        $response = $this->actingAs($viewer)->get(route('reporter.community'));

        $response->assertOk();
        $response->assertDontSee('<script>alert("reply")</script>', false);
        $response->assertSee('&lt;script&gt;alert(&quot;reply&quot;)&lt;/script&gt;', false);
    }

    public function test_feed_replies_do_not_expose_pii(): void
    {
        $this->ensureRolesExist();
        $secret = User::factory()->create([
            'name' => 'AutorRespuestaSecretoPII',
            'email' => 'reply-secret@test.test',
        ]);
        $secret->assignRole('reporter');
        $viewer = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket reply pii FRPII');
        $root = $this->makeRootComment($ticket);
        $this->makeReply($ticket, $root, user: $secret, body: 'Respuesta anónima FRPIIBODY.');

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Respuesta anónima FRPIIBODY.', false)
            ->assertDontSee('AutorRespuestaSecretoPII', false)
            ->assertDontSee('reply-secret@test.test', false);
    }

    // ── C. Existing actions on replies ────────────────────────────────────────

    public function test_reporter_can_edit_own_reply(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);
        $reply = $this->makeReply($ticket, $root, user: $reporter, body: 'Respuesta original EDIT.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $reply), [
                'body' => 'Respuesta editada EDIT.',
            ])
            ->assertRedirect();

        $reply->refresh();
        $this->assertSame('Respuesta editada EDIT.', $reply->body);
        $this->assertNotNull($reply->edited_at);
    }

    public function test_reporter_can_delete_own_reply(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);
        $reply = $this->makeReply($ticket, $root, user: $reporter);

        $this->actingAs($reporter)
            ->delete(route('reporter.community.comments.destroy', $reply))
            ->assertRedirect();

        $reply->refresh();
        $this->assertSame(CommunityComment::STATUS_DELETED, $reply->status);
        $this->assertNotNull($reply->deleted_at);
    }

    public function test_reporter_cannot_edit_or_delete_another_users_reply(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);
        $reply = $this->makeReply($ticket, $root, user: $other, body: 'Respuesta ajena.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $reply), [
                'body' => 'Intento de editar respuesta ajena.',
            ])
            ->assertForbidden();

        $this->actingAs($reporter)
            ->delete(route('reporter.community.comments.destroy', $reply))
            ->assertForbidden();

        $reply->refresh();
        $this->assertSame('Respuesta ajena.', $reply->body);
        $this->assertSame(CommunityComment::STATUS_VISIBLE, $reply->status);
    }

    public function test_reporter_can_report_another_users_reply(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);
        $reply = $this->makeReply($ticket, $root, user: $other);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $reply), [
                'reason' => 'other',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', [
            'ticket_id' => $ticket->id,
            'comment_id' => $reply->id,
            'reported_by' => $reporter->id,
            'status' => CommunityReport::STATUS_PENDING,
        ]);
    }

    public function test_feed_shows_reported_badge_for_reported_reply(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket reported reply FREPORTED');
        $root = $this->makeRootComment($ticket);
        $reply = $this->makeReply($ticket, $root, user: $other, body: 'Respuesta reportada FRBODY.');

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $reply), [
                'reason' => 'other',
            ])
            ->assertRedirect();

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Comentario reportado', false);
    }

    public function test_admin_can_hide_and_restore_a_reply(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);
        $reply = $this->makeReply($ticket, $root);

        $this->actingAs($admin)
            ->patch(route('admin.community.comments.hide', $reply), [
                'reason' => 'Respuesta inapropiada.',
            ])
            ->assertRedirect();

        $reply->refresh();
        $this->assertSame(CommunityComment::STATUS_HIDDEN, $reply->status);
        $this->assertSame($admin->id, $reply->hidden_by);

        $this->actingAs($admin)
            ->patch(route('admin.community.comments.restore', $reply))
            ->assertRedirect();

        $reply->refresh();
        $this->assertSame(CommunityComment::STATUS_VISIBLE, $reply->status);
        $this->assertNull($reply->hidden_by);
    }

    public function test_hidden_reply_disappears_from_feed(): void
    {
        $admin = $this->createUserWithRole('admin');
        $viewer = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket hidden reply FHIDDENREPLY');
        $root = $this->makeRootComment($ticket);
        $reply = $this->makeReply($ticket, $root, body: 'Respuesta a ocultar FHIDEME.');

        $this->actingAs($admin)
            ->patch(route('admin.community.comments.hide', $reply), [
                'reason' => 'Oculta para test.',
            ])
            ->assertRedirect();

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Respuesta a ocultar FHIDEME.', false);
    }

    // ── D. Regression ─────────────────────────────────────────────────────────

    public function test_root_comments_still_work_without_parent_id(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario raíz clásico ROOTOK.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_comments', [
            'ticket_id' => $ticket->id,
            'parent_id' => null,
            'body' => 'Comentario raíz clásico ROOTOK.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    public function test_edit_audit_log_works_for_replies(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);
        $reply = $this->makeReply($ticket, $root, user: $reporter, body: 'Antes de editar respuesta LOG.');

        $this->actingAs($reporter)
            ->patch(route('reporter.community.comments.update', $reply), [
                'body' => 'Después de editar respuesta LOG.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_comment_edit_logs', [
            'comment_id' => $reply->id,
            'ticket_id' => $ticket->id,
            'edited_by' => $reporter->id,
            'previous_body' => 'Antes de editar respuesta LOG.',
            'new_body' => 'Después de editar respuesta LOG.',
        ]);
        $this->assertSame(1, CommunityCommentEditLog::query()->where('comment_id', $reply->id)->count());
    }

    public function test_reply_notification_does_not_leak_body_or_identity(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);

        $replyBody = 'Respuesta con datos sensibles NOLEAKBODY.';

        $this->actingAs($reporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => $replyBody,
                'parent_id' => $root->id,
            ])
            ->assertRedirect();

        // Ticket reporter is notified (commenter differs from ticket reporter).
        $this->assertDatabaseHas('notifications', [
            'user_id' => $ticket->reporter_id,
            'type' => 'community.comment.created',
        ]);

        // The notification body must be generic — never the reply body.
        $this->assertDatabaseMissing('notifications', ['body' => $replyBody]);
        $this->assertSame(
            0,
            Notification::query()->where('body', 'like', '%NOLEAKBODY%')->count(),
        );
    }

    public function test_reactions_and_saves_unaffected_by_replies(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);
        $this->makeReply($ticket, $root);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'interested'])
            ->assertRedirect();
        $this->actingAs($reporter)
            ->post(route('reporter.community.saves.store', $ticket))
            ->assertRedirect();

        $this->assertDatabaseHas('community_reactions', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'interested',
        ]);
        $this->assertDatabaseHas('community_saves', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
        ]);
    }

    public function test_ticket_reports_unaffected_by_replies(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $root = $this->makeRootComment($ticket);
        $this->makeReply($ticket, $root);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), ['reason' => 'other'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', [
            'ticket_id' => $ticket->id,
            'comment_id' => null,
            'reported_by' => $reporter->id,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeRootComment(
        Ticket $ticket,
        ?User $user = null,
        string $body = 'Comentario raíz de prueba.',
        string $status = CommunityComment::STATUS_VISIBLE,
        ?User $hiddenBy = null,
    ): CommunityComment {
        $user ??= $this->createUserWithRole('reporter');

        $attributes = [
            'ticket_id' => $ticket->id,
            'parent_id' => null,
            'user_id' => $user->id,
            'body' => $body,
            'status' => $status,
        ];

        if ($status === CommunityComment::STATUS_HIDDEN) {
            $attributes['hidden_by'] = $hiddenBy?->id;
            $attributes['hidden_at'] = now();
            $attributes['hidden_reason'] = 'Oculto para test.';
        }

        if ($status === CommunityComment::STATUS_DELETED) {
            $attributes['deleted_at'] = now();
        }

        return CommunityComment::create($attributes);
    }

    private function makeReply(
        Ticket $ticket,
        CommunityComment $parent,
        ?User $user = null,
        string $body = 'Respuesta de prueba.',
        string $status = CommunityComment::STATUS_VISIBLE,
    ): CommunityComment {
        $user ??= $this->createUserWithRole('reporter');

        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'parent_id' => $parent->id,
            'user_id' => $user->id,
            'body' => $body,
            'status' => $status,
        ]);
    }

    private function makeVisibleTicket(
        string $title = 'Ticket visible comunidad REPLY',
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        $reporter = $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de test para respuestas comunitarias.',
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
            'name' => 'Sala Replies Test',
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'RP-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de respuestas comunitarias',
        ]);
    }
}
