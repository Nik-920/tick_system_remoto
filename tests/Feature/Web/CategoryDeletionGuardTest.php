<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\LocationIncidentHistory;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 0 — Red de seguridad para BUG-1 (P0).
 *
 * CategoryController::destroy NO valida relaciones antes de borrar y la FK
 * tickets.category_id es cascadeOnDelete. Borrar una categoria con tickets
 * destruye en cascada todos sus tickets, su state_history, media y embeddings.
 *
 * Estos tests afirman el comportamiento DESEADO (categoria y tickets sobreviven).
 * Hoy estan en ROJO: documentan el bug y son el criterio de aceptacion de la Fase 1.
 *
 * Excluibles de CI mientras tanto: --exclude-group phase1
 */
#[Group('phase1')]
class CategoryDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_delete_category_with_related_tickets(): void
    {
        $admin = $this->createUserWithRole('admin');
        $location = $this->createLocation('Z-201');
        $category = $this->createCategory('Categoria con tickets web');

        $ticket = Ticket::query()->create([
            'title' => 'Ticket que NO debe borrarse en cascada',
            'description' => 'Borrar la categoria no debe destruir este ticket.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $this->actingAs($admin)
            ->delete(route('categories.destroy', $category));

        // Invariante de seguridad (independiente del wording del fix):
        // ni la categoria ni el ticket pueden desaparecer.
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
    }

    public function test_admin_cannot_delete_category_with_incident_history(): void
    {
        $admin = $this->createUserWithRole('admin');
        $location = $this->createLocation('Z-202');
        $category = $this->createCategory('Categoria con historial web');

        $history = LocationIncidentHistory::query()->create([
            'location_id' => $location->id,
            'category_id' => $category->id,
            'recurrence_count' => 3,
        ]);

        $this->actingAs($admin)
            ->delete(route('categories.destroy', $category));

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
            'building' => 'Edificio Z',
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
            'description' => 'Categoria de prueba para guard de borrado.',
        ]);
    }
}
