<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IdempotencyWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_ticket_store_replays_on_duplicate_key(): void
    {
        config(['idempotency.allow_missing' => false]);

        $user = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $payload = [
            'title' => 'Ticket web con idempotencia',
            'description' => 'Descripcion valida para crear ticket desde web.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'high',
            'idempotency_key' => (string) Str::uuid(),
        ];

        $first = $this
            ->actingAs($user)
            ->post(route('tickets.store'), $payload);

        $first->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);

        $second = $this
            ->actingAs($user)
            ->post(route('tickets.store'), $payload);

        $second->assertRedirect();
        $this->assertDatabaseCount('tickets', 1);
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
        return Location::create([
            'name' => 'Aula A',
            'building' => 'Edificio A',
            'floor' => '2',
            'room_code' => 'A-201',
            'qr_token' => 'qr-a-201',
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Electricidad',
            'icon' => 'bolt',
            'description' => 'Incidencias electricas',
        ]);
    }
}
