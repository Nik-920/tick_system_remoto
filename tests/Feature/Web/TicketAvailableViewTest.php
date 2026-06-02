<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketAvailableViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_can_view_available_tickets_page(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket($reporter, 'open');

        $response = $this
            ->actingAs($maintenance)
            ->get(route('tickets.available'));

        $response->assertOk();
        $response->assertViewIs('tickets.available');
        $response->assertSeeText($ticket->title);
    }

    public function test_available_page_only_lists_open_unassigned_tickets(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');

        $available = $this->createTicket($reporter, 'open');

        $assigned = $this->createTicket($reporter, 'open');
        $assigned->forceFill(['assigned_to' => $maintenance->id])->save();

        $resolved = $this->createTicket($reporter, 'resolved');

        $response = $this
            ->actingAs($maintenance)
            ->get(route('tickets.available'));

        $response->assertOk();
        $response->assertSeeText($available->title);
        $response->assertDontSeeText($assigned->title);
        $response->assertDontSeeText($resolved->title);
    }

    public function test_available_page_does_not_list_assigned_tickets(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');

        $assigned = $this->createTicket($reporter, 'open');
        $assigned->forceFill(['assigned_to' => $maintenance->id])->save();

        $response = $this
            ->actingAs($maintenance)
            ->get(route('tickets.available'));

        $response->assertOk();
        $response->assertDontSeeText($assigned->title);
    }

    public function test_available_page_does_not_list_resolved_tickets(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');

        $resolved = $this->createTicket($reporter, 'resolved');

        $response = $this
            ->actingAs($maintenance)
            ->get(route('tickets.available'));

        $response->assertOk();
        $response->assertDontSeeText($resolved->title);
    }

    public function test_available_page_does_not_list_locked_tickets(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');

        $locked = $this->createTicket($reporter, 'open');
        $locked->forceFill(['assignment_locked' => true])->save();

        $response = $this
            ->actingAs($maintenance)
            ->get(route('tickets.available'));

        $response->assertOk();
        $response->assertDontSeeText($locked->title);
    }

    public function test_available_page_shows_empty_state_message(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $response = $this
            ->actingAs($maintenance)
            ->get(route('tickets.available'));

        $response->assertOk();
        $response->assertSeeText('No hay tickets disponibles en este momento');
        $response->assertSeeText('Cuando un reporte abierto quede sin responsable, aparecerá aquí.');
    }

    public function test_reporter_cannot_access_available_tickets_page(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $response = $this
            ->actingAs($reporter)
            ->get(route('tickets.available'));

        $response->assertForbidden();
    }

    public function test_admin_can_access_available_tickets_page(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this
            ->actingAs($admin)
            ->get(route('tickets.available'));

        $response->assertOk();
    }

    public function test_super_admin_can_access_available_tickets_page(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');

        $response = $this
            ->actingAs($superAdmin)
            ->get(route('tickets.available'));

        $response->assertOk();
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

    private function createTicket(User $reporter, string $state): Ticket
    {
        $location = Location::create([
            'name' => 'Aula Disponible',
            'building' => 'Edificio D',
            'floor' => '1',
            'room_code' => 'D-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::uuid()->toString(),
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Disponibles '.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoria disponible',
        ]);

        return Ticket::create([
            'title' => 'Ticket disponible '.Str::upper(Str::random(4)),
            'description' => 'Descripcion de prueba para disponibilidad de tickets.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => $state,
            'priority' => 'medium',
        ]);
    }
}
