<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateLocationQrImage;
use App\Models\Location;
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

        $response = $this->postJson(route('api.locations.store'), $payload);

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

        Queue::assertPushed(GenerateLocationQrImage::class, function (GenerateLocationQrImage $job) use ($locationId, $jobId): bool {
            return $job->locationId === $locationId
                && $job->jobTrackingId === $jobId;
        });
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
            'is_active' => 0,
        ]);
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

        $response = $this->postJson(route('api.locations.regenerate-qr', $location));

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

        Queue::assertPushed(GenerateLocationQrImage::class, function (GenerateLocationQrImage $job) use ($location, $jobId): bool {
            return $job->locationId === $location->id
                && $job->jobTrackingId === $jobId;
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

    private function createLocation(string $roomCode, string $token): Location
    {
        return Location::query()->create([
            'name' => 'Aula ' . $roomCode,
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => $roomCode,
            'qr_token' => $token,
            'qr_image_url' => null,
            'qr_generation_status' => 'pending',
            'qr_last_error' => null,
            'qr_job_id' => null,
            'qr_generated_at' => null,
            'is_active' => true,
        ]);
    }
}
