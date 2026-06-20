<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryTest extends TestCase
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
            'name' => 'Categoria Test '.uniqid(),
            'icon' => null,
            'description' => null,
        ], $attrs));
    }

    // ── Create page ──────────────────────────────────────────────────────────

    public function test_create_page_shows_community_visibility_section(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('categories.create'));

        $response->assertOk();
        $response->assertSee('Visibilidad en Comunidad');
        $response->assertSee('community_default_visible');
        $response->assertSee('community_visibility_locked');
        $response->assertSee('community_visibility_help');
    }

    // ── Store defaults ────────────────────────────────────────────────────────

    public function test_store_category_with_default_community_settings(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('categories.store'), [
                'name' => 'Categoria Publica',
                'community_default_visible' => '1',
                'community_visibility_locked' => '0',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'name' => 'Categoria Publica',
            'community_default_visible' => true,
            'community_visibility_locked' => false,
            'community_visibility_help' => null,
        ]);
    }

    public function test_store_category_without_community_fields_uses_safe_defaults(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('categories.store'), [
                'name' => 'Categoria Sin Community',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'name' => 'Categoria Sin Community',
            'community_default_visible' => true,
            'community_visibility_locked' => false,
        ]);
    }

    // ── Store private locked ──────────────────────────────────────────────────

    public function test_store_category_as_private_locked(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('categories.store'), [
                'name' => 'Categoria Privada Bloqueada',
                'community_default_visible' => '0',
                'community_visibility_locked' => '1',
                'community_visibility_help' => 'Por privacidad, los reportes de esta categoría no aparecen en Comunidad.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'name' => 'Categoria Privada Bloqueada',
            'community_default_visible' => false,
            'community_visibility_locked' => true,
            'community_visibility_help' => 'Por privacidad, los reportes de esta categoría no aparecen en Comunidad.',
        ]);
    }

    public function test_store_category_as_private_not_locked(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('categories.store'), [
                'name' => 'Categoria Privada Editable',
                'community_default_visible' => '0',
                'community_visibility_locked' => '0',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'name' => 'Categoria Privada Editable',
            'community_default_visible' => false,
            'community_visibility_locked' => false,
        ]);
    }

    // ── Edit / update ─────────────────────────────────────────────────────────

    public function test_edit_page_shows_current_community_values(): void
    {
        $category = $this->makeCategory([
            'community_default_visible' => false,
            'community_visibility_locked' => true,
            'community_visibility_help' => 'Texto de ayuda existente.',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.edit', $category));

        $response->assertOk();
        $response->assertSee('Visibilidad en Comunidad');
        $response->assertSee('Texto de ayuda existente.');
    }

    public function test_update_category_community_defaults(): void
    {
        $category = $this->makeCategory([
            'community_default_visible' => true,
            'community_visibility_locked' => false,
        ]);

        $response = $this->actingAs($this->admin())
            ->patch(route('categories.update', $category), [
                'name' => $category->name,
                'community_default_visible' => '0',
                'community_visibility_locked' => '1',
                'community_visibility_help' => 'Categoría sensible — permanece privada.',
            ]);

        $response->assertRedirect(route('categories.edit', $category));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'community_default_visible' => false,
            'community_visibility_locked' => true,
            'community_visibility_help' => 'Categoría sensible — permanece privada.',
        ]);
    }

    public function test_update_can_make_private_locked_category_public(): void
    {
        $category = $this->makeCategory([
            'community_default_visible' => false,
            'community_visibility_locked' => true,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('categories.update', $category), [
                'name' => $category->name,
                'community_default_visible' => '1',
                'community_visibility_locked' => '0',
                'community_visibility_help' => '',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'community_default_visible' => true,
            'community_visibility_locked' => false,
        ]);
    }

    // ── Index badges ──────────────────────────────────────────────────────────

    public function test_index_shows_public_badge_for_public_category(): void
    {
        $this->makeCategory([
            'name' => 'Cat Publica Badge',
            'community_default_visible' => true,
            'community_visibility_locked' => false,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertOk();
        $response->assertSee('Público');
    }

    public function test_index_shows_private_badge_for_private_category(): void
    {
        $this->makeCategory([
            'name' => 'Cat Privada Badge',
            'community_default_visible' => false,
            'community_visibility_locked' => false,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertOk();
        $response->assertSee('Privado');
    }

    public function test_index_shows_locked_badge_for_locked_category(): void
    {
        $this->makeCategory([
            'name' => 'Cat Bloqueada Badge',
            'community_default_visible' => false,
            'community_visibility_locked' => true,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('categories.index'));

        $response->assertOk();
        $response->assertSee('Bloqueado');
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_community_visibility_help_max_length_validation(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('categories.store'), [
                'name' => 'Categoria Validacion',
                'community_visibility_help' => str_repeat('a', 501),
            ]);

        $response->assertSessionHasErrors(['community_visibility_help']);
    }

    public function test_community_visibility_help_at_max_length_passes(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('categories.store'), [
                'name' => 'Categoria Help Max',
                'community_visibility_help' => str_repeat('a', 500),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_update_community_visibility_help_max_length_validation(): void
    {
        $category = $this->makeCategory();

        $response = $this->actingAs($this->admin())
            ->patch(route('categories.update', $category), [
                'name' => $category->name,
                'community_visibility_help' => str_repeat('b', 501),
            ]);

        $response->assertSessionHasErrors(['community_visibility_help']);
    }
}
