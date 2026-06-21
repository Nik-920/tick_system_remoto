<?php

declare(strict_types=1);

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
 * Community Reports by Users — reporter-submitted reports for admin moderation.
 *
 * Coverage:
 *   A) Reporter report creation: access, visibility gates, dedupe.
 *   B) Reporter UI: Reportar/Reporte enviado in feed, no-leak.
 *   C) Admin queue: pending counts, review actions, role gates.
 *   D) Integration with hide: ticket can be hidden after report.
 *   E) No-leak: reporter PII not exposed via reports.
 */
class CommunityReportsByUsersTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Reporter report creation ───────────────────────────────────────────

    public function test_guest_cannot_report(): void
    {
        $ticket = $this->makeVisibleTicket();

        $this->post(route('reporter.community.reports.store', $ticket), [
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
        ])->assertRedirect(route('login'));
    }

    public function test_reporter_can_report_visible_community_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_SENSITIVE_INFO,
                'note' => 'Contiene información personal.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', [
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);
    }

    public function test_reporter_cannot_report_hidden_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeHiddenTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_INCORRECT_INFO,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_reports', ['ticket_id' => $ticket->id]);
    }

    public function test_reporter_cannot_report_cancelled_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(state: Ticket::STATE_CANCELLED);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_reports', ['ticket_id' => $ticket->id]);
    }

    public function test_reporter_cannot_report_rejected_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(state: Ticket::STATE_REJECTED);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_reports', ['ticket_id' => $ticket->id]);
    }

    public function test_reporter_cannot_create_duplicate_pending_report_for_same_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket dedupe RDUP');

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_INCORRECT_INFO,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('community_reports', 1);
    }

    public function test_reporter_can_report_again_after_previous_report_resolved(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket re-report RREP');

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_RESOLVED,
        ]);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('community_reports', 2);
    }

    public function test_invalid_reason_is_rejected(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => 'invalid_reason_xyz',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseMissing('community_reports', ['ticket_id' => $ticket->id]);
    }

    public function test_note_max_length_is_validated(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_OTHER,
                'note' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('note');

        $this->assertDatabaseMissing('community_reports', ['ticket_id' => $ticket->id]);
    }

    // ── B. Reporter UI ────────────────────────────────────────────────────────

    public function test_feed_shows_reportar_publicacion_action(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeVisibleTicket(title: 'Ticket para reportar UI');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Reportar', false);
    }

    public function test_feed_shows_reporte_enviado_when_viewer_has_pending_report(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket reporte enviado RENV');

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Reporte enviado', false);
    }

    public function test_feed_does_not_expose_other_users_reports(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(title: 'Ticket otra persona RLEAK');

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $other->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'note' => 'Nota secreta del otro reporter',
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Nota secreta del otro reporter', false)
            ->assertDontSee('Reporte enviado', false);
    }

    public function test_feed_does_not_expose_report_notes_or_reasons_of_others(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $secret = User::factory()->create([
            'name' => 'SecretReporterUser',
            'email' => 'secret-reporter@test.test',
        ]);
        $ticket = $this->makeVisibleTicket(title: 'Ticket pii report RPII');

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $secret->id,
            'reason' => CommunityReport::REASON_INAPPROPRIATE_EVIDENCE,
            'note' => 'Nota privada del reporter secreto',
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('SecretReporterUser', false)
            ->assertDontSee('secret-reporter@test.test', false)
            ->assertDontSee('Nota privada del reporter secreto', false);
    }

    // ── C. Admin queue ────────────────────────────────────────────────────────

    public function test_admin_queue_shows_pending_report_count(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket con reportes AQUEUE');

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('1 pendiente', false);
    }

    public function test_admin_queue_shows_pending_reports_kpi(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Reportes pendientes', false);
    }

    public function test_admin_can_mark_report_resolved(): void
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
                'resolution_note' => 'Se verificó y se ocultará el ticket.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', [
            'id' => $report->id,
            'status' => CommunityReport::STATUS_RESOLVED,
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_admin_can_mark_report_dismissed(): void
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

        $this->assertDatabaseHas('community_reports', [
            'id' => $report->id,
            'status' => CommunityReport::STATUS_DISMISSED,
        ]);
    }

    public function test_reporter_cannot_review_reports(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($reporter)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'dismissed',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('community_reports', [
            'id' => $report->id,
            'status' => CommunityReport::STATUS_PENDING,
        ]);
    }

    public function test_maintenance_cannot_review_reports(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($maintenance)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
            ])
            ->assertForbidden();
    }

    public function test_admin_review_stores_reviewed_by_and_reviewed_at(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_DUPLICATE_OR_CONFUSING,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
                'resolution_note' => 'Duplicado confirmado.',
            ])
            ->assertRedirect();

        $report->refresh();

        $this->assertSame(CommunityReport::STATUS_RESOLVED, $report->status);
        $this->assertSame($admin->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
        $this->assertSame('Duplicado confirmado.', $report->resolution_note);
    }

    // ── D. Integration with hide ──────────────────────────────────────────────

    public function test_admin_can_hide_ticket_after_report_using_existing_action(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket a ocultar AHIDE');

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), [
                'reason' => 'Contiene información sensible reportada por usuarios.',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertFalse((bool) $ticket->community_visible);
    }

    public function test_hiding_ticket_removes_it_from_reporter_community_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket que desaparece AHIDE2');

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), [
                'reason' => 'Ocultado tras reporte.',
            ])
            ->assertRedirect();

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Ticket que desaparece AHIDE2', false);
    }

    public function test_report_remains_in_db_after_ticket_is_hidden(): void
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
            ->patch(route('tickets.community.hide', $ticket), [
                'reason' => 'Ocultado por reporte.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reports', ['id' => $report->id]);
    }

    // ── E. No-leak ────────────────────────────────────────────────────────────

    public function test_reporter_community_does_not_expose_reporter_email_through_reports(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $secret = User::factory()->create(['email' => 'secret-noleak@test.test']);
        $ticket = $this->makeVisibleTicket(title: 'Ticket noleak email REMAIL');

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $secret->id,
            'reason' => CommunityReport::REASON_OTHER,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('secret-noleak@test.test', false);
    }

    public function test_reporter_community_does_not_expose_moderation_reason_logs(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Ticket no mod reason RMOD');

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), [
                'reason' => 'Motivo secreto de moderación MODSECRET',
            ])
            ->assertRedirect();

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Motivo secreto de moderación MODSECRET', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleTicket(
        string $title = 'Ticket visible comunidad REPORT',
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        $reporter = $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de test para reportes comunitarios.',
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
            'title' => 'Ticket oculto REPORT',
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
            'community_visibility_reason' => 'Ocultado para test de reportes.',
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
            'name' => 'Sala Reports Test',
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
            'description' => 'Categoría para tests de reportes comunitarios',
        ]);
    }
}
