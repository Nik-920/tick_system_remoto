<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_tickets_from_api(): void
    {
        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        Ticket::create([
            'title' => 'Proyector sin imagen API',
            'description' => 'API test para listado de tickets.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->getJson(route('api.tickets.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'title',
                    'state',
                    'priority',
                ],
            ],
        ]);
    }

    public function test_api_store_creates_ticket_and_returns_201(): void
    {
        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $payload = [
            'title' => 'Incidencia creada via API',
            'description' => 'Se registra un ticket de ejemplo creado desde endpoint API.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'high',
        ];

        $response = $this->postJson(route('api.tickets.store'), $payload);

        $response->assertCreated();
        $response->assertJsonPath('duplicate', false);
        $response->assertJsonPath('data.title', 'Incidencia creada via API');
        $this->assertDatabaseHas('tickets', [
            'title' => 'Incidencia creada via API',
            'reporter_id' => $user->id,
        ]);
    }

    public function test_api_store_detects_duplicate_ticket_in_window(): void
    {
        $user = $this->createUserWithRole('reporter');
        Sanctum::actingAs($user);

        $location = $this->createLocation();
        $category = $this->createCategory();

        Ticket::create([
            'title' => 'Ticket existente',
            'description' => 'Ya hay un ticket en esta ubicacion y categoria.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $payload = [
            'title' => 'Nuevo intento duplicado',
            'description' => 'Intento crear otro ticket para la misma incidencia activa.',
            'location_id' => $location->id,
            'category_id' => $category->id,
        ];

        $response = $this->postJson(route('api.tickets.store'), $payload);

        $response->assertOk();
        $response->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_reporter_cannot_change_ticket_state_via_api(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket sin permiso de cambio',
            'description' => 'El reporter no deberia poder cambiar estado.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->patchJson(route('api.tickets.update-state', $ticket), [
            'to_state' => 'in_progress',
            'comment' => 'Intento no autorizado.',
        ]);

        $response->assertForbidden();
    }

    public function test_maintenance_can_change_ticket_state_via_api(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        Sanctum::actingAs($maintenance);

        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Ticket para atender',
            'description' => 'Cambio de estado permitido para maintenance.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->patchJson(route('api.tickets.update-state', $ticket), [
            'to_state' => 'in_progress',
            'comment' => 'Tecnico asignado.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.state', 'in_progress');
        $this->assertDatabaseHas('state_history', [
            'ticket_id' => $ticket->id,
            'to_state' => 'in_progress',
            'changed_by' => $maintenance->id,
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
}
