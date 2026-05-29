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

class TicketAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_can_claim_open_ticket(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket($reporter);

        $response = $this
            ->actingAs($maintenance)
            ->patch(route('tickets.claim', $ticket));

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assigned_to' => $maintenance->id,
            'assigned_by' => $maintenance->id,
            'assignment_locked' => false,
            'assignment_source' => Ticket::ASSIGNMENT_SOURCE_SELF,
        ]);
    }

    public function test_maintenance_can_release_self_claimed_ticket(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket($reporter);

        $ticket->forceFill([
            'assigned_to' => $maintenance->id,
            'assigned_by' => $maintenance->id,
            'assigned_at' => now(),
            'assignment_locked' => false,
            'assignment_source' => Ticket::ASSIGNMENT_SOURCE_SELF,
        ])->save();

        $response = $this
            ->actingAs($maintenance)
            ->patch(route('tickets.release', $ticket));

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assigned_to' => null,
            'assignment_locked' => false,
            'assignment_source' => null,
            'assigned_by' => $maintenance->id,
        ]);
    }

    public function test_admin_can_assign_ticket_to_maintenance(): void
    {
        $admin = $this->createUserWithRole('admin');
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket($reporter);

        $response = $this
            ->actingAs($admin)
            ->patch(route('tickets.assign', $ticket), [
                'assigned_to' => $maintenance->id,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assigned_to' => $maintenance->id,
            'assigned_by' => $admin->id,
            'assignment_locked' => true,
            'assignment_source' => Ticket::ASSIGNMENT_SOURCE_ADMIN,
        ]);
    }

    public function test_super_admin_can_assign_ticket_to_maintenance(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket($reporter);

        $response = $this
            ->actingAs($superAdmin)
            ->patch(route('tickets.assign', $ticket), [
                'assigned_to' => $maintenance->id,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assigned_to' => $maintenance->id,
            'assigned_by' => $superAdmin->id,
            'assignment_locked' => true,
            'assignment_source' => Ticket::ASSIGNMENT_SOURCE_ADMIN,
        ]);
    }

    public function test_admin_can_unassign_ticket(): void
    {
        $admin = $this->createUserWithRole('admin');
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket($reporter);

        $ticket->forceFill([
            'assigned_to' => $maintenance->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
            'assignment_locked' => true,
            'assignment_source' => Ticket::ASSIGNMENT_SOURCE_ADMIN,
        ])->save();

        $response = $this
            ->actingAs($admin)
            ->patch(route('tickets.unassign', $ticket));

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assigned_to' => null,
            'assignment_locked' => false,
            'assignment_source' => null,
            'assigned_by' => $admin->id,
        ]);
    }

    public function test_super_admin_can_unassign_ticket(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket($reporter);

        $ticket->forceFill([
            'assigned_to' => $maintenance->id,
            'assigned_by' => $superAdmin->id,
            'assigned_at' => now(),
            'assignment_locked' => true,
            'assignment_source' => Ticket::ASSIGNMENT_SOURCE_ADMIN,
        ])->save();

        $response = $this
            ->actingAs($superAdmin)
            ->patch(route('tickets.unassign', $ticket));

        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assigned_to' => null,
            'assignment_locked' => false,
            'assignment_source' => null,
            'assigned_by' => $superAdmin->id,
        ]);
    }

    public function test_reporter_cannot_claim_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $otherReporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket($otherReporter);

        $response = $this
            ->actingAs($reporter)
            ->patch(route('tickets.claim', $ticket));

        $response->assertForbidden();
    }

    public function test_reporter_cannot_assign_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $otherReporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket($otherReporter);

        $response = $this
            ->actingAs($reporter)
            ->patch(route('tickets.assign', $ticket), [
                'assigned_to' => $maintenance->id,
            ]);

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
            'name' => 'Aula Assign',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::uuid()->toString(),
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Asignacion '.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoria asignacion',
        ]);

        return Ticket::create([
            'title' => 'Ticket para asignacion',
            'description' => 'Descripcion de prueba para asignaciones.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }
}
