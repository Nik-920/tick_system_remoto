<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_idempotency_replays_ticket_store_response(): void
    {
        config(['idempotency.allow_missing' => false]);

        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $payload = [
            'title' => 'Ticket via API con idempotencia',
            'description' => 'Descripcion suficientemente larga para crear ticket via API.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'high',
        ];

        $key = (string) Str::uuid();

        $first = $this
            ->withHeader('Idempotency-Key', $key)
            ->postJson(route('api.tickets.store'), $payload);

        $first->assertCreated();
        $first->assertHeader('Idempotency-Status', 'stored');
        $this->assertDatabaseCount('tickets', 1);

        $second = $this
            ->withHeader('Idempotency-Key', $key)
            ->postJson(route('api.tickets.store'), $payload);

        $second->assertCreated();
        $second->assertHeader('Idempotency-Status', 'replayed');
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_idempotency_conflicts_on_payload_mismatch(): void
    {
        config(['idempotency.allow_missing' => false]);

        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $key = (string) Str::uuid();

        $this
            ->withHeader('Idempotency-Key', $key)
            ->postJson(route('api.tickets.store'), [
                'title' => 'Ticket base',
                'description' => 'Descripcion base para ticket.',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'priority' => 'low',
            ])
            ->assertCreated();

        $response = $this
            ->withHeader('Idempotency-Key', $key)
            ->postJson(route('api.tickets.store'), [
                'title' => 'Ticket diferente',
                'description' => 'Descripcion distinta para forzar conflicto.',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'priority' => 'high',
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
    }

    public function test_idempotency_conflicts_when_request_is_processing(): void
    {
        config(['idempotency.allow_missing' => false]);

        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $payload = [
            'title' => 'Ticket en proceso',
            'description' => 'Descripcion base para el test de processing.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
        ];

        $key = (string) Str::uuid();
        $requestHash = $this->buildRequestHash('POST', '/api/tickets', [], $payload, $user->id);

        IdempotencyKey::create([
            'key' => $key,
            'user_id' => $user->id,
            'route' => 'api.tickets.store',
            'method' => 'POST',
            'path' => '/api/tickets',
            'request_hash' => $requestHash,
            'status' => IdempotencyKey::STATUS_PROCESSING,
            'locked_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this
            ->withHeader('Idempotency-Key', $key)
            ->postJson(route('api.tickets.store'), $payload);

        $response->assertStatus(409);
        $response->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
    }

    public function test_idempotency_requires_key_when_missing(): void
    {
        config(['idempotency.allow_missing' => false]);

        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $payload = [
            'title' => 'Ticket sin idempotency key',
            'description' => 'Descripcion valida para probar missing key.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'low',
        ];

        $response = $this->postJson(route('api.tickets.store'), $payload);

        $response->assertStatus(428);
        $response->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    public function test_idempotency_rejects_oversized_key(): void
    {
        config([
            'idempotency.allow_missing' => false,
            'idempotency.max_key_length' => 8,
        ]);

        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $payload = [
            'title' => 'Ticket con key larga',
            'description' => 'Descripcion valida para probar key demasiado larga.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'low',
        ];

        $response = $this
            ->withHeader('Idempotency-Key', str_repeat('a', 12))
            ->postJson(route('api.tickets.store'), $payload);

        $response->assertStatus(400);
        $response->assertJsonPath('code', 'IDEMPOTENCY_KEY_INVALID');
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
            'name' => 'Laboratorio API',
            'building' => 'Edificio B',
            'floor' => '1',
            'room_code' => 'B-101',
            'qr_token' => 'qr-b-101',
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Red',
            'icon' => 'wifi',
            'description' => 'Incidencias de conectividad',
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     */
    private function buildRequestHash(
        string $method,
        string $path,
        array $query,
        array $body,
        string $userId,
    ): string {
        $payload = [
            'method' => $method,
            'path' => $path,
            'query' => $this->normalizeInput($query),
            'body' => $this->normalizeInput($body),
            'user_id' => $userId,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = serialize($payload);
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input): array
    {
        $normalized = [];
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = $this->normalizeInput($value);

                continue;
            }

            $normalized[$key] = $value;
        }

        if ($this->isAssoc($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
