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
 * CSS regression guard for /admin/community/moderation.
 *
 * Root cause that prompted this test:
 *   community-moderation.css used CSS custom properties (--color-surface,
 *   --space-6, etc.) without fallback values, and those variables were not
 *   defined in tokens/base.css.  The compiled CSS contained the classes but
 *   every layout-sensitive property resolved to its initial value, making the
 *   page appear as unstyled text.
 *
 * These tests prevent that regression at three layers:
 *   1. Source — CSS module file contains required selectors.
 *   2. Token — tokens/base.css defines the variables the module depends on.
 *   3. Import — app.css imports the module.
 *   4. DOM — live HTTP response contains the expected HTML class hooks.
 */
class AdminCommunityModerationCssRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ── 1. Source-level: CSS module contains required selectors ───────────────

    public function test_css_module_file_exists(): void
    {
        $this->assertFileExists(
            base_path('resources/css/modules/community-moderation.css'),
        );
    }

    public function test_css_module_contains_adm_comm_card(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('.adm-comm-card', $css);
    }

    public function test_css_module_contains_adm_comm_list(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('.adm-comm-list', $css);
    }

    public function test_css_module_contains_adm_comm_card_topbar(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('.adm-comm-card__topbar', $css);
    }

    public function test_css_module_contains_adm_comm_card_actions(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('.adm-comm-card__actions', $css);
    }

    public function test_css_module_contains_adm_comm_btn(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('.adm-comm-btn', $css);
    }

    public function test_css_module_contains_comm_mod_kpi_card(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('.comm-mod-kpi-card', $css);
    }

    public function test_css_module_contains_adm_comm_empty(): void
    {
        $css = file_get_contents(base_path('resources/css/modules/community-moderation.css'));

        $this->assertStringContainsString('.adm-comm-empty', $css);
    }

    // ── 2. Token-level: base.css defines the CSS custom properties ────────────

    public function test_tokens_define_color_surface(): void
    {
        $tokens = file_get_contents(base_path('resources/css/tokens/base.css'));

        $this->assertStringContainsString('--color-surface:', $tokens,
            '--color-surface must be defined in tokens/base.css; community-moderation.css uses it without a fallback value.');
    }

    public function test_tokens_define_color_border(): void
    {
        $tokens = file_get_contents(base_path('resources/css/tokens/base.css'));

        $this->assertStringContainsString('--color-border:', $tokens,
            '--color-border must be defined in tokens/base.css; community-moderation.css uses it without a fallback value.');
    }

    public function test_tokens_define_color_bg(): void
    {
        $tokens = file_get_contents(base_path('resources/css/tokens/base.css'));

        $this->assertStringContainsString('--color-bg:', $tokens);
    }

    public function test_tokens_define_color_text_primary(): void
    {
        $tokens = file_get_contents(base_path('resources/css/tokens/base.css'));

        $this->assertStringContainsString('--color-text-primary:', $tokens);
    }

    public function test_tokens_define_space_6(): void
    {
        $tokens = file_get_contents(base_path('resources/css/tokens/base.css'));

        $this->assertStringContainsString('--space-6:', $tokens,
            '--space-6 must be defined in tokens/base.css; community-moderation.css uses it without a fallback value.');
    }

    public function test_tokens_define_space_4(): void
    {
        $tokens = file_get_contents(base_path('resources/css/tokens/base.css'));

        $this->assertStringContainsString('--space-4:', $tokens);
    }

    public function test_tokens_define_space_3(): void
    {
        $tokens = file_get_contents(base_path('resources/css/tokens/base.css'));

        $this->assertStringContainsString('--space-3:', $tokens);
    }

    public function test_tokens_define_radius_md(): void
    {
        $tokens = file_get_contents(base_path('resources/css/tokens/base.css'));

        $this->assertStringContainsString('--radius-md:', $tokens);
    }

    // ── 3. Import-level: app.css imports the community-moderation module ──────

    public function test_app_css_imports_community_moderation_module(): void
    {
        $appCss = file_get_contents(base_path('resources/css/app.css'));

        $this->assertStringContainsString(
            'modules/community-moderation.css',
            $appCss,
            'app.css must @import community-moderation.css so the module is included in the Vite build.',
        );
    }

    public function test_app_css_imports_tokens_base(): void
    {
        $appCss = file_get_contents(base_path('resources/css/app.css'));

        $this->assertStringContainsString(
            'tokens/base.css',
            $appCss,
            'app.css must import tokens/base.css before any module that depends on its variables.',
        );
    }

    // ── 4. DOM-level: live HTTP response contains card structure ──────────────

    public function test_moderation_page_returns_200(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.community.moderation'))
            ->assertOk();
    }

    public function test_dom_has_comm_mod_page_wrapper(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('comm-mod-page', false);
    }

    public function test_dom_has_adm_comm_list(): void
    {
        $this->actingAs($this->adminUser())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('adm-comm-list', false);
    }

    public function test_dom_has_adm_comm_card_when_tickets_exist(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->adminUser())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('adm-comm-card', false);
    }

    public function test_dom_has_adm_comm_card_topbar_when_tickets_exist(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->adminUser())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('adm-comm-card__topbar', false);
    }

    public function test_dom_has_adm_comm_card_actions_when_tickets_exist(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->adminUser())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('adm-comm-card__actions', false);
    }

    public function test_dom_has_no_table_element(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->adminUser())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertDontSee('<table', false);
    }

    public function test_dom_has_no_thead(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->adminUser())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertDontSee('<thead', false);
    }

    public function test_dom_has_no_tbody(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->adminUser())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertDontSee('<tbody', false);
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

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-CSS-'.Str::lower(Str::random(5)),
            'icon' => 'wrench',
            'description' => 'Categoría para CSS regression tests.',
        ]);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Sala CSS-'.Str::upper(Str::random(4)),
            'building' => 'Edificio CSS Test',
            'floor' => '1',
            'room_code' => 'CSS-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-css-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }
}
