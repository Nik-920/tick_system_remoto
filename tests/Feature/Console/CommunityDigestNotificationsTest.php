<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\CommunityNotificationPreference;
use App\Models\CommunityReaction;
use App\Models\CommunityReport;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Community\CommunityDigestQuery;
use App\Services\Community\CommunityDigestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community Digest Notifications — weekly digest of active community tickets.
 *
 * Coverage:
 *   A) Digest query / ranking — period-bounded scoring and exclusions.
 *   B) Notifications — creation, dedup, and no-leak.
 *   C) Recipients — role filtering.
 *   D) Artisan command — success/no-activity/dry-run/limit.
 *   E) Preferences UI regression — digest preference in profile.
 */
class CommunityDigestNotificationsTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Digest query / ranking ─────────────────────────────────────────────

    public function test_no_digest_sent_when_no_active_public_tickets(): void
    {
        $service = app(CommunityDigestService::class);

        $created = $service->sendWeeklyDigest();

        $this->assertSame(0, $created);
        $this->assertDatabaseMissing('notifications', ['type' => 'community.digest.weekly']);
    }

    public function test_hidden_community_tickets_are_excluded_from_digest(): void
    {
        $reporter = $this->makeReporter();

        $ticket = $this->makeTicket($reporter, 'Ticket oculto DIGESTHIDE');
        $ticket->forceFill([
            'community_hidden_at' => now(),
            'community_hidden_by' => $reporter->id,
        ])->save();

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => CommunityReaction::TYPE_INTERESTED,
        ]);

        $query = app(CommunityDigestQuery::class);
        $results = $query->topActiveTickets(now()->subDays(7), now());

        $this->assertTrue($results->isEmpty());
    }

    public function test_cancelled_tickets_are_excluded_from_digest(): void
    {
        $reporter = $this->makeReporter();

        $ticket = $this->makeTicket($reporter, 'Ticket cancelado DIGESTCNL');
        $ticket->update(['state' => Ticket::STATE_CANCELLED]);

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => CommunityReaction::TYPE_INTERESTED,
        ]);

        $query = app(CommunityDigestQuery::class);
        $results = $query->topActiveTickets(now()->subDays(7), now());

        $this->assertTrue($results->isEmpty());
    }

    public function test_rejected_tickets_are_excluded_from_digest(): void
    {
        $reporter = $this->makeReporter();

        $ticket = $this->makeTicket($reporter, 'Ticket rechazado DIGESTREJ');
        $ticket->update(['state' => Ticket::STATE_REJECTED]);

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => CommunityReaction::TYPE_INTERESTED,
        ]);

        $query = app(CommunityDigestQuery::class);
        $results = $query->topActiveTickets(now()->subDays(7), now());

        $this->assertTrue($results->isEmpty());
    }

    public function test_tickets_with_more_weekly_reactions_rank_higher(): void
    {
        $owner = $this->makeReporter();
        $reactor = $this->makeNonReporter();
        $reactor2 = $this->makeNonReporter();

        $active = $this->makeTicket($owner, 'Ticket muy apoyado DIGESTACT');
        $quiet = $this->makeTicket($owner, 'Ticket tranquilo DIGESTQUIET');

        // Active: 2 reactions within week
        CommunityReaction::create(['ticket_id' => $active->id, 'user_id' => $reactor->id, 'type' => CommunityReaction::TYPE_ALSO_HAPPENS]);
        CommunityReaction::create(['ticket_id' => $active->id, 'user_id' => $reactor2->id, 'type' => CommunityReaction::TYPE_INTERESTED]);

        $query = app(CommunityDigestQuery::class);
        $results = $query->topActiveTickets(now()->subDays(7), now(), 10);

        $ids = $results->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertContains($quiet->id, $ids);
        $this->assertLessThan(
            array_search($quiet->id, $ids),
            array_search($active->id, $ids),
            'Ticket with more reactions must rank above quiet ticket'
        );
    }

    public function test_tickets_with_weekly_comments_rank_higher(): void
    {
        $owner = $this->makeReporter();
        $commenter = $this->makeNonReporter();

        $commented = $this->makeTicket($owner, 'Ticket comentado DIGESTCMT');
        $silent = $this->makeTicket($owner, 'Ticket sin comentarios DIGESTSILENT');

        CommunityComment::create([
            'ticket_id' => $commented->id,
            'user_id' => $commenter->id,
            'body' => 'Comentario de test en digest',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $query = app(CommunityDigestQuery::class);
        $results = $query->topActiveTickets(now()->subDays(7), now(), 10);

        $ids = $results->pluck('id')->all();
        $this->assertLessThan(
            array_search($silent->id, $ids),
            array_search($commented->id, $ids),
            'Ticket with comments must rank above ticket without comments'
        );
    }

    public function test_pending_reports_penalize_ranking(): void
    {
        $owner = $this->makeReporter();
        $u1 = $this->makeNonReporter();
        $u2 = $this->makeNonReporter();
        $u3 = $this->makeNonReporter();

        // ticketA: 1 visible comment (+5)
        $ticketA = $this->makeTicket($owner, 'Ticket comentado DIGESTPENALT');
        CommunityComment::create([
            'ticket_id' => $ticketA->id,
            'user_id' => $u1->id,
            'body' => 'Comentario único',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        // ticketB: 3 pending reports (-12), no engagement
        $ticketB = $this->makeTicket($owner, 'Ticket penalizado DIGESTPENALB');
        foreach ([$u1, $u2, $u3] as $user) {
            CommunityReport::create([
                'ticket_id' => $ticketB->id,
                'reported_by' => $user->id,
                'reason' => 'spam',
                'status' => CommunityReport::STATUS_PENDING,
            ]);
        }

        $query = app(CommunityDigestQuery::class);
        $results = $query->topActiveTickets(now()->subDays(7), now(), 10);

        $ids = $results->pluck('id')->all();
        $this->assertLessThan(
            array_search($ticketB->id, $ids),
            array_search($ticketA->id, $ids),
            'Commented ticket (+5) must rank above ticket with 3 pending reports (-12)'
        );
    }

    public function test_digest_uses_only_activity_within_period(): void
    {
        $owner = $this->makeReporter();
        $reactor = $this->makeNonReporter();

        $ticketOld = $this->makeTicket($owner, 'Ticket antiguo DIGESTOLD');
        $ticketNew = $this->makeTicket($owner, 'Ticket nuevo DIGESTNEW');

        // Old ticket: engagement older than 7 days
        $reaction = CommunityReaction::create([
            'ticket_id' => $ticketOld->id,
            'user_id' => $reactor->id,
            'type' => CommunityReaction::TYPE_ALSO_HAPPENS,
        ]);
        $reaction->created_at = now()->subDays(10);
        $reaction->saveQuietly();

        // New ticket: engagement within period
        CommunityReaction::create([
            'ticket_id' => $ticketNew->id,
            'user_id' => $reactor->id,
            'type' => CommunityReaction::TYPE_ALSO_HAPPENS,
        ]);

        $query = app(CommunityDigestQuery::class);
        $results = $query->topActiveTickets(now()->subDays(7)->startOfDay(), now()->endOfDay(), 10);

        $ids = $results->pluck('id')->all();
        $this->assertLessThan(
            array_search($ticketOld->id, $ids),
            array_search($ticketNew->id, $ids),
            'Ticket with recent-period engagement must rank above ticket with only old engagement'
        );
    }

    // ── B. Notifications ─────────────────────────────────────────────────────

    public function test_weekly_digest_creates_notification_for_reporter_with_enabled_preference(): void
    {
        $reporter = $this->makeReporter();
        $this->addEngagementFor($reporter);

        $service = app(CommunityDigestService::class);
        $created = $service->sendWeeklyDigest();

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'community.digest.weekly',
            'title' => 'Resumen semanal de Comunidad',
        ]);
    }

    public function test_missing_digest_preference_defaults_to_enabled(): void
    {
        $reporter = $this->makeReporter();
        $this->addEngagementFor($reporter);

        $this->assertDatabaseMissing('community_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => 'community.digest.weekly',
        ]);

        $created = app(CommunityDigestService::class)->sendWeeklyDigest();

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'community.digest.weekly',
        ]);
    }

    public function test_disabled_digest_preference_prevents_notification(): void
    {
        $reporter = $this->makeReporter();
        $this->addEngagementFor($reporter);

        CommunityNotificationPreference::create([
            'user_id' => $reporter->id,
            'type' => CommunityNotificationPreference::TYPE_DIGEST_WEEKLY,
            'enabled' => false,
        ]);

        $created = app(CommunityDigestService::class)->sendWeeklyDigest();

        $this->assertSame(0, $created);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $reporter->id,
            'type' => 'community.digest.weekly',
        ]);
    }

    public function test_dedup_prevents_duplicate_digest_for_same_period_and_user(): void
    {
        $reporter = $this->makeReporter();
        $this->addEngagementFor($reporter);

        $service = app(CommunityDigestService::class);

        $first = $service->sendWeeklyDigest();
        $second = $service->sendWeeklyDigest();

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertSame(
            1,
            Notification::where('user_id', $reporter->id)->where('type', 'community.digest.weekly')->count()
        );
    }

    public function test_running_command_twice_does_not_duplicate_notifications(): void
    {
        $reporter = $this->makeReporter();
        $this->addEngagementFor($reporter);

        $this->artisan('community:digest')->assertExitCode(0);
        $this->artisan('community:digest')->assertExitCode(0);

        $this->assertSame(
            1,
            Notification::where('user_id', $reporter->id)->where('type', 'community.digest.weekly')->count()
        );
    }

    public function test_notification_body_does_not_include_pii(): void
    {
        $reporter = $this->makeReporter();
        $reporter->update(['name' => 'NombreOculto', 'email' => 'oculto@digest.test']);
        $this->addEngagementFor($reporter);

        app(CommunityDigestService::class)->sendWeeklyDigest();

        $notification = Notification::where('user_id', $reporter->id)
            ->where('type', 'community.digest.weekly')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('NombreOculto', (string) $notification->body);
        $this->assertStringNotContainsString('oculto@digest.test', (string) $notification->body);
        $this->assertStringNotContainsString('NombreOculto', (string) $notification->title);
    }

    public function test_notification_body_does_not_include_comment_body_or_report_notes(): void
    {
        $reporter = $this->makeReporter();
        $other = $this->makeNonReporter();

        $ticket = $this->makeTicket($reporter, 'Ticket digest noleak DIGESTNL');

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $other->id,
            'body' => 'Contenido secreto del comentario SENSITIVECOMMENT',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $other->id,
            'reason' => 'spam',
            'note' => 'Nota privada del reporte SENSITIVENOTE',
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        app(CommunityDigestService::class)->sendWeeklyDigest();

        $notification = Notification::where('type', 'community.digest.weekly')
            ->where('user_id', $reporter->id)
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('SENSITIVECOMMENT', (string) $notification->body);
        $this->assertStringNotContainsString('SENSITIVENOTE', (string) $notification->body);
        $this->assertStringNotContainsString('SENSITIVECOMMENT', (string) $notification->title);
    }

    public function test_notification_url_points_to_reporter_community_active_view(): void
    {
        $reporter = $this->makeReporter();
        $this->addEngagementFor($reporter);

        app(CommunityDigestService::class)->sendWeeklyDigest();

        $notification = Notification::where('user_id', $reporter->id)
            ->where('type', 'community.digest.weekly')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString(route('reporter.community'), (string) $notification->url);
        $this->assertStringContainsString('sort=active', (string) $notification->url);
    }

    // ── C. Recipients ────────────────────────────────────────────────────────

    public function test_reporter_recipients_receive_digest(): void
    {
        $r1 = $this->makeReporter();
        $r2 = $this->makeReporter();
        $this->addEngagementFor($r1);

        app(CommunityDigestService::class)->sendWeeklyDigest();

        $this->assertDatabaseHas('notifications', ['user_id' => $r1->id, 'type' => 'community.digest.weekly']);
        $this->assertDatabaseHas('notifications', ['user_id' => $r2->id, 'type' => 'community.digest.weekly']);
    }

    public function test_users_without_reporter_role_do_not_receive_digest(): void
    {
        $reporter = $this->makeReporter();
        $this->addEngagementFor($reporter);

        $admin = $this->makeNonReporter('admin');
        $maintenance = $this->makeNonReporter('maintenance');

        app(CommunityDigestService::class)->sendWeeklyDigest();

        $this->assertDatabaseMissing('notifications', ['user_id' => $admin->id, 'type' => 'community.digest.weekly']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $maintenance->id, 'type' => 'community.digest.weekly']);
    }

    public function test_multi_reporter_scenario_creates_one_notification_per_enabled_reporter(): void
    {
        $r1 = $this->makeReporter();
        $r2 = $this->makeReporter();
        $r3 = $this->makeReporter();

        // r3 opts out
        CommunityNotificationPreference::create([
            'user_id' => $r3->id,
            'type' => CommunityNotificationPreference::TYPE_DIGEST_WEEKLY,
            'enabled' => false,
        ]);

        $this->addEngagementFor($r1);

        $created = app(CommunityDigestService::class)->sendWeeklyDigest();

        $this->assertSame(2, $created);
        $this->assertDatabaseHas('notifications', ['user_id' => $r1->id, 'type' => 'community.digest.weekly']);
        $this->assertDatabaseHas('notifications', ['user_id' => $r2->id, 'type' => 'community.digest.weekly']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $r3->id, 'type' => 'community.digest.weekly']);
    }

    // ── D. Artisan command ───────────────────────────────────────────────────

    public function test_command_exits_successfully_with_activity(): void
    {
        $reporter = $this->makeReporter();
        $this->addEngagementFor($reporter);

        $this->artisan('community:digest')
            ->assertExitCode(0);
    }

    public function test_command_exits_successfully_with_no_activity(): void
    {
        $this->artisan('community:digest')
            ->assertExitCode(0);
    }

    public function test_dry_run_does_not_create_notifications(): void
    {
        $reporter = $this->makeReporter();
        $this->addEngagementFor($reporter);

        $this->artisan('community:digest --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $reporter->id,
            'type' => 'community.digest.weekly',
        ]);
    }

    public function test_limit_option_is_respected_by_query(): void
    {
        $owner = $this->makeReporter();
        $reactor = $this->makeNonReporter();

        foreach (range(1, 5) as $i) {
            $ticket = $this->makeTicket($owner, "Ticket limit test {$i} DIGESTLIM");
            CommunityReaction::create([
                'ticket_id' => $ticket->id,
                'user_id' => $reactor->id,
                'type' => CommunityReaction::TYPE_INTERESTED,
            ]);
        }

        $query = app(CommunityDigestQuery::class);
        $results = $query->topActiveTickets(now()->subDays(7), now(), 3);

        $this->assertCount(3, $results);
    }

    // ── E. Preferences UI regression ────────────────────────────────────────

    public function test_digest_preference_appears_in_profile_for_reporter(): void
    {
        $reporter = $this->makeReporter();

        $response = $this->actingAs($reporter)
            ->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('Avisarme con un resumen semanal de Comunidad', false);
    }

    public function test_reporter_can_disable_and_re_enable_digest_preference(): void
    {
        $reporter = $this->makeReporter();

        // Disable
        $this->actingAs($reporter)
            ->patch(route('profile.community-notifications.update'), [
                'preferences' => [
                    CommunityNotificationPreference::TYPE_DIGEST_WEEKLY => '0',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => 'community.digest.weekly',
            'enabled' => false,
        ]);

        // Re-enable
        $this->actingAs($reporter)
            ->patch(route('profile.community-notifications.update'), [
                'preferences' => [
                    CommunityNotificationPreference::TYPE_DIGEST_WEEKLY => '1',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => 'community.digest.weekly',
            'enabled' => true,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeReporter(): User
    {
        $this->ensureRolesExist();
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeNonReporter(string $role = 'maintenance'): User
    {
        $this->ensureRolesExist();
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Aula Digest '.Str::random(4),
            'building' => 'Edificio Digest',
            'floor' => '1',
            'room_code' => 'DGT-'.Str::upper(Str::random(4)),
            'qr_token' => 'qr-dgt-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-Dgt-'.Str::lower(Str::random(5)),
            'icon' => 'tag',
            'description' => 'Categoría para tests de digest',
        ]);
    }

    private function makeTicket(User $reporter, string $title): Ticket
    {
        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de prueba para digest: '.$title,
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    /**
     * Creates a visible ticket owned by $reporter and reacts to it with a
     * non-reporter user, so only $reporter appears in recipients.
     */
    private function addEngagementFor(User $reporter): void
    {
        $reactor = $this->makeNonReporter();
        $ticket = $this->makeTicket($reporter, 'Ticket con engagement DIGESTENG '.Str::random(4));

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reactor->id,
            'type' => CommunityReaction::TYPE_INTERESTED,
        ]);
    }
}
