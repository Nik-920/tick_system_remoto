<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Canal 'email' agregado a las preferencias core de tickets (Fase A).
 * No debe alterar el comportamiento de in_app/fcm.
 */
class TicketEmailNotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private TicketNotificationPreferenceService $prefs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
        $this->prefs = app(TicketNotificationPreferenceService::class);
    }

    public function test_missing_preference_defaults_to_enabled_email(): void
    {
        $reporter = $this->makeReporter();

        $this->assertTrue(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_EMAIL)
        );
    }

    public function test_reporter_evidence_type_exposes_email_channel(): void
    {
        $reporter = $this->makeReporter();

        $prefs = $this->prefs->applicablePreferencesFor($reporter);

        $channels = $prefs[TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER]['channels'];
        $this->assertArrayHasKey(TicketNotificationPreference::CHANNEL_EMAIL, $channels);
        $this->assertTrue($channels[TicketNotificationPreference::CHANNEL_EMAIL]);
    }

    public function test_maintenance_sees_email_channel_for_all_applicable_types(): void
    {
        $maintenance = $this->makeMaintenance();

        $prefs = $this->prefs->applicablePreferencesFor($maintenance);

        foreach ($prefs as $entry) {
            $this->assertArrayHasKey(TicketNotificationPreference::CHANNEL_EMAIL, $entry['channels']);
        }
    }

    public function test_admin_sees_email_channel_for_ticket_created_admin(): void
    {
        $admin = $this->makeAdmin();

        $prefs = $this->prefs->applicablePreferencesFor($admin);

        $this->assertArrayHasKey(
            TicketNotificationPreference::CHANNEL_EMAIL,
            $prefs[TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN]['channels'],
        );
    }

    public function test_disabling_email_does_not_disable_in_app_or_fcm(): void
    {
        $reporter = $this->makeReporter();
        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_EMAIL, false);

        $this->assertTrue(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP)
        );
        $this->assertTrue(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_FCM)
        );
    }

    public function test_disabling_in_app_does_not_disable_email(): void
    {
        $reporter = $this->makeReporter();
        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $this->assertTrue(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_EMAIL)
        );
    }

    public function test_profile_shows_correo_label(): void
    {
        $reporter = $this->makeReporter();

        $this->actingAs($reporter)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Correo');
    }

    public function test_patch_saves_email_preference(): void
    {
        $reporter = $this->makeReporter();

        $this->actingAs($reporter)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER => [
                        TicketNotificationPreference::CHANNEL_IN_APP => '1',
                        TicketNotificationPreference::CHANNEL_FCM => '1',
                        TicketNotificationPreference::CHANNEL_EMAIL => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertFalse(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_EMAIL)
        );
        $this->assertTrue(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP)
        );
        $this->assertTrue(
            $this->prefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_FCM)
        );
    }

    public function test_patch_ignores_type_not_applicable_to_role_for_email(): void
    {
        $reporter = $this->makeReporter();

        $this->actingAs($reporter)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN => [
                        TicketNotificationPreference::CHANNEL_EMAIL => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('ticket_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN,
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

    private function makeMaintenance(): User
    {
        $user = User::factory()->create();
        $user->assignRole('maintenance');

        return $user;
    }

    private function makeReporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }
}
