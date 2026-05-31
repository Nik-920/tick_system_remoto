<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketAssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_can_claim_ticket(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($maintenance);

        $ticket = $this->createTicket($reporter);

        $response = $this->patchJson(route('api.tickets.claim', $ticket));

        $response->assertOk();
        $response->assertJsonPath('data.assignee.id', $maintenance->id);
    }

    public function test_admin_can_assign_ticket(): void
    {
        $admin = $this->createUserWithRole('admin');
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($admin);

        $ticket = $this->createTicket($reporter);

        $response = $this->patchJson(route('api.tickets.assign', $ticket), [
            'assigned_to' => $maintenance->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.assignee.id', $maintenance->id);
    }

    public function test_super_admin_can_assign_ticket(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($superAdmin);

        $ticket = $this->createTicket($reporter);

        $response = $this->patchJson(route('api.tickets.assign', $ticket), [
            'assigned_to' => $maintenance->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.assignee.id', $maintenance->id);
    }

    public function test_assign_with_non_maintenance_returns_422(): void
    {
        $admin = $this->createUserWithRole('admin');
        $reporter = $this->createUserWithRole('reporter');
        $otherReporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($admin);

        $ticket = $this->createTicket($reporter);

        $response = $this->patchJson(route('api.tickets.assign', $ticket), [
            'assigned_to' => $otherReporter->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.assigned_to.0', 'El usuario asignado debe tener rol maintenance.');
    }

    public function test_reporter_is_forbidden_to_claim_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $otherReporter = $this->createUserWithRole('reporter');
        Sanctum::actingAs($reporter);

        $ticket = $this->createTicket($otherReporter);

        $response = $this->patchJson(route('api.tickets.claim', $ticket));

        $response->assertForbidden();
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

    private function createTicket(User $reporter): Ticket
    {
        $location = Location::create([
            'name' => 'Aula API',
            'building' => 'Edificio B',
            'floor' => '1',
            'room_code' => 'B-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::uuid()->toString(),
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Asignacion '.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoria asignacion',
        ]);

        return Ticket::create([
            'title' => 'Ticket API asignacion',
            'description' => 'Descripcion de prueba para asignaciones API.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }
}
