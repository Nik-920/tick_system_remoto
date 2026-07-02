<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CSS/markup regression guard for the redesigned "Visibilidad en Comunidad"
 * component (ticket show §9) and the moderation queue's "Ocultar" reason prompt.
 *
 * Mirrors the layered approach of AdminCommunityModerationCssRegressionTest:
 *   1. Source  — CSS module contains the new component selectors.
 *   2. Import  — app.css pulls in the module that defines them.
 *   3. DOM     — live HTTP responses render the class hooks.
 *   4. Hygiene — touched partials introduce no new @php blocks or debug markers.
 */
class CommunityVisibilityCssRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ── 1. Source-level: CSS module contains the new component ────────────────

    public function test_css_module_contains_comm_visibility_card(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('.comm-visibility-card', $css);
    }

    public function test_css_module_contains_comm_visibility_card_form(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('.comm-visibility-card__form', $css);
    }

    public function test_css_module_contains_comm_visibility_card_history(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('.comm-visibility-card__history', $css);
    }

    public function test_css_module_still_contains_adm_comm_card(): void
    {
        // Guards against the redesign accidentally clobbering the queue's
        // existing, separately-tested component while appending the new one.
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('.adm-comm-card', $css);
    }

    public function test_css_module_has_dark_mode_rules_for_new_component(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('[data-theme="dark"] .comm-visibility-card', $css);
    }

    public function test_css_module_has_mobile_rules_for_new_component(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*640px\)\s*\{[^}]*\.comm-visibility-card/s',
            $css,
        );
    }

    // ── 2. Import-level ─────────────────────────────────────────────────────

    public function test_app_css_imports_community_moderation_module(): void
    {
        $appCss = file_get_contents(base_path('resources/css/app.css'));

        $this->assertStringContainsString('modules/community-moderation.css', $appCss);
    }

    // ── 3. DOM-level: ticket show renders the new component ───────────────────

    public function test_ticket_show_renders_comm_visibility_card(): void
    {
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($this->adminUser())
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('comm-visibility-card', false);
    }

    public function test_ticket_show_renders_comm_visibility_card_form(): void
    {
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($this->adminUser())
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('comm-visibility-card__form', false);
    }

    public function test_ticket_show_renders_status_pill_for_visible_ticket(): void
    {
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($this->adminUser())
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('comm-visibility-card__status--visible', false);
    }

    public function test_ticket_show_renders_status_pill_for_hidden_ticket(): void
    {
        $ticket = $this->makeHiddenTicket();

        $this->actingAs($this->adminUser())
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('comm-visibility-card__status--hidden', false);
    }

    public function test_ticket_show_renders_history_timeline_when_logs_exist(): void
    {
        $admin = $this->adminUser();
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'CSS regression history check']);

        $this->actingAs($admin)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('comm-visibility-card__history-list', false)
            ->assertSee('comm-visibility-card__history-item--hidden', false);
    }

    public function test_ticket_show_hide_form_still_has_csrf_and_patch_method(): void
    {
        $ticket = $this->makeVisibleTicket();

        $response = $this->actingAs($this->adminUser())
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee(route('tickets.community.hide', $ticket), false);
        $response->assertSee('_token', false);
        $response->assertSee('_method', false);
        $response->assertSee('name="reason"', false);
    }

    // ── 4. DOM-level: moderation queue "Ocultar" form triggers the reason prompt ──

    public function test_moderation_queue_ocultar_form_triggers_reason_prompt(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->adminUser())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('data-prompt="¿Ocultar ticket? Ingresa el motivo:"', false);
    }

    // ── 5. Hygiene: no new @php blocks or debug markers in touched partials ───

    public function test_community_visibility_partial_has_no_php_blocks(): void
    {
        $blade = file_get_contents(
            base_path('resources/views/tickets/partials/show/community-visibility.blade.php'),
        );

        $this->assertStringNotContainsString('@php', $blade);
    }

    public function test_moderation_card_partial_has_no_php_blocks(): void
    {
        $blade = file_get_contents(
            base_path('resources/views/community/admin/moderation/partials/card.blade.php'),
        );

        $this->assertStringNotContainsString('@php', $blade);
    }

    public function test_touched_partials_have_no_debug_markers(): void
    {
        $files = [
            base_path('resources/views/tickets/partials/show/community-visibility.blade.php'),
            base_path('resources/views/community/admin/moderation/partials/card.blade.php'),
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            foreach (['dd(', 'dump(', 'console.log(', 'TODO', 'FIXME'] as $marker) {
                $this->assertStringNotContainsString($marker, $contents, "{$file} contains debug marker '{$marker}'.");
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function adminUser(): User
    {
        return $this->createUserWithRole('admin');
    }

    private function createUserWithRole(string $roleName): User
    {
        $this->ensureRolesExist();
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $name) {
            Role::findOrCreate($name, 'web');
        }
    }

    private function makeVisibleTicket(string $title = 'Visible ticket CSS test'): Ticket
    {
        $reporter = $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'CSS regression test ticket.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeHiddenTicket(string $title = 'Hidden ticket CSS test'): Ticket
    {
        $reporter = $this->createUserWithRole('reporter');
        $moderator = $this->adminUser();

        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'CSS regression test ticket (hidden).',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => false,
        ]);

        $ticket->forceFill([
            'community_hidden_at' => now(),
            'community_hidden_by' => $moderator->id,
            'community_visibility_reason' => 'Motivo CSS regression',
        ])->save();

        return $ticket;
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-CSSVIS-'.Str::lower(Str::random(5)),
            'icon' => 'wrench',
            'description' => 'Categoría para CSS regression tests de visibilidad.',
        ]);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Sala CSSVIS-'.Str::upper(Str::random(4)),
            'building' => 'Edificio CSS Test',
            'floor' => '1',
            'room_code' => 'CSSVIS-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-cssvis-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }
}
