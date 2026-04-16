<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_categories(): void
    {
        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $this->createCategory('Electricidad');
        $this->createCategory('Red');

        $response = $this->getJson(route('api.categories.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_authenticated_user_can_show_category(): void
    {
        $user = $this->createUserWithRole('maintenance');
        Sanctum::actingAs($user);

        $category = $this->createCategory('Climatizacion');

        $response = $this->getJson(route('api.categories.show', $category));

        $response->assertOk();
        $response->assertJsonPath('data.id', $category->id);
        $response->assertJsonPath('data.name', 'Climatizacion');
    }

    public function test_admin_can_store_category(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Seguridad',
            'icon' => 'shield',
            'description' => 'Incidencias de seguridad',
        ];

        $response = $this->postJson(route('api.categories.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Seguridad');

        $this->assertDatabaseHas('categories', [
            'name' => 'Seguridad',
            'icon' => 'shield',
        ]);
    }

    public function test_reporter_cannot_store_category(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $response = $this->postJson(route('api.categories.store'), [
            'name' => 'No permitido',
        ]);

        $response->assertForbidden();
    }

    public function test_super_admin_can_update_category(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        Sanctum::actingAs($superAdmin);

        $category = $this->createCategory('Mobiliario');

        $response = $this->patchJson(route('api.categories.update', $category), [
            'name' => 'Mobiliario y Equipamiento',
            'icon' => 'cube',
            'description' => 'Actualizado',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Mobiliario y Equipamiento');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Mobiliario y Equipamiento',
        ]);
    }

    public function test_maintenance_cannot_update_category(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        Sanctum::actingAs($maintenance);

        $category = $this->createCategory('Accesibilidad');

        $response = $this->patchJson(route('api.categories.update', $category), [
            'description' => 'Intento no autorizado',
        ]);

        $response->assertForbidden();
    }

    public function test_store_category_validates_unique_name(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $this->createCategory('Audio');

        $response = $this->postJson(route('api.categories.store'), [
            'name' => 'Audio',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
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

    private function createCategory(string $name): Category
    {
        return Category::query()->create([
            'name' => $name,
            'icon' => 'icon-' . strtolower($name),
            'description' => 'Descripcion de ' . $name,
        ]);
    }
}
