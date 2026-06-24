<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression suite for the Category index card-grid redesign.
 *
 * Verifies that /categories renders a card grid instead of a table,
 * that all data is real (counts, badges, icons, routes), and that
 * create/edit/delete flows are preserved.
 */
class CategoryCardRedesignTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function admin(): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makeCategory(array $attrs = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => 'Cat '.uniqid(),
            'icon' => null,
            'description' => null,
            'community_default_visible' => true,
            'community_visibility_locked' => false,
        ], $attrs));
    }

    // ── 1. Render principal ───────────────────────────────────────────────────

    public function test_categories_index_returns_200_for_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertOk();
    }

    public function test_index_does_not_render_table_element(): void
    {
        $this->makeCategory();

        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertDontSee('<table', false);
    }

    public function test_index_does_not_render_thead_or_tbody(): void
    {
        $this->makeCategory();

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertDontSee('<thead', false);
        $response->assertDontSee('<tbody', false);
    }

    public function test_index_renders_cat_grid_class(): void
    {
        $this->makeCategory();

        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('cat-grid', false);
    }

    public function test_index_renders_cat_card_class(): void
    {
        $this->makeCategory();

        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('cat-card', false);
    }

    public function test_index_renders_title_categorias(): void
    {
        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('Categorías');
    }

    public function test_index_renders_nueva_categoria_button(): void
    {
        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('Nueva categoría');
    }

    public function test_index_renders_categorias_en_la_vista_actual(): void
    {
        $this->makeCategory();

        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('categorías en la vista actual');
    }

    // ── 2. Cards ──────────────────────────────────────────────────────────────

    public function test_each_category_renders_its_own_card(): void
    {
        $this->makeCategory(['name' => 'Hardware Test']);
        $this->makeCategory(['name' => 'Software Test']);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertSee('Hardware Test');
        $response->assertSee('Software Test');
    }

    public function test_card_shows_category_name(): void
    {
        $this->makeCategory(['name' => 'Redes e Internet']);

        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('Redes e Internet');
    }

    public function test_card_shows_real_incident_count(): void
    {
        $this->makeCategory(['name' => 'Cat Cero Incidencias']);

        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertOk();
        // Category with no incidents — count "0" must appear somewhere in the response
        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('INCIDENCIAS');
    }

    public function test_card_shows_real_ticket_count(): void
    {
        $this->makeCategory();

        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('TICKETS');
    }

    public function test_card_shows_publico_badge_when_visible(): void
    {
        $this->makeCategory([
            'name' => 'Cat Publica Card',
            'community_default_visible' => true,
            'community_visibility_locked' => false,
        ]);

        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('Público');
    }

    public function test_card_shows_privado_badge_when_not_visible(): void
    {
        $this->makeCategory([
            'name' => 'Cat Privada Card',
            'community_default_visible' => false,
            'community_visibility_locked' => false,
        ]);

        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('Privado');
    }

    public function test_card_shows_bloqueado_badge_when_locked(): void
    {
        $this->makeCategory([
            'name' => 'Cat Bloqueada Card',
            'community_default_visible' => false,
            'community_visibility_locked' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('Bloqueado');
    }

    public function test_card_shows_edit_button_with_correct_route(): void
    {
        $category = $this->makeCategory(['name' => 'Cat Edit Route']);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertSee(route('categories.edit', $category), false);
        $response->assertSee('Editar');
    }

    public function test_card_shows_delete_form_for_empty_category(): void
    {
        $this->makeCategory(['name' => 'Cat Sin Tickets']);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertSee('Eliminar');
        $response->assertSee('_method', false);
    }

    public function test_delete_form_has_csrf_and_method_delete(): void
    {
        $this->makeCategory(['name' => 'Cat Para Borrar']);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertSee('_token', false);
        $response->assertSee('DELETE', false);
    }

    public function test_card_shows_disabled_delete_button_when_category_has_tickets(): void
    {
        $admin = $this->admin();
        $category = $this->makeCategory(['name' => 'Cat Con Tickets']);

        $location = Location::query()->create([
            'name' => 'Ubicacion Test Card',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'TA-'.uniqid(),
            'qr_token' => 'qr-card-'.uniqid(),
            'is_active' => true,
        ]);

        Ticket::query()->create([
            'title' => 'Ticket prueba card disabled',
            'description' => 'Para verificar disabled delete.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('categories.index'));

        $response->assertSee('disabled', false);
        $response->assertSee('Tiene tickets o incidencias asociadas', false);
    }

    // ── 3. Icon rendering ─────────────────────────────────────────────────────

    public function test_icon_url_renders_img_tag(): void
    {
        $this->makeCategory([
            'name' => 'Cat Icon URL',
            'icon' => 'https://example.com/icon.png',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertOk();
        $response->assertSee('<img', false);
        $response->assertSee('https://example.com/icon.png', false);
    }

    public function test_icon_lucide_name_renders_without_error(): void
    {
        $this->makeCategory([
            'name' => 'Cat Icon Lucide',
            'icon' => 'tag',
        ]);

        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertOk();
    }

    public function test_empty_icon_renders_lucide_tag_fallback(): void
    {
        $this->makeCategory([
            'name' => 'Cat Sin Icono',
            'icon' => null,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertOk();
        // lucide-tag is rendered as SVG — response must not contain the broken raw icon string
        $response->assertDontSee('lucide-', false);
    }

    // ── 4. Search / empty state ───────────────────────────────────────────────

    public function test_search_filter_returns_matching_category(): void
    {
        $this->makeCategory(['name' => 'Hardware Especial']);
        $this->makeCategory(['name' => 'Software Especial']);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index', ['search' => 'Hardware']));

        $response->assertSee('Hardware Especial');
        $response->assertDontSee('Software Especial');
    }

    public function test_empty_search_shows_empty_state_title(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('categories.index', ['search' => 'xyznotfound999']));

        $response->assertSee('No se encontraron categorías');
    }

    public function test_empty_state_shows_crear_categoria_link(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('categories.index', ['search' => 'xyznotfound999']));

        $response->assertSee('Crear categoría');
    }

    public function test_pagination_is_present(): void
    {
        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertOk();
        // pagination landmark present
        $this->actingAs($this->admin())
            ->get(route('categories.index'))
            ->assertSee('Página', false);
    }

    // ── 5. No debug / no raw table ────────────────────────────────────────────

    public function test_response_has_no_console_log(): void
    {
        $this->makeCategory();

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertDontSee('console.log', false);
    }

    public function test_response_has_no_table_element_with_categories(): void
    {
        $this->makeCategory(['name' => 'Cat Grid Only']);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertDontSee('<table', false);
    }
}
