<?php

namespace Tests\Feature\Web;

use App\Jobs\GenerateLocationQrImage;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporter_does_not_see_admin_links_in_navigation(): void
    {
        $user = $this->createUserWithRole('reporter');

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.index'));

        $response->assertOk();
        $response->assertDontSee('Ubicaciones');
        $response->assertDontSee('Categorias');
    }

    public function test_admin_sees_admin_links_in_navigation(): void
    {
        $user = $this->createUserWithRole('admin');

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.index'));

        $response->assertOk();
        $response->assertSee('Ubicaciones');
        $response->assertSee('Categorias');
    }

    public function test_reporter_and_maintenance_cannot_access_admin_modules(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');

        $this->actingAs($reporter)
            ->get(route('locations.index'))
            ->assertForbidden();

        $this->actingAs($maintenance)
            ->get(route('categories.index'))
            ->assertForbidden();
    }

    public function test_admin_and_super_admin_can_access_admin_modules(): void
    {
        $admin = $this->createUserWithRole('admin');
        $superAdmin = $this->createUserWithRole('super_admin');

        $this->actingAs($admin)
            ->get(route('locations.index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(route('categories.index'))
            ->assertOk();
    }

    public function test_admin_can_create_location_and_queue_qr_generation(): void
    {
        Queue::fake();

        $user = $this->createUserWithRole('admin');

        $response = $this
            ->actingAs($user)
            ->post(route('locations.store'), [
                'name' => 'Laboratorio Redes',
                'building' => 'Edificio B',
                'floor' => '1',
                'room_code' => 'B-101',
                'is_active' => '1',
            ]);

        $location = Location::query()->where('room_code', 'B-101')->first();

        $response->assertRedirect(route('locations.edit', $location));
        $response->assertSessionHas('status', 'Ubicacion creada correctamente. La imagen QR se generara en background.');

        $this->assertDatabaseHas('locations', [
            'room_code' => 'B-101',
            'is_active' => true,
            'qr_generation_status' => 'pending',
        ]);

        Queue::assertPushed(GenerateLocationQrImage::class);
    }

    public function test_admin_can_create_category_from_web_module(): void
    {
        $user = $this->createUserWithRole('admin');

        $response = $this
            ->actingAs($user)
            ->post(route('categories.store'), [
                'name' => 'Audio',
                'icon' => 'volume-2',
                'description' => 'Incidencias de audio y sonido',
            ]);

        $category = Category::query()->where('name', 'Audio')->first();

        $response->assertRedirect(route('categories.edit', $category));
        $response->assertSessionHas('status', 'Categoria creada correctamente.');

        $this->assertDatabaseHas('categories', [
            'name' => 'Audio',
            'icon' => 'volume-2',
        ]);
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
