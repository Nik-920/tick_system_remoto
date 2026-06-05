<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 2 — Decisión documentada: divergencia INTENCIONAL Web/API en el index
 * de catálogos (ubicaciones y categorías).
 *
 *  - API: viewAny abierto a cualquier usuario autenticado (clientes necesitan
 *    poblar selects al crear tickets).
 *  - Web: el módulo de gestión restringe el index a admin/super_admin.
 *
 * Estos tests congelan esa decisión para que no se rompa por accidente. VERDE.
 */
class CatalogIndexAccessParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporter_can_list_locations_and_categories_via_api(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $this->getJson(route('api.locations.index'))->assertOk();
        $this->getJson(route('api.categories.index'))->assertOk();
    }

    public function test_maintenance_can_list_locations_and_categories_via_api(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        Sanctum::actingAs($maintenance);

        $this->getJson(route('api.locations.index'))->assertOk();
        $this->getJson(route('api.categories.index'))->assertOk();
    }

    public function test_reporter_cannot_access_catalog_management_index_via_web(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)->get(route('locations.index'))->assertForbidden();
        $this->actingAs($reporter)->get(route('categories.index'))->assertForbidden();
    }

    public function test_maintenance_cannot_access_catalog_management_index_via_web(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $this->actingAs($maintenance)->get(route('locations.index'))->assertForbidden();
        $this->actingAs($maintenance)->get(route('categories.index'))->assertForbidden();
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
}
