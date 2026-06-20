<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\CommunityReport;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community Comment Reports — reporter flags individual comments for moderation.
 *
 * Coverage:
 *   A) Reporter report creation: access gates, visibility gates, dedupe.
 *   B) Reporter UI: Reportar/Comentario reportado states, no-leak.
 *   C) Admin review: resolved/dismissed, role gates.
 *   D) Admin moderation queue: counts include comment reports, target_label.
 *   E) Integration: hidden comment disappears from feed, report persists in DB.
 *   F) No-leak: PII, hidden_reason, XSS safety.
 */
class CommunityCommentReportsTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Reporter report creation ───────────────────────────────────────────

    public function test_guest_cannot_report_comment(): void
    {
        $comment = $this->makeVisibleComment();

        $this->post(route('reporter.community.comment-reports.store', $comment), [
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
        ])->assertRedirect(route('login'));
    }

    public function test_reporter_can_report_visible_comment_on_visible_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_SENSITIVE_INFO,
                'note' => 'Contiene información personal.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', [
            'comment_id' => $comment->id,
            'ticket_id' => $comment->ticket_id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);
    }

    public function test_reporter_cannot_report_hidden_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeHiddenComment();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_INCORRECT_INFO,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_reports', ['comment_id' => $comment->id]);
    }

    public function test_reporter_cannot_report_deleted_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeDeletedComment();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_reports', ['comment_id' => $comment->id]);
    }

    public function test_reporter_cannot_report_comment_if_parent_ticket_hidden(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeHiddenTicket();
        $comment = $this->makeCommentOnTicket($ticket);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_INCORRECT_INFO,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_reports', ['comment_id' => $comment->id]);
    }

    public function test_reporter_cannot_report_comment_if_parent_ticket_cancelled(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(state: Ticket::STATE_CANCELLED);
        $comment = $this->makeCommentOnTicket($ticket);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_reports', ['comment_id' => $comment->id]);
    }

    public function test_reporter_cannot_report_comment_if_parent_ticket_rejected(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(state: Ticket::STATE_REJECTED);
        $comment = $this->makeCommentOnTicket($ticket);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_reports', ['comment_id' => $comment->id]);
    }

    public function test_duplicate_pending_report_for_same_comment_is_not_created(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment();

        CommunityReport::create([
            'ticket_id' => $comment->ticket_id,
            'comment_id' => $comment->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('community_reports', 1);
    }

    public function test_reporter_can_report_again_after_previous_comment_report_resolved(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment();

        CommunityReport::create([
            'ticket_id' => $comment->ticket_id,
            'comment_id' => $comment->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_RESOLVED,
        ]);

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_INCORRECT_INFO,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('community_reports', 2);
    }

    public function test_invalid_reason_is_rejected(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => 'not_a_valid_reason',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseMissing('community_reports', ['comment_id' => $comment->id]);
    }

    public function test_note_max_length_is_validated(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_OTHER,
                'note' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('note');

        $this->assertDatabaseMissing('community_reports', ['comment_id' => $comment->id]);
    }

    // ── B. Reporter UI ────────────────────────────────────────────────────────

    public function test_feed_shows_report_action_for_visible_comments_from_others(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket con comentario reportable REPT');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $other->id,
            'body' => 'Comentario de otro usuario REPT',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Reportar', false);
    }

    public function test_feed_shows_comentario_reportado_badge_for_viewer_pending_report(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket reportado badge REPBADGE');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $other->id,
            'body' => 'Comentario que ya reporté REPBADGE',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment->id,
            'reported_by' => $viewer->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Comentario reportado', false);
    }

    public function test_feed_does_not_show_reports_from_other_users(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $reporter2 = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket otras personas reportan OTHERSREP');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter2->id,
            'body' => 'Comentario reportado por otro OTHERSREP',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment->id,
            'reported_by' => $reporter2->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        // viewer has not reported — should see "Reportar", NOT "Comentario reportado"
        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Reportar', false)
            ->assertDontSee('Comentario reportado', false);
    }

    public function test_feed_does_not_show_report_notes_or_reasons(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket noleak reason NOLKREASON');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $other->id,
            'body' => 'Comentario con reporte privado NOLKREASON',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment->id,
            'reported_by' => $other->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'note' => 'Nota privada de moderación SECRETNOTE',
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Nota privada de moderación SECRETNOTE', false)
            ->assertDontSee('Información sensible', false);
    }

    // ── C. Admin review ───────────────────────────────────────────────────────

    public function test_admin_can_mark_comment_report_resolved(): void
    {
        $admin = $this->createUserWithRole('admin');
        $comment = $this->makeVisibleComment();

        $report = CommunityReport::create([
            'ticket_id' => $comment->ticket_id,
            'comment_id' => $comment->id,
            'reported_by' => $this->createUserWithRole('reporter')->id,
            'reason' => CommunityReport::REASON_INCORRECT_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
                'resolution_note' => 'Comentario revisado y aprobado.',
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(CommunityReport::STATUS_RESOLVED, $report->status);
        $this->assertSame($admin->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
        $this->assertSame('Comentario revisado y aprobado.', $report->resolution_note);
    }

    public function test_admin_can_mark_comment_report_dismissed(): void
    {
        $admin = $this->createUserWithRole('admin');
        $comment = $this->makeVisibleComment();

        $report = CommunityReport::create([
            'ticket_id' => $comment->ticket_id,
            'comment_id' => $comment->id,
            'reported_by' => $this->createUserWithRole('reporter')->id,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'dismissed',
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(CommunityReport::STATUS_DISMISSED, $report->status);
    }

    public function test_reporter_cannot_review_comment_report(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment();

        $report = CommunityReport::create([
            'ticket_id' => $comment->ticket_id,
            'comment_id' => $comment->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($reporter)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
            ])
            ->assertForbidden();
    }

    public function test_maintenance_cannot_review_comment_report(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $comment = $this->makeVisibleComment();

        $report = CommunityReport::create([
            'ticket_id' => $comment->ticket_id,
            'comment_id' => $comment->id,
            'reported_by' => $this->createUserWithRole('reporter')->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($maintenance)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
            ])
            ->assertForbidden();
    }

    public function test_review_stores_reviewed_by_and_reviewed_at(): void
    {
        $admin = $this->createUserWithRole('admin');
        $comment = $this->makeVisibleComment();

        $report = CommunityReport::create([
            'ticket_id' => $comment->ticket_id,
            'comment_id' => $comment->id,
            'reported_by' => $this->createUserWithRole('reporter')->id,
            'reason' => CommunityReport::REASON_DUPLICATE_OR_CONFUSING,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'dismissed',
                'resolution_note' => 'No viola las normas.',
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame($admin->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
        $this->assertSame('No viola las normas.', $report->resolution_note);
    }

    // ── D. Admin moderation queue ─────────────────────────────────────────────

    public function test_admin_queue_pending_count_includes_comment_reports(): void
    {
        $this->createUserWithRole('admin');
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket queue count QCOUNT');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario con reporte en queue QCOUNT',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('1 pendiente', false);
    }

    public function test_admin_queue_shows_target_label_comentario_for_comment_report(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket target label TLABEL');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario objetivo TLABEL',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Comentario', false);
    }

    public function test_admin_queue_shows_target_label_publicacion_for_ticket_report(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket target pub TPUB');

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_INCORRECT_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Publicación', false);
    }

    // ── E. Integration with comment moderation ────────────────────────────────

    public function test_admin_can_hide_reported_comment_using_existing_action(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $comment = $this->makeVisibleComment();

        CommunityReport::create([
            'ticket_id' => $comment->ticket_id,
            'comment_id' => $comment->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.comments.hide', $comment), [
                'reason' => 'Contiene información sensible reportada.',
            ])
            ->assertRedirect();

        $comment->refresh();
        $this->assertSame(CommunityComment::STATUS_HIDDEN, $comment->status);
    }

    public function test_hidden_comment_disappears_from_reporter_feed(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket oculta comentario HIDECMT');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $viewer->id,
            'body' => 'Comentario que se va a ocultar HIDECMT',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.comments.hide', $comment), [
                'reason' => 'Ocultado por prueba.',
            ])
            ->assertRedirect();

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Comentario que se va a ocultar HIDECMT', false);
    }

    public function test_report_remains_in_db_after_comment_hidden(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $comment = $this->makeVisibleComment();

        $report = CommunityReport::create([
            'ticket_id' => $comment->ticket_id,
            'comment_id' => $comment->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.comments.hide', $comment), [
                'reason' => 'Ocultado tras reporte.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', ['id' => $report->id]);
    }

    // ── F. No-leak / security ─────────────────────────────────────────────────

    public function test_reporter_community_does_not_expose_reporter_email_through_comment_reports(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $secret = User::factory()->create(['email' => 'secret-cmtrep@test.test']);
        $ticket = $this->makeVisibleTicket(title: 'Ticket noleak email CMT NEMAIL');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $secret->id,
            'body' => 'Comentario con email secreto NEMAIL',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment->id,
            'reported_by' => $secret->id,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('secret-cmtrep@test.test', false);
    }

    public function test_reporter_community_does_not_expose_hidden_reason(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket noleak hidden reason NHIDDEN');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $viewer->id,
            'body' => 'Comentario que será ocultado NHIDDEN',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.comments.hide', $comment), [
                'reason' => 'Motivo secreto de ocultamiento SECRETHIDDEN',
            ])
            ->assertRedirect();

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Motivo secreto de ocultamiento SECRETHIDDEN', false);
    }

    public function test_xss_in_comment_body_is_escaped_in_reporter_feed(): void
    {
        $viewer = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket XSS comment XSSCMT');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $other->id,
            'body' => '<script>alert("xss")</script>',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleComment(string $body = 'Comentario visible de prueba CMTBASE'): CommunityComment
    {
        $ticket = $this->makeVisibleTicket();
        $commenter = $this->createUserWithRole('reporter');

        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $commenter->id,
            'body' => $body,
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    private function makeHiddenComment(): CommunityComment
    {
        $ticket = $this->makeVisibleTicket();
        $admin = $this->createUserWithRole('admin');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->createUserWithRole('reporter')->id,
            'body' => 'Comentario oculto de prueba CMTHIDDEN',
            'status' => CommunityComment::STATUS_HIDDEN,
        ]);

        $comment->forceFill([
            'hidden_by' => $admin->id,
            'hidden_at' => now(),
            'hidden_reason' => 'Ocultado para test.',
        ])->save();

        return $comment;
    }

    private function makeDeletedComment(): CommunityComment
    {
        $ticket = $this->makeVisibleTicket();

        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->createUserWithRole('reporter')->id,
            'body' => 'Comentario eliminado de prueba CMTDEL',
            'status' => CommunityComment::STATUS_DELETED,
        ]);
    }

    private function makeCommentOnTicket(Ticket $ticket, string $status = CommunityComment::STATUS_VISIBLE): CommunityComment
    {
        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->createUserWithRole('reporter')->id,
            'body' => 'Comentario en ticket especificado CMTONTICKET',
            'status' => $status,
        ]);
    }

    private function makeVisibleTicket(
        string $title = 'Ticket visible comunidad CMTREP',
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        $reporter = $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de test para reportes de comentarios.',
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
        $admin = $this->createUserWithRole('admin');

        $ticket = Ticket::create([
            'title' => 'Ticket oculto CMTREPHIDDEN',
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
            'community_hidden_by' => $admin->id,
            'community_visibility_reason' => 'Ocultado para test de reportes de comentarios.',
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
            'name' => 'Sala CmtRep Test',
            'building' => 'Edificio CmtRep',
            'floor' => '1',
            'room_code' => 'CR-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'CatCmtRep-'.Str::lower(Str::random(6)),
            'icon' => 'flag',
            'description' => 'Categoría para tests de reportes de comentarios',
        ]);
    }
}
