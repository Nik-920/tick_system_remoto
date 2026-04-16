<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_ticket_index(): void
    {
        $user = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        Ticket::create([
            'title' => 'Proyector sin imagen',
            'description' => 'El proyector del aula 201 no muestra señal HDMI.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.index'));

        $response->assertOk();
        $response->assertViewIs('tickets.index');
        $response->assertSee('Proyector sin imagen');
    }

    public function test_authenticated_user_can_create_ticket_from_web_form(): void
    {
        $user = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $payload = [
            'title' => 'Enchufe sin corriente',
            'description' => 'El enchufe de la esquina derecha no entrega energia desde la mañana.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'high',
        ];

        $response = $this
            ->actingAs($user)
            ->post(route('tickets.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Ticket creado correctamente.');
        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseHas('tickets', [
            'title' => 'Enchufe sin corriente',
            'reporter_id' => $user->id,
            'state' => 'open',
        ]);
    }

    public function test_maintenance_can_change_ticket_state_from_open_to_in_progress(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Luz apagada en laboratorio',
            'description' => 'No encienden las luces del laboratorio principal.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this
            ->actingAs($maintenance)
            ->patch(route('tickets.update-state', $ticket), [
                'to_state' => 'in_progress',
                'comment' => 'Se asigna tecnico de turno.',
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'state' => 'in_progress',
        ]);
        $this->assertDatabaseHas('state_history', [
            'ticket_id' => $ticket->id,
            'from_state' => 'open',
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
            'name' => 'Aula Innovacion',
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
