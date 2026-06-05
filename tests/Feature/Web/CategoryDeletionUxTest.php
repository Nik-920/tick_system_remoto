<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 3 — UX: cuando el borrado de categoría se bloquea (BUG-1 fix), el motivo
 * debe ser visible para el admin (la vista de edición renderiza session('error')).
 */
class CategoryDeletionUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_category_deletion_shows_error_message_on_edit_view(): void
    {
        $admin = $this->createUserWithRole('admin');
        $location = $this->createLocation();
        $category = $this->createCategory();

        Ticket::query()->create([
            'title' => 'Ticket asociado',
            'description' => 'Bloquea el borrado de la categoria.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('categories.edit', $category))
            ->followingRedirects()
            ->delete(route('categories.destroy', $category));

        $response->assertOk();
        $response->assertSeeText('No se puede eliminar la categoria porque tiene tickets o historial de incidencias asociados.');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
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

    private function createLocation(): Location
    {
        return Location::query()->create([
            'name' => 'Ubicacion UX',
            'building' => 'Edificio UX',
            'floor' => '1',
            'room_code' => 'UX-101',
            'qr_token' => 'qr-ux-101',
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::query()->create([
            'name' => 'Categoria UX',
            'icon' => 'folder',
            'description' => 'Categoria para test de UX de borrado.',
        ]);
    }
}
