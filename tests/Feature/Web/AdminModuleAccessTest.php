<?php

namespace Tests\Feature\Web;

use App\Jobs\GenerateLocationQrImage;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
        $response->assertSee('Categorías');
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

    public function test_admin_can_filter_locations_by_active_state_in_web_module(): void
    {
        $admin = $this->createUserWithRole('admin');

        $activeLocation = Location::query()->create([
            'name' => 'Ubicacion activa web',
            'building' => 'Edificio W',
            'floor' => '1',
            'room_code' => 'W-101',
            'qr_token' => 'qr-w-101-token',
            'is_active' => true,
        ]);

        $inactiveLocation = Location::query()->create([
            'name' => 'Ubicacion inactiva web',
            'building' => 'Edificio W',
            'floor' => '2',
            'room_code' => 'W-102',
            'qr_token' => 'qr-w-102-token',
            'is_active' => false,
        ]);

        $activeResponse = $this
            ->actingAs($admin)
            ->get(route('locations.index', ['is_active' => '1']));

        $activeResponse->assertOk();
        $activeResponse->assertSee($activeLocation->name);
        $activeResponse->assertDontSee($inactiveLocation->name);

        $inactiveResponse = $this
            ->actingAs($admin)
            ->get(route('locations.index', ['is_active' => '0']));

        $inactiveResponse->assertOk();
        $inactiveResponse->assertSee($inactiveLocation->name);
        $inactiveResponse->assertDontSee($activeLocation->name);
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

    public function test_admin_can_delete_location_from_web_module(): void
    {
        $user = $this->createUserWithRole('admin');

        $location = Location::query()->create([
            'name' => 'Ubicacion temporal',
            'building' => 'Edificio Z',
            'floor' => '1',
            'room_code' => 'Z-901',
            'qr_token' => 'qr-z-901-token',
            'qr_image_url' => null,
            'qr_generation_status' => 'pending',
            'qr_last_error' => null,
            'qr_job_id' => null,
            'qr_generated_at' => null,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('locations.destroy', $location));

        $response->assertRedirect(route('locations.index'));
        $response->assertSessionHas('status', 'Ubicacion eliminada correctamente.');

        $this->assertDatabaseMissing('locations', [
            'id' => $location->id,
        ]);
    }

    public function test_admin_cannot_delete_location_with_related_tickets(): void
    {
        $user = $this->createUserWithRole('admin');

        $location = Location::query()->create([
            'name' => 'Ubicacion bloqueada',
            'building' => 'Edificio Z',
            'floor' => '2',
            'room_code' => 'Z-902',
            'qr_token' => 'qr-z-902-token',
            'qr_image_url' => null,
            'qr_generation_status' => 'pending',
            'qr_last_error' => null,
            'qr_job_id' => null,
            'qr_generated_at' => null,
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Categoria temporal web',
            'icon' => 'icon-temp-web',
            'description' => 'Categoria temporal para test de borrado',
        ]);

        Ticket::query()->create([
            'title' => 'Ticket asociado web',
            'description' => 'Debe bloquear eliminacion de ubicacion.',
            'location_id' => $location->id,
            'category_id' => $category->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('locations.destroy', $location));

        $response->assertRedirect(route('locations.edit', $location));
        $response->assertSessionHas('error', 'No se puede eliminar la ubicacion porque tiene tickets o historial de incidencias asociados.');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
        ]);
    }

    public function test_admin_can_create_category_from_web_module_with_icon_upload(): void
    {
        config([
            'services.supabase.storage.domain_buckets.categories' => 'TicketCategoria',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
        ]);
        Storage::fake('public');

        $user = $this->createUserWithRole('admin');

        $response = $this
            ->actingAs($user)
            ->post(route('categories.store'), [
                'name' => 'Video',
                'description' => 'Incidencias de video y proyectores',
                'icon_file' => UploadedFile::fake()->image('video.png', 64, 64),
            ]);

        $category = Category::query()->where('name', 'Video')->first();

        $response->assertRedirect(route('categories.edit', $category));
        $response->assertSessionHas('status', 'Categoria creada correctamente.');

        $this->assertNotNull($category);
        $this->assertIsString($category?->icon);
        $this->assertStringContainsString('/storage/v1/object/public/TicketCategoria/categories/icons/', (string) $category?->icon);

        $relativePath = $this->relativeStoragePath((string) $category?->icon);
        $this->assertNotNull($relativePath);
        Storage::disk('public')->assertExists($relativePath);
    }

    public function test_admin_can_delete_category_from_web_module_and_remove_icon_file(): void
    {
        config([
            'services.supabase.storage.domain_buckets.categories' => 'TicketCategoria',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
        ]);
        Storage::fake('public');

        $user = $this->createUserWithRole('admin');

        $category = Category::query()->create([
            'name' => 'EliminarCategoria',
            'icon' => null,
            'description' => 'Categoria temporal para eliminar',
        ]);

        $iconPath = 'categories/icons/'.$category->id.'/delete-icon.png';
        Storage::disk('public')->put($iconPath, 'icon-content');

        $category->forceFill([
            'icon' => '/storage/v1/object/public/TicketCategoria/'.$iconPath,
        ])->save();

        $response = $this
            ->actingAs($user)
            ->delete(route('categories.destroy', $category));

        $response->assertRedirect(route('categories.index'));
        $response->assertSessionHas('status', 'Categoria eliminada correctamente.');

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
        Storage::disk('public')->assertMissing($iconPath);
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

    private function relativeStoragePath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        $normalizedPath = ltrim($path, '/');
        if (str_starts_with($normalizedPath, 'storage/v1/object/public/')) {
            $bucketAndPath = substr($normalizedPath, strlen('storage/v1/object/public/'));
            if (! is_string($bucketAndPath) || trim($bucketAndPath) === '') {
                return null;
            }

            $parts = explode('/', $bucketAndPath, 2);
            if (! isset($parts[1]) || trim($parts[1]) === '') {
                return null;
            }

            $segments = array_values(array_filter(explode('/', trim($parts[1], '/')), static fn (string $part): bool => $part !== ''));

            return implode('/', array_map('rawurldecode', $segments));
        }

        if (! str_starts_with($normalizedPath, 'storage/')) {
            return null;
        }

        return substr($normalizedPath, strlen('storage/'));
    }
}
