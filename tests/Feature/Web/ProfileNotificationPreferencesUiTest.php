<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityNotificationPreference;
use App\Models\Location;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Community\CommunityNotificationPreferenceService;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Profile notification preferences UI alignment.
 *
 * A) Community UI — toggle controls visible, badge, no Push móvil
 * B) Ticket counts by role (types × channels = active total)
 * C) Community counts by role
 * D) Profile page comment-type visibility per role
 * E) PATCH saves community and ticket comment preferences
 * F) Regression — no-action cards absent; save forms only when prefs exist
 */
class ProfileNotificationPreferencesUiTest extends TestCase
{
    use RefreshDatabase;

    private TicketNotificationPreferenceService $ticketPrefs;

    private CommunityNotificationPreferenceService $commPrefs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
        $this->ticketPrefs = app(TicketNotificationPreferenceService::class);
        $this->commPrefs = app(CommunityNotificationPreferenceService::class);
    }

    // ── A. Community UI — toggle controls visible ─────────────────────────────

    public function test_community_card_renders_with_toggle_track_for_reporter(): void
    {
        $reporter = $this->makeUser('reporter');

        $this->actingAs($reporter)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('profile-pref-toggle-track', false)
            ->assertSee('js-comm-pref-toggle', false);
    }

    public function test_community_card_shows_en_la_app_channel_for_reporter(): void
    {
        $reporter = $this->makeUser('reporter');

        $this->actingAs($reporter)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('commNotifForm', false)
            ->assertSee('En la app');
    }

    public function test_community_card_has_active_badge_element_for_reporter(): void
    {
        $reporter = $this->makeUser('reporter');

        $this->actingAs($reporter)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('commActiveBadge', false)
            ->assertSee('activas');
    }

    public function test_community_card_does_not_show_push_movil_label(): void
    {
        $admin = $this->makeUser('admin');

        $html = $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->getContent();

        $commStart = strpos((string) $html, 'commNotifForm');
        $commEnd = strpos((string) $html, '/commNotifForm');

        $this->assertNotFalse($commStart, 'Community form not found in page');

        $communitySection = substr((string) $html, (int) $commStart, (int) $commEnd - (int) $commStart);

        $this->assertStringNotContainsString('Push móvil', $communitySection);
    }

    public function test_maintenance_does_not_see_community_card(): void
    {
        $maintenance = $this->makeUser('maintenance');

        $this->actingAs($maintenance)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Preferencias de notificaciones de Comunidad')
            ->assertDontSee('commNotifForm', false);
    }

    public function test_community_save_button_only_appears_when_prefs_exist(): void
    {
        $maintenance = $this->makeUser('maintenance');

        $html = $this->actingAs($maintenance)
            ->get(route('profile.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('commNotifForm', (string) $html);
        $this->assertStringNotContainsString('commSaveBtn', (string) $html);
    }

    public function test_community_card_uses_correct_form_action(): void
    {
        $reporter = $this->makeUser('reporter');

        $this->actingAs($reporter)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(route('profile.community-notifications.update'), false);
    }

    // ── B. Ticket counts by role ──────────────────────────────────────────────

    public function test_reporter_has_three_applicable_ticket_types(): void
    {
        $reporter = $this->makeUser('reporter');
        $types = $this->ticketPrefs->applicableTypesFor($reporter);

        $this->assertCount(3, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER, $types);
    }

    public function test_reporter_has_six_active_ticket_channels_by_default(): void
    {
        $reporter = $this->makeUser('reporter');
        $prefs = $this->ticketPrefs->applicablePreferencesFor($reporter);

        $total = array_sum(array_map(fn (array $p) => count($p['channels']), $prefs));

        $this->assertSame(6, $total);
    }

    public function test_maintenance_has_five_applicable_ticket_types(): void
    {
        $maintenance = $this->makeUser('maintenance');
        $types = $this->ticketPrefs->applicableTypesFor($maintenance);

        $this->assertCount(5, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_UNASSIGNED_ASSIGNEE, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE, $types);
    }

    public function test_maintenance_has_ten_active_ticket_channels_by_default(): void
    {
        $maintenance = $this->makeUser('maintenance');
        $prefs = $this->ticketPrefs->applicablePreferencesFor($maintenance);

        $total = array_sum(array_map(fn (array $p) => count($p['channels']), $prefs));

        $this->assertSame(10, $total);
    }

    public function test_admin_has_one_applicable_ticket_type(): void
    {
        $admin = $this->makeUser('admin');
        $types = $this->ticketPrefs->applicableTypesFor($admin);

        $this->assertCount(1, $types);
        $this->assertContains(TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, $types);
    }

    public function test_admin_has_two_active_ticket_channels_by_default(): void
    {
        $admin = $this->makeUser('admin');
        $prefs = $this->ticketPrefs->applicablePreferencesFor($admin);

        $total = array_sum(array_map(fn (array $p) => count($p['channels']), $prefs));

        $this->assertSame(2, $total);
    }

    public function test_super_admin_has_two_active_ticket_channels_by_default(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $prefs = $this->ticketPrefs->applicablePreferencesFor($superAdmin);

        $total = array_sum(array_map(fn (array $p) => count($p['channels']), $prefs));

        $this->assertSame(2, $total);
    }

    public function test_reporter_does_not_see_assignee_ticket_types(): void
    {
        $reporter = $this->makeUser('reporter');
        $types = $this->ticketPrefs->applicableTypesFor($reporter);

        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE, $types);
        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE, $types);
    }

    public function test_maintenance_does_not_see_reporter_or_admin_ticket_types(): void
    {
        $maintenance = $this->makeUser('maintenance');
        $types = $this->ticketPrefs->applicableTypesFor($maintenance);

        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, $types);
        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, $types);
    }

    public function test_admin_does_not_see_comment_types_in_ticket_applicable_types(): void
    {
        $admin = $this->makeUser('admin');
        $types = $this->ticketPrefs->applicableTypesFor($admin);

        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER, $types);
        $this->assertNotContains(TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE, $types);
    }

    // ── C. Community counts by role ───────────────────────────────────────────

    public function test_reporter_has_three_applicable_community_types(): void
    {
        $reporter = $this->makeUser('reporter');
        $types = $this->commPrefs->applicableTypesFor($reporter);

        $this->assertCount(3, $types);
        $this->assertContains(CommunityNotificationPreference::TYPE_REPORT_REVIEWED, $types);
        $this->assertContains(CommunityNotificationPreference::TYPE_COMMENT_CREATED, $types);
        $this->assertContains(CommunityNotificationPreference::TYPE_DIGEST_WEEKLY, $types);
    }

    public function test_admin_has_one_applicable_community_type(): void
    {
        $admin = $this->makeUser('admin');
        $types = $this->commPrefs->applicableTypesFor($admin);

        $this->assertCount(1, $types);
        $this->assertContains(CommunityNotificationPreference::TYPE_REPORT_CREATED, $types);
    }

    public function test_super_admin_has_one_applicable_community_type(): void
    {
        $superAdmin = $this->makeUser('super_admin');
        $types = $this->commPrefs->applicableTypesFor($superAdmin);

        $this->assertCount(1, $types);
        $this->assertContains(CommunityNotificationPreference::TYPE_REPORT_CREATED, $types);
    }

    public function test_maintenance_has_zero_applicable_community_types(): void
    {
        $maintenance = $this->makeUser('maintenance');
        $types = $this->commPrefs->applicableTypesFor($maintenance);

        $this->assertCount(0, $types);
    }

    public function test_reporter_community_prefs_all_enabled_by_default(): void
    {
        $reporter = $this->makeUser('reporter');
        $prefs = $this->commPrefs->applicablePreferencesFor($reporter);

        foreach ($prefs as $pref) {
            $this->assertTrue($pref['enabled']);
        }
    }

    // ── D. Profile page comment-type visibility per role ──────────────────────

    public function test_reporter_profile_shows_comment_reporter_label(): void
    {
        $reporter = $this->makeUser('reporter');

        $this->actingAs($reporter)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Comentarios en mis tickets');
    }

    public function test_maintenance_profile_shows_comment_assignee_label(): void
    {
        $maintenance = $this->makeUser('maintenance');

        $this->actingAs($maintenance)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Comentarios en tickets asignados');
    }

    public function test_admin_profile_does_not_show_comment_reporter_or_assignee_labels(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Comentarios en mis tickets')
            ->assertDontSee('Comentarios en tickets asignados');
    }

    public function test_maintenance_profile_shows_all_five_ticket_preference_labels(): void
    {
        $maintenance = $this->makeUser('maintenance');

        $this->actingAs($maintenance)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Tickets asignados a mí')
            ->assertSee('Tickets desasignados')
            ->assertSee('Cambios de estado asignado')
            ->assertSee('Evidencias en tickets asignados')
            ->assertSee('Comentarios en tickets asignados');
    }

    public function test_reporter_profile_shows_all_three_ticket_preference_labels(): void
    {
        $reporter = $this->makeUser('reporter');

        $this->actingAs($reporter)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Cambios de estado de mis tickets')
            ->assertSee('Evidencias en mis tickets')
            ->assertSee('Comentarios en mis tickets');
    }

    public function test_admin_profile_shows_only_ticket_created_label(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Tickets creados')
            ->assertDontSee('Cambios de estado de mis tickets')
            ->assertDontSee('Tickets asignados a mí');
    }

    // ── E. PATCH saves community and ticket comment preferences ───────────────

    public function test_patch_community_disables_preference_for_reporter(): void
    {
        $reporter = $this->makeUser('reporter');

        $this->actingAs($reporter)
            ->patch(route('profile.community-notifications.update'), [
                'preferences' => [
                    CommunityNotificationPreference::TYPE_REPORT_REVIEWED => '0',
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('community_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => CommunityNotificationPreference::TYPE_REPORT_REVIEWED,
            'enabled' => false,
        ]);
    }

    public function test_patch_community_reenables_preference_for_reporter(): void
    {
        $reporter = $this->makeUser('reporter');

        CommunityNotificationPreference::create([
            'user_id' => $reporter->id,
            'type' => CommunityNotificationPreference::TYPE_DIGEST_WEEKLY,
            'enabled' => false,
        ]);

        $this->actingAs($reporter)
            ->patch(route('profile.community-notifications.update'), [
                'preferences' => [
                    CommunityNotificationPreference::TYPE_DIGEST_WEEKLY => '1',
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('community_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => CommunityNotificationPreference::TYPE_DIGEST_WEEKLY,
            'enabled' => true,
        ]);
    }

    public function test_patch_community_ignores_non_applicable_type_for_maintenance(): void
    {
        $maintenance = $this->makeUser('maintenance');

        $this->actingAs($maintenance)
            ->patch(route('profile.community-notifications.update'), [
                'preferences' => [
                    CommunityNotificationPreference::TYPE_REPORT_REVIEWED => '0',
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('community_notification_preferences', [
            'user_id' => $maintenance->id,
            'type' => CommunityNotificationPreference::TYPE_REPORT_REVIEWED,
        ]);
    }

    public function test_patch_ticket_saves_comment_reporter_in_app_channel(): void
    {
        $reporter = $this->makeUser('reporter');

        $this->actingAs($reporter)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER => [
                        TicketNotificationPreference::CHANNEL_IN_APP => '0',
                        TicketNotificationPreference::CHANNEL_FCM => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('ticket_notification_preferences', [
            'user_id' => $reporter->id,
            'type' => TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER,
            'channel' => TicketNotificationPreference::CHANNEL_IN_APP,
            'enabled' => false,
        ]);

        $this->assertTrue(
            $this->ticketPrefs->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER, TicketNotificationPreference::CHANNEL_FCM)
        );
    }

    public function test_patch_ticket_saves_comment_assignee_channels_for_maintenance(): void
    {
        $maintenance = $this->makeUser('maintenance');

        $this->actingAs($maintenance)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE => [
                        TicketNotificationPreference::CHANNEL_FCM => '0',
                        TicketNotificationPreference::CHANNEL_IN_APP => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertFalse(
            $this->ticketPrefs->isEnabled($maintenance, TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM)
        );

        $this->assertTrue(
            $this->ticketPrefs->isEnabled($maintenance, TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE, TicketNotificationPreference::CHANNEL_IN_APP)
        );
    }

    public function test_patch_ticket_ignores_comment_reporter_for_maintenance(): void
    {
        $maintenance = $this->makeUser('maintenance');

        $this->actingAs($maintenance)
            ->patch(route('profile.ticket-notifications.update'), [
                'preferences' => [
                    TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER => [
                        TicketNotificationPreference::CHANNEL_IN_APP => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseMissing('ticket_notification_preferences', [
            'user_id' => $maintenance->id,
            'type' => TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER,
        ]);
    }

    public function test_disabling_one_community_pref_does_not_disable_others(): void
    {
        $reporter = $this->makeUser('reporter');

        $this->actingAs($reporter)
            ->patch(route('profile.community-notifications.update'), [
                'preferences' => [
                    CommunityNotificationPreference::TYPE_REPORT_REVIEWED => '0',
                    CommunityNotificationPreference::TYPE_COMMENT_CREATED => '1',
                    CommunityNotificationPreference::TYPE_DIGEST_WEEKLY => '1',
                ],
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertFalse($this->commPrefs->enabled($reporter, CommunityNotificationPreference::TYPE_REPORT_REVIEWED));
        $this->assertTrue($this->commPrefs->enabled($reporter, CommunityNotificationPreference::TYPE_COMMENT_CREATED));
        $this->assertTrue($this->commPrefs->enabled($reporter, CommunityNotificationPreference::TYPE_DIGEST_WEEKLY));
    }

    // ── F. Regression — no dead cards ────────────────────────────────────────

    public function test_maintenance_profile_shows_ticket_card_but_no_community_card(): void
    {
        $maintenance = $this->makeUser('maintenance');

        $response = $this->actingAs($maintenance)
            ->get(route('profile.edit'))
            ->assertOk();

        $response->assertSee('Preferencias de notificaciones de tickets');
        $response->assertDontSee('Preferencias de notificaciones de Comunidad');
    }

    public function test_reporter_profile_shows_both_community_and_ticket_cards(): void
    {
        $reporter = $this->makeUser('reporter');

        $response = $this->actingAs($reporter)
            ->get(route('profile.edit'))
            ->assertOk();

        $response->assertSee('Preferencias de notificaciones de Comunidad');
        $response->assertSee('Preferencias de notificaciones de tickets');
    }

    public function test_admin_profile_shows_both_community_and_ticket_cards(): void
    {
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertOk();

        $response->assertSee('Preferencias de notificaciones de Comunidad');
        $response->assertSee('Preferencias de notificaciones de tickets');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Sala PrefUI Test',
            'building' => 'Edificio PrefUI',
            'floor' => '1',
            'room_code' => 'PU-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'CatPrefUI-'.Str::lower(Str::random(6)),
            'icon' => 'flag',
            'description' => 'Category for notification prefs UI tests',
        ]);
    }
}
