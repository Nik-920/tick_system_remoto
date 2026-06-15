<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateLocationQrImage;
use App\Models\Category;
use App\Models\Location;
use App\Models\LocationIncidentHistory;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LocationApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_locations(): void
    {
        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $this->createLocation('A-101', 'qr-a-101-token');
        $this->createLocation('A-102', 'qr-a-102-token');

        $response = $this->getJson(route('api.locations.index'));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_authenticated_user_can_filter_locations_by_active_state(): void
    {
        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $activeLocation = $this->createLocation('A-103', 'qr-a-103-token', true);
        $inactiveLocation = $this->createLocation('A-104', 'qr-a-104-token', false);

        $activeResponse = $this->getJson(route('api.locations.index', ['is_active' => 1]));

        $activeResponse->assertOk();
        $activeResponse->assertJsonCount(1, 'data');
        $activeResponse->assertJsonPath('data.0.id', $activeLocation->id);

        $inactiveResponse = $this->getJson(route('api.locations.index', ['is_active' => 0]));

        $inactiveResponse->assertOk();
        $inactiveResponse->assertJsonCount(1, 'data');
        $inactiveResponse->assertJsonPath('data.0.id', $inactiveLocation->id);
    }

    public function test_authenticated_user_can_show_location(): void
    {
        $user = $this->createUserWithRole('maintenance');
        Sanctum::actingAs($user);

        $location = $this->createLocation('A-201', 'qr-a-201-token');

        $response = $this->getJson(route('api.locations.show', $location));

        $response->assertOk();
        $response->assertJsonPath('data.id', $location->id);
        $response->assertJsonPath('data.room_code', 'A-201');
    }

    public function test_admin_can_store_location_and_dispatch_qr_job(): void
    {
        Queue::fake();

        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Aula Administrativa',
            'building' => 'Edificio D',
            'floor' => '4',
            'room_code' => 'D-401',
            'is_active' => true,
        ];

        $correlationId = 'corr-location-store-001';

        $response = $this
            ->withHeader('X-Correlation-Id', $correlationId)
            ->postJson(route('api.locations.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.room_code', 'D-401');
        $response->assertJsonPath('data.qr_image_url', null);
        $response->assertJsonPath('data.qr_generation_status', 'pending');

        $token = (string) $response->json('data.qr_token');
        $this->assertNotSame('', $token);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{6,128}$/', $token);

        $jobId = (string) $response->json('data.qr_job_id');
        $this->assertNotSame('', $jobId);

        $locationId = (string) $response->json('data.id');
        $this->assertDatabaseHas('locations', [
            'id' => $locationId,
            'room_code' => 'D-401',
            'qr_image_url' => null,
            'qr_generation_status' => 'pending',
            'qr_job_id' => $jobId,
        ]);

        Queue::assertPushedOn('media', GenerateLocationQrImage::class);
        Queue::assertPushed(GenerateLocationQrImage::class, function (GenerateLocationQrImage $job) use ($locationId, $jobId): bool {
            return $job->locationId === $locationId
                && $job->jobTrackingId === $jobId
                && $job->correlationId === 'corr-location-store-001';
        });
    }

    public function test_store_location_casts_is_active_string_one_to_boolean_true(): void
    {
        Queue::fake();

        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Ubicacion Activa Bool',
            'building' => 'Edificio Bool',
            'floor' => '1',
            'room_code' => 'BOOL-101',
            'is_active' => '1',
        ];

        $response = $this->postJson(route('api.locations.store'), $payload);

        $response->assertCreated();

        $locationId = (string) $response->json('data.id');
        $location = Location::query()->findOrFail($locationId);

        $this->assertTrue($location->is_active);
    }

    public function test_store_location_casts_is_active_string_zero_to_boolean_false(): void
    {
        Queue::fake();

        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Ubicacion Inactiva Bool',
            'building' => 'Edificio Bool',
            'floor' => '2',
            'room_code' => 'BOOL-102',
            'is_active' => '0',
        ];

        $response = $this->postJson(route('api.locations.store'), $payload);

        $response->assertCreated();

        $locationId = (string) $response->json('data.id');
        $location = Location::query()->findOrFail($locationId);

        $this->assertFalse($location->is_active);
    }

    public function test_store_same_name_building_floor_different_room_code_creates_successfully(): void
    {
        Queue::fake();

        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        Location::query()->create([
            'name' => 'Laboratorio 3',
            'building' => 'Ingenieria Laboratorios',
            'floor' => '1',
            'room_code' => 'ING-2-203',
            'qr_token' => 'qr-ing-2-203',
            'is_active' => true,
        ]);

        // ING-2-204 is a different physical space. A distinct room_code must bypass
        // the similarity check entirely and allow creation without confirmation.
        $payload = [
            'name' => 'Laboratorio 3',
            'building' => 'Ingenieria Laboratorios',
            'floor' => '1',
            'room_code' => 'ING-2-204',
            'is_active' => true,
        ];

        $response = $this->postJson(route('api.locations.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.room_code', 'ING-2-204');
        $this->assertDatabaseHas('locations', ['room_code' => 'ING-2-204']);
        Queue::assertPushedOn('media', GenerateLocationQrImage::class);
    }

    public function test_store_allows_similar_location_when_confirmed(): void
    {
        Queue::fake();

        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        Location::query()->create([
            'name' => 'Laboratorio 3',
            'building' => 'Ingenieria Laboratorios',
            'floor' => '1',
            'room_code' => 'ING-2-203',
            'qr_token' => 'qr-ing-2-203',
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Laboratorio 3',
            'building' => 'Ingenieria Laboratorios',
            'floor' => '1',
            'room_code' => 'ING-2-204',
            'is_active' => true,
            'confirm_similar_location' => true,
        ];

        $response = $this->postJson(route('api.locations.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.room_code', 'ING-2-204');
    }

    public function test_store_allows_same_name_different_building(): void
    {
        Queue::fake();

        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        Location::query()->create([
            'name' => 'Laboratorio 3',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-101',
            'qr_token' => 'qr-a-101',
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Laboratorio 3',
            'building' => 'Edificio B',
            'floor' => '1',
            'room_code' => 'B-101',
        ];

        $response = $this->postJson(route('api.locations.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.room_code', 'B-101');
    }

    public function test_store_allows_same_name_different_floor(): void
    {
        Queue::fake();

        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        Location::query()->create([
            'name' => 'Laboratorio 3',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-201',
            'qr_token' => 'qr-a-201',
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Laboratorio 3',
            'building' => 'Edificio A',
            'floor' => '2',
            'room_code' => 'A-202',
        ];

        $response = $this->postJson(route('api.locations.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.room_code', 'A-202');
    }

    public function test_store_case_insensitive_name_match_with_different_room_code_creates_successfully(): void
    {
        Queue::fake();

        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        Location::query()->create([
            'name' => 'Laboratorio 3',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-301',
            'qr_token' => 'qr-a-301',
            'is_active' => true,
        ]);

        // Different room_code (A-302 vs A-301) → not a duplicate regardless of name casing.
        $payload = [
            'name' => 'LABORATORIO 3',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-302',
        ];

        $response = $this->postJson(route('api.locations.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.room_code', 'A-302');
        $this->assertDatabaseHas('locations', ['room_code' => 'A-302']);
    }

    public function test_store_duplicate_room_code_with_confirm_flag_still_fails(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $this->createLocation('DUP-001', 'qr-dup-001-token');

        // confirm_similar_location=true must not bypass the unique validation rule.
        $response = $this->postJson(route('api.locations.store'), [
            'name' => 'Otra ubicacion',
            'building' => 'Edificio X',
            'floor' => '1',
            'room_code' => 'DUP-001',
            'confirm_similar_location' => true,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['room_code']);
        $this->assertDatabaseCount('locations', 1);
    }

    public function test_reporter_cannot_store_location(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $payload = [
            'name' => 'Aula Restringida',
            'building' => 'Edificio E',
            'room_code' => 'E-101',
        ];

        $response = $this->postJson(route('api.locations.store'), $payload);

        $response->assertForbidden();
    }

    public function test_super_admin_can_update_location(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        Sanctum::actingAs($superAdmin);

        $location = $this->createLocation('F-101', 'qr-f-101-token');

        $response = $this->patchJson(route('api.locations.update', $location), [
            'name' => 'Laboratorio Actualizado',
            'building' => 'Edificio F',
            'floor' => '2',
            'room_code' => 'F-102',
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Laboratorio Actualizado');
        $response->assertJsonPath('data.room_code', 'F-102');
        $response->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'room_code' => 'F-102',
        ]);
        $location->refresh();
        $this->assertFalse($location->is_active);
    }

    public function test_update_location_casts_is_active_string_zero_to_boolean_false(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation('BOOL-201', 'qr-bool-201-token', true);

        $response = $this->patchJson(route('api.locations.update', $location), [
            'is_active' => '0',
        ]);

        $response->assertOk();

        $location->refresh();
        $this->assertFalse($location->is_active);
    }

    public function test_update_location_casts_is_active_string_one_to_boolean_true(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation('BOOL-202', 'qr-bool-202-token', false);

        $response = $this->patchJson(route('api.locations.update', $location), [
            'is_active' => '1',
        ]);

        $response->assertOk();

        $location->refresh();
        $this->assertTrue($location->is_active);
    }

    public function test_update_ignores_self_when_checking_similar_locations(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = Location::query()->create([
            'name' => 'Laboratorio 7',
            'building' => 'Edificio S',
            'floor' => '1',
            'room_code' => 'S-101',
            'qr_token' => 'qr-s-101',
            'is_active' => true,
        ]);

        $response = $this->patchJson(route('api.locations.update', $location), [
            'name' => 'Laboratorio 7',
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_active', false);
    }

    public function test_update_renaming_to_similar_name_with_different_room_code_succeeds(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        Location::query()->create([
            'name' => 'Laboratorio 8',
            'building' => 'Edificio T',
            'floor' => '1',
            'room_code' => 'T-101',
            'qr_token' => 'qr-t-101',
            'is_active' => true,
        ]);

        $location = Location::query()->create([
            'name' => 'Laboratorio 9',
            'building' => 'Edificio T',
            'floor' => '1',
            'room_code' => 'T-102',
            'qr_token' => 'qr-t-102',
            'is_active' => true,
        ]);

        // T-102 and T-101 are different room_codes → rename is allowed without conflict.
        $response = $this->patchJson(route('api.locations.update', $location), [
            'name' => 'Laboratorio 8',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Laboratorio 8');
        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => 'Laboratorio 8',
        ]);
    }

    public function test_update_to_room_code_of_another_location_fails_with_validation_error(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $this->createLocation('U-101', 'qr-u-101-token');
        $location = $this->createLocation('U-102', 'qr-u-102-token');

        // Attempting to steal U-101's room_code must fail at validation (unique rule).
        $response = $this->patchJson(route('api.locations.update', $location), [
            'room_code' => 'U-101',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['room_code']);
        $this->assertDatabaseHas('locations', ['id' => $location->id, 'room_code' => 'U-102']);
    }

    public function test_reporter_cannot_update_location(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation('G-101', 'qr-g-101-token');

        $response = $this->patchJson(route('api.locations.update', $location), [
            'name' => 'Cambio no permitido',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_regenerate_qr_and_dispatch_job(): void
    {
        Queue::fake();

        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation('R-101', 'qr-r-101-token');
        $location->forceFill([
            'qr_image_url' => 'https://example.test/storage/qr-codes/legacy.png',
            'qr_generation_status' => 'ready',
            'qr_last_error' => 'Error previo',
            'qr_generated_at' => now()->subHour(),
        ])->save();

        $correlationId = 'corr-location-regenerate-001';

        $response = $this
            ->withHeader('X-Correlation-Id', $correlationId)
            ->postJson(route('api.locations.regenerate-qr', $location));

        $response->assertAccepted();
        $response->assertJsonPath('data.id', $location->id);
        $response->assertJsonPath('data.qr_generation_status', 'pending');
        $response->assertJsonPath('data.qr_last_error', null);

        $jobId = (string) $response->json('data.qr_job_id');
        $this->assertNotSame('', $jobId);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'qr_generation_status' => 'pending',
            'qr_last_error' => null,
            'qr_job_id' => $jobId,
        ]);

        Queue::assertPushedOn('media', GenerateLocationQrImage::class);
        Queue::assertPushed(GenerateLocationQrImage::class, function (GenerateLocationQrImage $job) use ($location, $jobId, $correlationId): bool {
            return $job->locationId === $location->id
            && $job->jobTrackingId === $jobId
            && $job->correlationId === $correlationId;
        });
    }

    public function test_reporter_cannot_regenerate_qr(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation('R-102', 'qr-r-102-token');

        $response = $this->postJson(route('api.locations.regenerate-qr', $location));

        $response->assertForbidden();
    }

    public function test_store_location_validates_unique_room_code(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $this->createLocation('H-101', 'qr-h-101-token');

        $payload = [
            'name' => 'Aula duplicada',
            'building' => 'Edificio H',
            'room_code' => 'H-101',
        ];

        $response = $this->postJson(route('api.locations.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['room_code']);
    }

    public function test_store_location_validates_room_code_format(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $payload = [
            'name' => 'Aula invalida',
            'building' => 'Edificio H',
            'room_code' => 'H 101',
        ];

        $response = $this->postJson(route('api.locations.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['room_code']);
    }

    public function test_admin_can_delete_location_without_dependencies(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation('DEL-101', 'qr-del-101-token');

        $response = $this->deleteJson(route('api.locations.destroy', $location));

        $response->assertOk();
        $response->assertJsonPath('message', 'Ubicacion eliminada correctamente.');

        $this->assertDatabaseMissing('locations', [
            'id' => $location->id,
        ]);
    }

    public function test_delete_location_is_blocked_when_has_tickets(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation('DEL-201', 'qr-del-201-token');
        $category = $this->createCategory('Infraestructura');

        Ticket::query()->create([
            'title' => 'Ticket asociado',
            'description' => 'No debe permitir eliminar ubicacion con tickets.',
            'location_id' => $location->id,
            'category_id' => $category->id,
        ]);

        $response = $this->deleteJson(route('api.locations.destroy', $location));

        $response->assertStatus(409);
        $response->assertJsonPath('data.tickets_count', 1);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
        ]);
    }

    public function test_delete_location_is_blocked_when_has_incident_history(): void
    {
        $admin = $this->createUserWithRole('admin');
        Sanctum::actingAs($admin);

        $location = $this->createLocation('DEL-301', 'qr-del-301-token');
        $category = $this->createCategory('Laboratorio');

        LocationIncidentHistory::query()->create([
            'location_id' => $location->id,
            'category_id' => $category->id,
            'recurrence_count' => 1,
            'avg_resolution_time' => '00:10:00',
        ]);

        $response = $this->deleteJson(route('api.locations.destroy', $location));

        $response->assertStatus(409);
        $response->assertJsonPath('data.incident_history_count', 1);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
        ]);
    }

    public function test_reporter_cannot_delete_location(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation('DEL-401', 'qr-del-401-token');

        $response = $this->deleteJson(route('api.locations.destroy', $location));

        $response->assertForbidden();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
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

    private function createLocation(string $roomCode, string $token, bool $isActive = true): Location
    {
        return Location::query()->create([
            'name' => 'Aula '.$roomCode,
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => $roomCode,
            'qr_token' => $token,
            'qr_image_url' => null,
            'qr_generation_status' => 'pending',
            'qr_last_error' => null,
            'qr_job_id' => null,
            'qr_generated_at' => null,
            'is_active' => $isActive,
        ]);
    }

    private function createCategory(string $name): Category
    {
        return Category::query()->create([
            'name' => $name,
            'icon' => 'icon-'.strtolower($name),
            'description' => 'Descripcion de '.$name,
        ]);
    }
}
