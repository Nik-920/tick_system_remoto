<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityComment;
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
 * Community Notifications — in-app notifications for community moderation events.
 *
 * Coverage:
 *   A) Admin notified when reporter reports a ticket (publication).
 *   B) Admin notified when reporter reports a comment.
 *   C) Reporter notified when their report is reviewed (resolved / dismissed).
 *   D) Duplicate pending report does not duplicate the admin notification.
 *   E) No reporter notification if report has no reported_by.
 *   F) Ticket reporter notified when another reporter comments (optional C).
 *   G) Ticket reporter not notified for their own comment.
 *   H) No-leak: no PII, no note/resolution_note in notification body.
 */
class CommunityNotificationsTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Admin notified on ticket report ────────────────────────────────────

    public function test_admin_receives_notification_when_reporter_reports_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'community.report.created',
            'title' => 'Nuevo reporte en Comunidad',
        ]);
    }

    public function test_admin_notification_links_to_moderation_queue(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $notification = Notification::where('type', 'community.report.created')->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString(route('admin.community.moderation'), (string) $notification->url);
    }

    public function test_admin_notification_carries_ticket_id(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'type' => 'community.report.created',
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_super_admin_receives_notification_when_reporter_reports_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $superAdmin = $this->createUserWithRole('super_admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_INCORRECT_INFO,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'type' => 'community.report.created',
        ]);
    }

    public function test_reporter_does_not_receive_admin_notification_on_ticket_report(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $reporter->id,
            'type' => 'community.report.created',
        ]);
    }

    // ── B. Admin notified on comment report ───────────────────────────────────

    public function test_admin_receives_notification_when_reporter_reports_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $comment = $this->makeVisibleComment();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_INAPPROPRIATE_EVIDENCE,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'community.report.created',
            'title' => 'Nuevo reporte en Comunidad',
        ]);
    }

    public function test_super_admin_receives_notification_on_comment_report(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $superAdmin = $this->createUserWithRole('super_admin');
        $comment = $this->makeVisibleComment();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $superAdmin->id,
            'type' => 'community.report.created',
        ]);
    }

    public function test_comment_report_notification_carries_ticket_id(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $comment = $this->makeVisibleComment();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'community.report.created',
            'ticket_id' => $comment->ticket_id,
        ]);
    }

    // ── C. Reporter notified on report review ─────────────────────────────────

    public function test_reporter_receives_notification_when_report_marked_resolved(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'community.report.reviewed',
            'title' => 'Tu reporte fue revisado',
        ]);
    }

    public function test_reporter_receives_resolved_body_when_report_resolved(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'community.report.reviewed',
            'body' => 'Tu reporte fue revisado y marcado como resuelto.',
        ]);
    }

    public function test_reporter_receives_dismissed_body_when_report_dismissed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'dismissed',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'community.report.reviewed',
            'body' => 'Tu reporte fue revisado y no se tomaron acciones adicionales.',
        ]);
    }

    public function test_reporter_notification_on_review_carries_ticket_id(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_INCORRECT_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'community.report.reviewed',
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_admin_does_not_receive_reporter_reviewed_notification(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $admin->id,
            'type' => 'community.report.reviewed',
        ]);
    }

    // ── D. Duplicate pending report does not re-notify ────────────────────────

    public function test_duplicate_pending_report_does_not_create_second_admin_notification(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        // First report
        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            ])
            ->assertRedirect();

        $countAfterFirst = Notification::where('user_id', $admin->id)
            ->where('type', 'community.report.created')
            ->count();

        // Duplicate attempt — controller rejects and never calls service
        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            ])
            ->assertRedirect();

        $countAfterSecond = Notification::where('user_id', $admin->id)
            ->where('type', 'community.report.created')
            ->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    public function test_duplicate_pending_comment_report_does_not_create_second_admin_notification(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $comment = $this->makeVisibleComment();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $countAfterFirst = Notification::where('user_id', $admin->id)
            ->where('type', 'community.report.created')
            ->count();

        $this->actingAs($reporter)
            ->post(route('reporter.community.comment-reports.store', $comment), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $countAfterSecond = Notification::where('user_id', $admin->id)
            ->where('type', 'community.report.created')
            ->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);
    }

    // ── E. No notification when report has no reported_by ────────────────────

    public function test_review_does_not_fail_when_report_has_no_reported_by(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => null,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', [
            'id' => $report->id,
            'status' => CommunityReport::STATUS_RESOLVED,
        ]);
    }

    public function test_no_reporter_notification_created_when_report_has_no_reported_by(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => null,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'dismissed',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'type' => 'community.report.reviewed',
        ]);
    }

    // ── F. Ticket reporter notified on new comment ────────────────────────────

    public function test_ticket_reporter_receives_notification_when_another_reporter_comments(): void
    {
        $ticketReporter = $this->createUserWithRole('reporter');
        $commenter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicketOwnedBy($ticketReporter);

        $this->actingAs($commenter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario de otro reporter.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $ticketReporter->id,
            'type' => 'community.comment.created',
            'title' => 'Nuevo comentario en Comunidad',
        ]);
    }

    public function test_comment_notification_body_is_generic_and_carries_ticket_id(): void
    {
        $ticketReporter = $this->createUserWithRole('reporter');
        $commenter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicketOwnedBy($ticketReporter);

        $this->actingAs($commenter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario genérico de test.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $ticketReporter->id,
            'type' => 'community.comment.created',
            'body' => 'Hay un nuevo comentario en uno de tus reportes públicos.',
            'ticket_id' => $ticket->id,
        ]);
    }

    // ── G. Ticket reporter not notified for their own comment ─────────────────

    public function test_ticket_reporter_does_not_receive_notification_for_own_comment(): void
    {
        $ticketReporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicketOwnedBy($ticketReporter);

        $this->actingAs($ticketReporter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Mi propio comentario.',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $ticketReporter->id,
            'type' => 'community.comment.created',
        ]);
    }

    // ── H. No-leak ───────────────────────────────────────────────────────────

    public function test_ticket_report_notification_body_does_not_contain_report_note(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_SENSITIVE_INFO,
                'note' => 'Nota privada con información sensible NOTAPRIVADA',
            ])
            ->assertRedirect();

        $notification = Notification::where('type', 'community.report.created')->first();
        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('NOTAPRIVADA', (string) $notification->body);
        $this->assertStringNotContainsString('NOTAPRIVADA', (string) $notification->title);
    }

    public function test_report_review_notification_body_does_not_contain_resolution_note(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
                'resolution_note' => 'Nota interna del admin RESOLUTIONNOTA',
            ])
            ->assertRedirect();

        $notification = Notification::where('user_id', $reporter->id)
            ->where('type', 'community.report.reviewed')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('RESOLUTIONNOTA', (string) $notification->body);
        $this->assertStringNotContainsString('RESOLUTIONNOTA', (string) $notification->title);
    }

    public function test_comment_notification_body_does_not_contain_commenter_identity(): void
    {
        $this->ensureRolesExist();
        $ticketReporter = $this->createUserWithRole('reporter');
        $commenter = User::factory()->create([
            'name' => 'CommenterFullName IDENTLEAK',
            'email' => 'commenter-identleak@test.test',
        ]);
        $commenter->assignRole('reporter');

        $ticket = $this->makeVisibleTicketOwnedBy($ticketReporter);

        $this->actingAs($commenter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario del commenter IDENTLEAK.',
            ])
            ->assertRedirect();

        $notification = Notification::where('user_id', $ticketReporter->id)
            ->where('type', 'community.comment.created')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('IDENTLEAK', (string) $notification->body);
        $this->assertStringNotContainsString('commenter-identleak@test.test', (string) $notification->body);
        $this->assertStringNotContainsString('CommenterFullName', (string) $notification->body);
    }

    public function test_comment_notification_body_does_not_contain_comment_body(): void
    {
        $ticketReporter = $this->createUserWithRole('reporter');
        $commenter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicketOwnedBy($ticketReporter);

        $this->actingAs($commenter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Cuerpo del comentario secreto SECRETBODY',
            ])
            ->assertRedirect();

        $notification = Notification::where('user_id', $ticketReporter->id)
            ->where('type', 'community.comment.created')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('SECRETBODY', (string) $notification->body);
    }

    public function test_admin_notification_body_does_not_contain_reporter_email(): void
    {
        $this->ensureRolesExist();
        $reporter = User::factory()->create([
            'email' => 'pii-reporter-notif@test.test',
        ]);
        $reporter->assignRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            ])
            ->assertRedirect();

        $notification = Notification::where('user_id', $admin->id)
            ->where('type', 'community.report.created')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('pii-reporter-notif@test.test', (string) $notification->body);
        $this->assertStringNotContainsString('pii-reporter-notif@test.test', (string) $notification->title);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleTicket(string $state = Ticket::STATE_OPEN): Ticket
    {
        $reporter = $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => 'Ticket visible NOTIF '.Str::random(6),
            'description' => 'Descripción para test de notificaciones.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeVisibleTicketOwnedBy(User $reporter, string $state = Ticket::STATE_OPEN): Ticket
    {
        return Ticket::create([
            'title' => 'Ticket owned NOTIF '.Str::random(6),
            'description' => 'Descripción para test de notificaciones con dueño.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeVisibleComment(): CommunityComment
    {
        $ticket = $this->makeVisibleTicket();
        $commenter = $this->createUserWithRole('reporter');

        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $commenter->id,
            'body' => 'Comentario visible NOTIF',
            'status' => CommunityComment::STATUS_VISIBLE,
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
            'name' => 'Sala Notif Test',
            'building' => 'Edificio Notif',
            'floor' => '1',
            'room_code' => 'NT-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'CatNotif-'.Str::lower(Str::random(6)),
            'icon' => 'flag',
            'description' => 'Categoría para tests de notificaciones de comunidad',
        ]);
    }
}
