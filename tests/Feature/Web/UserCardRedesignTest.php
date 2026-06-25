<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression suite for the /users card-grid redesign.
 *
 * Verifies that the index renders a card grid instead of a table,
 * that data is real, search/filter work, and create/edit/delete
 * routes are preserved.
 */
class UserCardRedesignTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function ensureRoles(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function superAdmin(array $attrs = []): User
    {
        $this->ensureRoles();
        $user = User::factory()->create($attrs);
        $user->assignRole('super_admin');

        return $user;
    }

    private function userWithRole(string $role, array $attrs = []): User
    {
        $this->ensureRoles();
        $user = User::factory()->create($attrs);
        $user->assignRole($role);

        return $user;
    }

    // ── 1. Render principal ───────────────────────────────────────────────────

    public function test_index_returns_200_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('users.index'))
            ->assertOk();
    }

    public function test_index_does_not_render_table(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('users.index'))
            ->assertDontSee('<table', false);
    }

    public function test_index_does_not_render_thead_or_tbody(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->get(route('users.index'));

        $response->assertDontSee('<thead', false);
        $response->assertDontSee('<tbody', false);
    }

    public function test_index_renders_users_grid(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('users.index'))
            ->assertSee('users-grid', false);
    }

    public function test_index_renders_a_card_per_visible_user(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('reporter');
        $this->userWithRole('maintenance');

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee('users-card', false);
    }

    public function test_index_renders_page_title(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('users.index'))
            ->assertSee('Gestión de usuarios');
    }

    public function test_index_renders_nuevo_usuario_button(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('users.index'))
            ->assertSee('Nuevo usuario');
    }

    public function test_index_renders_summary_chips(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('reporter');
        $this->userWithRole('maintenance');

        $response = $this->actingAs($actor)
            ->get(route('users.index'));

        $response->assertSee('users-summary-chip--reporter', false);
        $response->assertSee('users-summary-chip--admin', false);
        $response->assertSee('users-summary-chip--maintenance', false);
    }

    // ── 2. Cards ──────────────────────────────────────────────────────────────

    public function test_card_shows_user_name(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('reporter', ['name' => 'Maria', 'last_name' => 'Lopez']);

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee('Maria');
    }

    public function test_card_shows_user_email(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('reporter', ['email' => 'maria.lopez@example.test']);

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee('maria.lopez@example.test');
    }

    public function test_card_shows_formatted_created_date(): void
    {
        $actor = $this->superAdmin();
        $user = $this->userWithRole('reporter');
        $user->forceFill(['created_at' => now()->setDate(2026, 6, 23)->setTime(14, 48)])->save();

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee('23/06/2026');
    }

    public function test_card_shows_reporter_badge(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('reporter');

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee('Reporter');
    }

    public function test_card_shows_admin_badge(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('admin');

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee('Admin');
    }

    public function test_card_shows_maintenance_badge(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('maintenance');

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee('Maintenance');
    }

    public function test_card_shows_initials_when_no_avatar(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('reporter', ['name' => 'Pepe', 'avatar_url' => null]);

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee('users-card__avatar', false);
    }

    public function test_card_shows_avatar_image_when_set(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('reporter', [
            'name' => 'Juan',
            'avatar_url' => 'https://example.test/avatar.png',
        ]);

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee('https://example.test/avatar.png', false);
    }

    public function test_card_email_has_break_all_class(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('reporter', ['email' => 'longuser@example.test']);

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee('users-card__email', false);
    }

    public function test_card_edit_links_to_users_edit(): void
    {
        $actor = $this->superAdmin();
        $target = $this->userWithRole('reporter');

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee(route('users.edit', $target), false);
    }

    public function test_card_delete_uses_method_delete_form(): void
    {
        $actor = $this->superAdmin();
        $target = $this->userWithRole('reporter');

        $response = $this->actingAs($actor)->get(route('users.index'));

        $response->assertSee(route('users.destroy', $target), false);
        $response->assertSee('DELETE', false);
    }

    public function test_own_card_shows_tu_instead_of_delete(): void
    {
        $actor = $this->superAdmin();

        $this->actingAs($actor)
            ->get(route('users.index'))
            ->assertSee('Tú');
    }

    // ── 3. Search / filter ────────────────────────────────────────────────────

    public function test_search_by_name_filters_results(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('reporter', ['name' => 'Luisa', 'email' => 'luisa@example.test']);
        $this->userWithRole('reporter', ['name' => 'Carlos', 'email' => 'carlos@example.test']);

        $response = $this->actingAs($actor)
            ->get(route('users.index', ['search' => 'Luisa']));

        $response->assertSee('Luisa');
        $response->assertDontSee('carlos@example.test');
    }

    public function test_search_by_email_filters_results(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('reporter', ['name' => 'Ana', 'email' => 'ana@example.test']);
        $this->userWithRole('reporter', ['name' => 'Pedro', 'email' => 'pedro@example.test']);

        $response = $this->actingAs($actor)
            ->get(route('users.index', ['search' => 'ana@example']));

        $response->assertSee('ana@example.test');
        $response->assertDontSee('pedro@example.test');
    }

    public function test_role_filter_shows_only_reporters(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('reporter', ['email' => 'rep@example.test']);
        $this->userWithRole('maintenance', ['email' => 'maint@example.test']);

        $response = $this->actingAs($actor)
            ->get(route('users.index', ['role' => 'reporter']));

        $response->assertSee('rep@example.test');
        $response->assertDontSee('maint@example.test');
    }

    public function test_role_filter_shows_only_admins(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('admin', ['email' => 'adm@example.test']);
        $this->userWithRole('reporter', ['email' => 'rep2@example.test']);

        $response = $this->actingAs($actor)
            ->get(route('users.index', ['role' => 'admin']));

        $response->assertSee('adm@example.test');
        $response->assertDontSee('rep2@example.test');
    }

    public function test_role_filter_shows_only_maintenance(): void
    {
        $actor = $this->superAdmin();
        $this->userWithRole('maintenance', ['email' => 'maint2@example.test']);
        $this->userWithRole('reporter', ['email' => 'rep3@example.test']);

        $response = $this->actingAs($actor)
            ->get(route('users.index', ['role' => 'maintenance']));

        $response->assertSee('maint2@example.test');
        $response->assertDontSee('rep3@example.test');
    }

    public function test_active_role_tab_has_active_class(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('users.index', ['role' => 'reporter']))
            ->assertSee('users-role-tab--active', false);
    }

    public function test_no_results_shows_empty_state(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('users.index', ['search' => 'xyznonexistent999']))
            ->assertSee('No se encontraron usuarios');
    }

    // ── 4. Seguridad / no-regression ─────────────────────────────────────────

    public function test_admin_cannot_access_index(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_reporter_cannot_access_index(): void
    {
        $this->actingAs($this->userWithRole('reporter'))
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_response_does_not_contain_password_field(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('users.index'))
            ->assertDontSee('"password"', false)
            ->assertDontSee("'password'", false);
    }

    public function test_css_module_contains_users_card_class(): void
    {
        $cssPath = base_path('resources/css/modules/users.css');
        $this->assertFileExists($cssPath);
        $this->assertStringContainsString('.users-card', file_get_contents($cssPath));
    }

    public function test_css_module_contains_users_grid_class(): void
    {
        $cssPath = base_path('resources/css/modules/users.css');
        $this->assertStringContainsString('.users-grid', file_get_contents($cssPath));
    }

    public function test_app_css_imports_users_module(): void
    {
        $appCss = base_path('resources/css/app.css');
        $this->assertStringContainsString("@import './modules/users.css'", file_get_contents($appCss));
    }

    public function test_index_view_has_no_table_element(): void
    {
        $viewPath = resource_path('views/users/index.blade.php');
        $this->assertStringNotContainsString('<table', file_get_contents($viewPath));
        $this->assertStringNotContainsString('<thead', file_get_contents($viewPath));
        $this->assertStringNotContainsString('<tbody', file_get_contents($viewPath));
    }

    public function test_index_view_has_no_php_block(): void
    {
        $viewPath = resource_path('views/users/index.blade.php');
        $this->assertStringNotContainsString('@php', file_get_contents($viewPath));
    }

    public function test_card_partial_exists(): void
    {
        $this->assertFileExists(resource_path('views/users/partials/card.blade.php'));
    }
}
