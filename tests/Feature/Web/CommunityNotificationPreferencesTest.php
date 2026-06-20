<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityNotificationPreference;
use App\Models\CommunityReport;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Community\CommunityNotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community Notification Preferences — per-user opt-in/opt-out for community notifications.
 *
 * Coverage:
 *   A) Service defaults — missing preference defaults to enabled.
 *   B) UI — profile page shows applicable preferences by role.
 *   C) Update — user can toggle preferences via form.
 *   D) Notification integration — preference disabled prevents notification.
 *   E) Multi-admin — only admins with preference enabled receive notification.
 *   F) Dedup — dedup still works when preference is enabled.
 *   G) No-leak — no PII exposed in UI or notification content.
 */
class CommunityNotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Service defaults ───────────────────────────────────────────────────

    public function test_missing_preference_defaults_to_enabled(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $service = app(CommunityNotificationPreferenceService::class);

        $this->assertTrue($service->enabled($reporter, CommunityNotificationPreference::TYPE_COMMENT_CREATED));
    }

    public function test_disabled_preference_returns_false(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        CommunityNotificationPreference::create([
            'user_id' => $reporter->id,
            'type' => CommunityNotificationPreference::TYPE_COMMENT_CREATED,
            'enabled' => false,
        ]);

        $service = app(CommunityNotificationPreferenceService::class);

        $this->assertFalse($service->enabled($reporter, CommunityNotificationPreference::TYPE_COMMENT_CREATED));
    }

    public function test_enabled_preference_returns_true(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        CommunityNotificationPreference::create([
            'user_id' => $reporter->id,
            'type' => CommunityNotificationPreference::TYPE_COMMENT_CREATED,
            'enabled' => true,
        ]);

        $service = app(CommunityNotificationPreferenceService::class);

        $this->assertTrue($service->enabled($reporter, CommunityNotificationPreference::TYPE_COMMENT_CREATED));
    }

    public function test_unknown_type_does_not_break_service(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $service = app(CommunityNotificationPreferenceService::class);

        $this->assertTrue($service->enabled($reporter, 'community.unknown.type'));
    }

    // ── B. UI — profile shows applicable preferences by role ─────────────────

    public function test_auth_user_sees_community_notification_preferences_section(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Preferencias de notificaciones de Comunidad');
    }

    public function test_reporter_sees_report_reviewed_and_comment_created_preferences(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Avisarme cuando un reporte que envié sea revisado')
            ->assertSee('Avisarme cuando alguien comente en mis reportes públicos')
            ->assertDontSee('Avisarme cuando haya nuevos reportes de Comunidad pendientes de revisar');
    }

    public function test_admin_sees_report_created_preference(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Avisarme cuando haya nuevos reportes de Comunidad pendientes de revisar');
    }

    public function test_super_admin_sees_report_created_preference(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');

        $this->actingAs($superAdmin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Avisarme cuando haya nuevos reportes de Comunidad pendientes de revisar');
    }

    public function test_guest_cannot_update_preferences(): void
    {
        $this->patch(route('profile.community-notifications.update'), [
            'preferences' => [CommunityNotificationPreference::TYPE_COMMENT_CREATED => '0'],
        ])->assertRedirect(route('login'));
    }

    // ── C. Update ─────────────────────────────────────────────────────────────

    public function test_user_can_disable_comment_created_notifications(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->patch(route('profile.community-notifications.update'), [
                'preferences' => [
                    CommunityNotificationPreference::TYPE_REPORT_REVIEWED => '0',
                    CommunityNotificationPreference::TYPE_COMMENT_CREATED => '0',
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('community_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => CommunityNotificationPreference::TYPE_COMMENT_CREATED,
            'enabled' => false,
        ]);
    }

    public function test_user_can_reenable_comment_created_notifications(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        CommunityNotificationPreference::create([
            'user_id' => $reporter->id,
            'type' => CommunityNotificationPreference::TYPE_COMMENT_CREATED,
            'enabled' => false,
        ]);

        $this->actingAs($reporter)
            ->patch(route('profile.community-notifications.update'), [
                'preferences' => [
                    CommunityNotificationPreference::TYPE_COMMENT_CREATED => '1',
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('community_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => CommunityNotificationPreference::TYPE_COMMENT_CREATED,
            'enabled' => true,
        ]);
    }

    public function test_invalid_preference_type_is_ignored(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->patch(route('profile.community-notifications.update'), [
                'preferences' => [
                    'community.hacked.type' => '1',
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('community_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => 'community.hacked.type',
        ]);
    }

    public function test_reporter_cannot_set_admin_only_preference(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->patch(route('profile.community-notifications.update'), [
                'preferences' => [
                    CommunityNotificationPreference::TYPE_REPORT_CREATED => '0',
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        // Reporter's applicable types don't include report_created so it should not be persisted
        $this->assertDatabaseMissing('community_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => CommunityNotificationPreference::TYPE_REPORT_CREATED,
        ]);
    }

    // ── D. Notification integration ───────────────────────────────────────────

    public function test_admin_with_report_created_disabled_does_not_receive_notification(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        CommunityNotificationPreference::create([
            'user_id' => $admin->id,
            'type' => CommunityNotificationPreference::TYPE_REPORT_CREATED,
            'enabled' => false,
        ]);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $admin->id,
            'type' => 'community.report.created',
        ]);
    }

    public function test_reporter_with_report_reviewed_disabled_does_not_receive_notification(): void
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

        CommunityNotificationPreference::create([
            'user_id' => $reporter->id,
            'type' => CommunityNotificationPreference::TYPE_REPORT_REVIEWED,
            'enabled' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.community.reports.review', $report), [
                'status' => 'resolved',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $reporter->id,
            'type' => 'community.report.reviewed',
        ]);
    }

    public function test_ticket_owner_with_comment_created_disabled_does_not_receive_notification(): void
    {
        $ticketReporter = $this->createUserWithRole('reporter');
        $commenter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicketOwnedBy($ticketReporter);

        CommunityNotificationPreference::create([
            'user_id' => $ticketReporter->id,
            'type' => CommunityNotificationPreference::TYPE_COMMENT_CREATED,
            'enabled' => false,
        ]);

        $this->actingAs($commenter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario de otro reporter.',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $ticketReporter->id,
            'type' => 'community.comment.created',
        ]);
    }

    public function test_missing_preference_maintains_existing_notification_behavior(): void
    {
        $ticketReporter = $this->createUserWithRole('reporter');
        $commenter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicketOwnedBy($ticketReporter);

        // No preference record — should default to enabled and send notification
        $this->actingAs($commenter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario sin preferencia definida.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $ticketReporter->id,
            'type' => 'community.comment.created',
        ]);
    }

    // ── E. Multi-admin ────────────────────────────────────────────────────────

    public function test_only_admin_with_enabled_preference_receives_report_created_notification(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin1 = $this->createUserWithRole('admin');
        $admin2 = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        CommunityNotificationPreference::create([
            'user_id' => $admin1->id,
            'type' => CommunityNotificationPreference::TYPE_REPORT_CREATED,
            'enabled' => false,
        ]);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $admin1->id,
            'type' => 'community.report.created',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin2->id,
            'type' => 'community.report.created',
        ]);
    }

    // ── F. Dedup ─────────────────────────────────────────────────────────────

    public function test_dedup_still_works_when_preference_enabled(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        CommunityNotificationPreference::create([
            'user_id' => $admin->id,
            'type' => CommunityNotificationPreference::TYPE_REPORT_CREATED,
            'enabled' => true,
        ]);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'community.report.created',
        ]);
    }

    public function test_duplicate_pending_report_still_does_not_create_duplicate_notification(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $this->actingAs($reporter)
            ->post(route('reporter.community.reports.store', $ticket), [
                'reason' => CommunityReport::REASON_OTHER,
            ])
            ->assertRedirect();

        $this->assertCount(
            1,
            Notification::where('user_id', $admin->id)->where('type', 'community.report.created')->get()
        );
    }

    // ── G. No-leak ────────────────────────────────────────────────────────────

    public function test_preference_ui_does_not_expose_pii(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $otherUser = $this->createUserWithRole('reporter');

        $response = $this->actingAs($reporter)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('Preferencias de notificaciones de Comunidad');
        // Other users' data does not appear in the preferences section
        $response->assertDontSee($otherUser->email);
        $response->assertDontSee($otherUser->last_name);
    }

    public function test_notification_body_does_not_expose_pii_after_preference_checks(): void
    {
        $ticketReporter = $this->createUserWithRole('reporter');
        $commenter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicketOwnedBy($ticketReporter);

        $this->actingAs($commenter)
            ->post(route('reporter.community.comments.store', $ticket), [
                'body' => 'Comentario PII test.',
            ])
            ->assertRedirect();

        $notification = Notification::where('user_id', $ticketReporter->id)
            ->where('type', 'community.comment.created')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringNotContainsString($commenter->email, (string) $notification->body);
        $this->assertStringNotContainsString($commenter->name, (string) $notification->body);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleTicket(string $state = Ticket::STATE_OPEN): Ticket
    {
        $reporter = $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => 'Ticket CNP '.Str::random(6),
            'description' => 'Descripción para test de preferencias.',
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
            'title' => 'Ticket CNP owned '.Str::random(6),
            'description' => 'Descripción para test de preferencias con dueño.',
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
            'name' => 'Sala CNP Test',
            'building' => 'Edificio CNP',
            'floor' => '1',
            'room_code' => 'CP-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'CatCNP-'.Str::lower(Str::random(6)),
            'icon' => 'flag',
            'description' => 'Categoría para tests de preferencias de notificaciones',
        ]);
    }
}
