<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationIncidentHistory;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 0 — Red de seguridad para BUG-1 (P0) en la superficie API.
 *
 * Api\CategoryController::destroy tampoco valida relaciones (a diferencia de
 * Api\LocationController, que devuelve 409). Borrar via API una categoria con
 * tickets dispara el cascade y destruye datos.
 *
 * Comportamiento deseado: 409 (como Location) y datos intactos.
 * Hoy ROJO. Excluible: --exclude-group phase1
 */
#[Group('phase1')]
class CategoryDeletionGuardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_delete_category_with_related_tickets_via_api(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation('ZA-201');
        $category = $this->createCategory('Categoria con tickets api');

        $ticket = Ticket::query()->create([
            'title' => 'Ticket API que no debe borrarse en cascada',
            'description' => 'Borrar la categoria por API no debe destruir este ticket.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->deleteJson(route('api.categories.destroy', $category));

        // Contrato deseado (paridad con Location API): conflicto por dependencias.
        $response->assertStatus(409);

        // Invariante de seguridad: nada se destruye.
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
    }

    public function test_admin_cannot_delete_category_with_incident_history_via_api(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation('ZA-202');
        $category = $this->createCategory('Categoria con historial api');

        $history = LocationIncidentHistory::query()->create([
            'location_id' => $location->id,
            'category_id' => $category->id,
            'recurrence_count' => 5,
        ]);

        $response = $this->deleteJson(route('api.categories.destroy', $category));

        $response->assertStatus(409);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('location_incident_history', ['id' => $history->id]);
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

    private function createLocation(string $roomCode): Location
    {
        return Location::query()->create([
            'name' => 'Ubicacion '.$roomCode,
            'building' => 'Edificio ZA',
            'floor' => '2',
            'room_code' => $roomCode,
            'qr_token' => 'qr-'.strtolower($roomCode),
            'is_active' => true,
        ]);
    }

    private function createCategory(string $name): Category
    {
        return Category::query()->create([
            'name' => $name,
            'icon' => 'folder',
            'description' => 'Categoria de prueba para guard de borrado API.',
        ]);
    }
}
