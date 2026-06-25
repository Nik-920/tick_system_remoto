<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketEvidenceAdded;
use App\Events\TicketStateChanged;
use App\Listeners\CreateInAppNotificationOnTicketAssigned;
use App\Listeners\CreateInAppNotificationOnTicketEvidenceAdded;
use App\Listeners\CreateInAppNotificationOnTicketStateChanged;
use App\Listeners\SendFcmPushOnTicketAssigned;
use App\Listeners\SendFcmPushOnTicketCreated;
use App\Listeners\SendFcmPushOnTicketEvidenceAdded;
use App\Listeners\SendFcmPushOnTicketStateChanged;
use App\Models\Category;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakePushNotificationProvider;
use Tests\TestCase;

/**
 * Core Ticket Notification Preferences — per-user, per-channel opt-in/opt-out.
 *
 * A) Service defaults
 * B) In-app: reporter opt-out
 * C) In-app: maintenance assignee opt-out
 * D) FCM: reporter opt-out
 * E) FCM: maintenance opt-out (assignment + state)
 * F) Profile UI
 * G) Cross-user: other users not affected
 * H) FCM: admin/super_admin ticket.created.admin opt-out
 * I) Evidence preference types: applicability + channels + opt-out
 */
class TicketNotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private TicketNotificationPreferenceService $prefs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
        $this->prefs = app(TicketNotificationPreferenceService::class);
    }

    // ── A. Service defaults ───────────────────────────────────────────────────

    public function test_missing_preference_defaults_to_enabled_in_app(): void
    {
        $reporter = $this->makeReporter();

        $this->assertTrue(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP)
        );
    }

    public function test_missing_preference_defaults_to_enabled_fcm(): void
    {
        $maintenance = $this->makeMaintenance();

        $this->assertTrue(
            $this->prefs->isEnabled($maintenance, TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM)
        );
    }

    public function test_disabled_preference_returns_false(): void
    {
        $reporter = $this->makeReporter();
        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $this->assertFalse(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP)
        );
    }

    public function test_disabling_in_app_does_not_disable_fcm(): void
    {
        $reporter = $this->makeReporter();
        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $this->assertTrue(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_FCM)
        );
    }

    public function test_disabling_fcm_does_not_disable_in_app(): void
    {
        $maintenance = $this->makeMaintenance();
        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM, false);

        $this->assertTrue(
            $this->prefs->isEnabled($maintenance, TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, TicketNotificationPreference::CHANNEL_IN_APP)
        );
    }

    public function test_applicable_types_for_reporter(): void
    {
        $reporter = $this->makeReporter();

        $types = $this->prefs->applicableTypesFor($reporter);

        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, $types);
        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE, $types);
        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, $types);
    }

    public function test_applicable_types_for_maintenance(): void
    {
        $maintenance = $this->makeMaintenance();

        $types = $this->prefs->applicableTypesFor($maintenance);

        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_UNASSIGNED_ASSIGNEE, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, $types);
        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, $types);
    }

    public function test_applicable_types_for_admin(): void
    {
        $admin = $this->makeAdmin();

        $types = $this->prefs->applicableTypesFor($admin);

        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, $types);
        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, $types);
    }

    public function test_admin_sees_ticket_created_admin_with_both_in_app_and_fcm_channels(): void
    {
        $admin = $this->makeAdmin();

        $prefs = $this->prefs->applicablePreferencesFor($admin);

        $this->assertArrayHasKey(TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, $prefs);
        $channels = $prefs[TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN]['channels'];
        $this->assertArrayHasKey(TicketNotificationPreference::CHANNEL_IN_APP, $channels);
        $this->assertArrayHasKey(TicketNotificationPreference::CHANNEL_FCM, $channels);
    }

    public function test_super_admin_sees_ticket_created_admin_with_both_channels(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $prefs = $this->prefs->applicablePreferencesFor($superAdmin);

        $this->assertArrayHasKey(TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, $prefs);
        $channels = $prefs[TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN]['channels'];
        $this->assertArrayHasKey(TicketNotificationPreference::CHANNEL_FCM, $channels);
    }

    public function test_reporter_does_not_see_ticket_created_admin(): void
    {
        $reporter = $this->makeReporter();

        $prefs = $this->prefs->applicablePreferencesFor($reporter);

        $this->assertArrayNotHasKey(TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, $prefs);
    }

    public function test_maintenance_does_not_see_ticket_created_admin(): void
    {
        $maintenance = $this->makeMaintenance();

        $prefs = $this->prefs->applicablePreferencesFor($maintenance);

        $this->assertArrayNotHasKey(TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, $prefs);
    }

    public function test_applicable_types_for_multi_role_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');
        $user->assignRole('maintenance');

        $types = $this->prefs->applicableTypesFor($user);

        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, $types);
    }

    // ── B. In-app: reporter opt-out ───────────────────────────────────────────

    public function test_reporter_opt_out_in_app_state_change_prevents_notification(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $listener = new CreateInAppNotificationOnTicketStateChanged(app(NotificationService::class), $this->prefs);
        $listener->handle(new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'pref-t01'));

        $this->assertDatabaseMissing('notifications', ['user_id' => $reporter->id]);
    }

    public function test_reporter_with_in_app_enabled_receives_notification(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        $listener = new CreateInAppNotificationOnTicketStateChanged(app(NotificationService::class), $this->prefs);
        $listener->handle(new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'pref-t02'));

        $this->assertDatabaseHas('notifications', ['user_id' => $reporter->id, 'type' => 'ticket_state_changed']);
    }

    // ── C. In-app: maintenance opt-out ───────────────────────────────────────

    public function test_maintenance_opt_out_in_app_state_assignee_prevents_notification(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $listener = new CreateInAppNotificationOnTicketStateChanged(app(NotificationService::class), $this->prefs);
        $listener->handle(new TicketStateChanged($ticket, $admin, 'open', 'in_progress', 'pref-t03'));

        $this->assertDatabaseMissing('notifications', ['user_id' => $maintenance->id]);
    }

    public function test_maintenance_opt_out_in_app_assigned_prevents_notification(): void
    {
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicket($this->makeReporter());

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $listener = new CreateInAppNotificationOnTicketAssigned(app(NotificationService::class), $this->prefs);
        $listener->handle(new TicketAssigned($ticket, $admin, null, $maintenance, 'assigned', 'pref-t04'));

        $this->assertDatabaseMissing('notifications', ['user_id' => $maintenance->id]);
    }

    public function test_maintenance_opt_out_in_app_state_does_not_affect_reporter(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $listener = new CreateInAppNotificationOnTicketStateChanged(app(NotificationService::class), $this->prefs);
        $listener->handle(new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'pref-t05'));

        $this->assertDatabaseHas('notifications', ['user_id' => $reporter->id, 'type' => 'ticket_state_changed']);
    }

    // ── D. FCM: reporter opt-out ──────────────────────────────────────────────

    public function test_reporter_opt_out_fcm_state_change_prevents_push(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $fcm = new FakePushNotificationProvider;

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_FCM, false);

        $listener = new SendFcmPushOnTicketStateChanged($fcm, $this->prefs);
        $listener->handle(new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'pref-t06'));

        $fcm->assertNotSentToUser($reporter->id);
    }

    public function test_reporter_fcm_in_app_disabled_still_sends_fcm(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $fcm = new FakePushNotificationProvider;

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $listener = new SendFcmPushOnTicketStateChanged($fcm, $this->prefs);
        $listener->handle(new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'pref-t07'));

        $fcm->assertSentToUser($reporter->id);
    }

    public function test_no_explicit_preference_still_sends_fcm(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $fcm = new FakePushNotificationProvider;

        $listener = new SendFcmPushOnTicketStateChanged($fcm, $this->prefs);
        $listener->handle(new TicketStateChanged($ticket, $admin, 'open', 'in_progress', 'pref-t08'));

        $fcm->assertSentToUser($reporter->id);
    }

    // ── E. FCM: maintenance opt-out ───────────────────────────────────────────

    public function test_maintenance_opt_out_fcm_state_assignee_prevents_push(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $fcm = new FakePushNotificationProvider;

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM, false);

        $listener = new SendFcmPushOnTicketStateChanged($fcm, $this->prefs);
        $listener->handle(new TicketStateChanged($ticket, $admin, 'open', 'in_progress', 'pref-t09'));

        $fcm->assertNotSentToUser($maintenance->id);
        $fcm->assertSentToUser($reporter->id);
    }

    public function test_maintenance_opt_out_fcm_assigned_prevents_push(): void
    {
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicket($this->makeReporter());
        $fcm = new FakePushNotificationProvider;

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM, false);

        $listener = new SendFcmPushOnTicketAssigned($fcm, $this->prefs);
        $listener->handle(new TicketAssigned($ticket, $admin, null, $maintenance, 'assigned', 'pref-t10'));

        $fcm->assertNothingSent();
    }

    public function test_in_app_enabled_when_only_fcm_disabled(): void
    {
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_FCM, false);

        $listener = new CreateInAppNotificationOnTicketStateChanged(app(NotificationService::class), $this->prefs);
        $listener->handle(new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'pref-t11'));

        $this->assertDatabaseHas('notifications', ['user_id' => $reporter->id, 'type' => 'ticket_state_changed']);
    }

    // ── F. Profile UI ─────────────────────────────────────────────────────────

    public function test_reporter_profile_shows_applicable_ticket_preferences(): void
    {
        $reporter = $this->makeReporter();

        $this->actingAs($reporter)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Preferencias de notificaciones de tickets')
            ->assertSee('Cambios de estado de mis tickets');
    }

    public function test_maintenance_profile_shows_assigned_and_state_preferences(): void
    {
        $maintenance = $this->makeMaintenance();

        $this->actingAs($maintenance)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Tickets asignados a mí')
            ->assertSee('Cambios de estado asignado');
    }

    public function test_admin_profile_shows_created_admin_preference(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Tickets creados');
    }

    public function test_patch_saves_valid_preferences(): void
    {
        $reporter = $this->makeReporter();

        $this->actingAs($reporter)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER => [
                        TicketNotificationPreference::CHANNEL_IN_APP => '1',
                        TicketNotificationPreference::CHANNEL_FCM => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertFalse(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_FCM)
        );
        $this->assertTrue(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP)
        );
    }

    public function test_patch_ignores_type_not_applicable_to_role(): void
    {
        $reporter = $this->makeReporter();

        $this->actingAs($reporter)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE => [
                        TicketNotificationPreference::CHANNEL_IN_APP => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('ticket_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_ticket_preferences(): void
    {
        $this->patch(route('profile.ticket-notifications.update'), [])
            ->assertRedirect(route('login'));
    }

    public function test_profile_shows_in_app_and_push_labels_in_spanish(): void
    {
        $reporter = $this->makeReporter();

        $this->actingAs($reporter)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('En la app')
            ->assertSee('Push móvil');
    }

    // ── G. Cross-user: other users not affected ───────────────────────────────

    public function test_preference_of_one_user_does_not_affect_another(): void
    {
        $admin = $this->makeAdmin();
        $reporterA = $this->makeReporter('a');
        $reporterB = $this->makeReporter('b');
        $ticketA = $this->makeTicket($reporterA);
        $ticketB = $this->makeTicket($reporterB);

        $this->prefs->set($reporterA, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $listener = new CreateInAppNotificationOnTicketStateChanged(app(NotificationService::class), $this->prefs);
        $listener->handle(new TicketStateChanged($ticketA, $admin, 'open', 'resolved', 'pref-t12a'));
        $listener->handle(new TicketStateChanged($ticketB, $admin, 'open', 'resolved', 'pref-t12b'));

        $this->assertDatabaseMissing('notifications', ['user_id' => $reporterA->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $reporterB->id, 'type' => 'ticket_state_changed']);
    }

    // ── H. FCM: admin/super_admin ticket.created.admin opt-out ──────────────

    public function test_admin_opt_out_fcm_ticket_created_admin_prevents_push(): void
    {
        $admin = $this->makeAdmin();
        $fcm = new FakePushNotificationProvider;

        $this->prefs->set($admin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_FCM, false);

        $ticket = $this->makeTicket($this->makeReporter());
        $listener = new SendFcmPushOnTicketCreated($fcm, $this->prefs);
        $listener->handle(new TicketCreated($ticket, 'pref-h01'));

        $fcm->assertNotSentToUser($admin->id);
    }

    public function test_super_admin_opt_out_fcm_ticket_created_admin_prevents_push(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $fcm = new FakePushNotificationProvider;

        $this->prefs->set($superAdmin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_FCM, false);

        $ticket = $this->makeTicket($this->makeReporter());
        $listener = new SendFcmPushOnTicketCreated($fcm, $this->prefs);
        $listener->handle(new TicketCreated($ticket, 'pref-h02'));

        $fcm->assertNotSentToUser($superAdmin->id);
    }

    public function test_admin_with_default_preference_receives_fcm_for_ticket_created(): void
    {
        $admin = $this->makeAdmin();
        $fcm = new FakePushNotificationProvider;

        $ticket = $this->makeTicket($this->makeReporter());
        $listener = new SendFcmPushOnTicketCreated($fcm, $this->prefs);
        $listener->handle(new TicketCreated($ticket, 'pref-h03'));

        $fcm->assertSentToUser($admin->id);
    }

    public function test_super_admin_opt_out_independent_of_admin(): void
    {
        $admin = $this->makeAdmin();
        $superAdmin = $this->makeSuperAdmin();
        $fcm = new FakePushNotificationProvider;

        $this->prefs->set($superAdmin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_FCM, false);

        $ticket = $this->makeTicket($this->makeReporter());
        $listener = new SendFcmPushOnTicketCreated($fcm, $this->prefs);
        $listener->handle(new TicketCreated($ticket, 'pref-h04'));

        $fcm->assertSentToUser($admin->id);
        $fcm->assertNotSentToUser($superAdmin->id);
    }

    public function test_patch_saves_ticket_created_admin_fcm_preference_for_admin(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN => [
                        TicketNotificationPreference::CHANNEL_IN_APP => '1',
                        TicketNotificationPreference::CHANNEL_FCM => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertFalse(
            $this->prefs->isEnabled($admin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_FCM)
        );
        $this->assertTrue(
            $this->prefs->isEnabled($admin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_IN_APP)
        );
    }

    public function test_patch_ignores_ticket_created_admin_fcm_for_reporter(): void
    {
        $reporter = $this->makeReporter();

        $this->actingAs($reporter)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN => [
                        TicketNotificationPreference::CHANNEL_FCM => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('ticket_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN,
        ]);
    }

    public function test_patch_ignores_ticket_created_admin_fcm_for_maintenance(): void
    {
        $maintenance = $this->makeMaintenance();

        $this->actingAs($maintenance)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN => [
                        TicketNotificationPreference::CHANNEL_FCM => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('ticket_notification_preferences', [
            'user_id' => $maintenance->id,
            'type' => TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN,
        ]);
    }

    public function test_admin_in_app_opt_out_does_not_affect_fcm_for_ticket_created(): void
    {
        $admin = $this->makeAdmin();
        $fcm = new FakePushNotificationProvider;

        $this->prefs->set($admin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $ticket = $this->makeTicket($this->makeReporter());
        $listener = new SendFcmPushOnTicketCreated($fcm, $this->prefs);
        $listener->handle(new TicketCreated($ticket, 'pref-h05'));

        $fcm->assertSentToUser($admin->id);
    }

    // ── I. Evidence preference types ──────────────────────────────────────────

    public function test_reporter_sees_evidence_reporter_type_in_applicable_types(): void
    {
        $reporter = $this->makeReporter();

        $types = $this->prefs->applicableTypesFor($reporter);

        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, $types);
        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, $types);
    }

    public function test_maintenance_sees_evidence_assignee_type_in_applicable_types(): void
    {
        $maintenance = $this->makeMaintenance();

        $types = $this->prefs->applicableTypesFor($maintenance);

        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, $types);
        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, $types);
    }

    public function test_admin_does_not_see_evidence_types_in_applicable_types(): void
    {
        $admin = $this->makeAdmin();

        $types = $this->prefs->applicableTypesFor($admin);

        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, $types);
        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, $types);
    }

    public function test_reporter_evidence_type_exposes_both_channels(): void
    {
        $reporter = $this->makeReporter();

        $prefs = $this->prefs->applicablePreferencesFor($reporter);

        $this->assertArrayHasKey(TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, $prefs);
        $channels = $prefs[TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER]['channels'];
        $this->assertArrayHasKey(TicketNotificationPreference::CHANNEL_IN_APP, $channels);
        $this->assertArrayHasKey(TicketNotificationPreference::CHANNEL_FCM, $channels);
        $this->assertTrue($channels[TicketNotificationPreference::CHANNEL_IN_APP]);
        $this->assertTrue($channels[TicketNotificationPreference::CHANNEL_FCM]);
    }

    public function test_reporter_opt_out_evidence_in_app_blocks_in_app_notification(): void
    {
        $reporter = $this->makeReporter();
        $admin = $this->makeAdmin();
        $ticket = $this->makeTicket($reporter);

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $listener = new CreateInAppNotificationOnTicketEvidenceAdded(app(NotificationService::class), $this->prefs);
        $listener->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $this->assertDatabaseMissing('notifications', ['user_id' => $reporter->id]);
    }

    public function test_reporter_opt_out_evidence_fcm_blocks_push(): void
    {
        $reporter = $this->makeReporter();
        $admin = $this->makeAdmin();
        $ticket = $this->makeTicket($reporter);
        $fcm = new FakePushNotificationProvider;

        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, TicketNotificationPreference::CHANNEL_FCM, false);

        $listener = new SendFcmPushOnTicketEvidenceAdded($fcm, $this->prefs);
        $listener->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $fcm->assertNotSentToUser($reporter->id);
    }

    public function test_maintenance_opt_out_evidence_assignee_fcm_blocks_push(): void
    {
        $reporter = $this->makeReporter();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $admin = $this->makeAdmin();
        $fcm = new FakePushNotificationProvider;

        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM, false);

        $listener = new SendFcmPushOnTicketEvidenceAdded($fcm, $this->prefs);
        $listener->handle(new TicketEvidenceAdded($ticket, $admin, 1));

        $fcm->assertNotSentToUser($maintenance->id);
        $fcm->assertSentToUser($reporter->id);
    }

    public function test_patch_saves_evidence_reporter_preference_for_reporter(): void
    {
        $reporter = $this->makeReporter();

        $this->actingAs($reporter)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER => [
                        TicketNotificationPreference::CHANNEL_IN_APP => '0',
                        TicketNotificationPreference::CHANNEL_FCM => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('ticket_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER,
            'channel' => TicketNotificationPreference::CHANNEL_IN_APP,
            'enabled' => false,
        ]);
    }

    public function test_patch_ignores_evidence_assignee_preference_for_reporter(): void
    {
        $reporter = $this->makeReporter();

        $this->actingAs($reporter)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE => [
                        TicketNotificationPreference::CHANNEL_FCM => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('ticket_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makeSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    private function makeMaintenance(string $suffix = ''): User
    {
        $user = User::factory()->create();
        $user->assignRole('maintenance');

        return $user;
    }

    private function makeReporter(string $suffix = ''): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeTicket(User $reporter): Ticket
    {
        $location = Location::create([
            'name' => 'Sala pref '.uniqid(),
            'building' => 'Edificio P',
            'floor' => '1',
            'room_code' => 'P-'.uniqid(),
            'qr_token' => 'qr-p-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat pref '.uniqid(),
            'icon' => 'bolt',
            'description' => 'Test pref',
        ]);

        return Ticket::create([
            'title' => 'Ticket pref prueba',
            'description' => 'Descripcion pref.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function makeTicketWithAssignment(User $reporter, User $assignee): Ticket
    {
        $ticket = $this->makeTicket($reporter);
        $ticket->assigned_to = $assignee->id;
        $ticket->save();

        return $ticket->fresh(['reporter', 'assignee']) ?? $ticket;
    }
}
