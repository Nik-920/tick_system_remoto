<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\ViewModels\Tickets\MaintenanceBoardViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The maintenance "Tickets V2" board is the official /tickets view for the
 * maintenance role. Reporters and admins keep the classic table.
 *
 * Covers: which role gets which view, the visibility boundary, the tab scopes,
 * the summary counts, and the quick-filter chips.
 */
class MaintenanceTicketsBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('tickets.index'))->assertRedirect(route('login'));
    }

    public function test_maintenance_index_renders_the_board(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $otherTech = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');

        $mine = $this->createTicket('Proyector asignado', $reporter, [
            'state' => 'in_progress',
            'priority' => 'high',
        ]);
        $mine->forceFill(['assigned_to' => $maintenance->id])->save();

        $this->createTicket('Cable disponible', $reporter, ['state' => 'open', 'priority' => 'low']);

        $foreign = $this->createTicket('Ticket de otro tecnico', $reporter, ['state' => 'in_progress']);
        $foreign->forceFill(['assigned_to' => $otherTech->id])->save();

        $response = $this->actingAs($maintenance)->get(route('tickets.index'));

        $response->assertOk();
        $response->assertViewIs('tickets.maintenance-v2');
        $response->assertSeeText('Cola priorizada');
        $response->assertSeeText('Insights operativos');
        $response->assertSeeText('Proyector asignado');
        $response->assertSeeText('Cable disponible');
        $response->assertDontSeeText('Ticket de otro tecnico');

        $board = $response->viewData('board');
        $this->assertInstanceOf(MaintenanceBoardViewModel::class, $board);
        $this->assertSame(2, $board->summary['visible']);
        $this->assertSame(1, $board->summary['mine']);
        $this->assertSame(1, $board->summary['available']);
        $this->assertSame(1, $board->summary['in_progress']);
    }

    public function test_reporter_index_keeps_the_classic_table(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $response = $this->actingAs($reporter)->get(route('tickets.index'));

        $response->assertOk();
        $response->assertViewIs('tickets.index');
        $response->assertDontSeeText('Insights operativos');
    }

    public function test_admin_index_keeps_the_classic_table(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->get(route('tickets.index'));

        $response->assertOk();
        $response->assertViewIs('tickets.index');
        $response->assertDontSeeText('Insights operativos');
    }

    public function test_mine_tab_scopes_to_my_assignments(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');

        $mine = $this->createTicket('Mesa asignada', $reporter, ['state' => 'open']);
        $mine->forceFill(['assigned_to' => $maintenance->id])->save();

        $this->createTicket('PC disponible', $reporter, ['state' => 'open']);

        $response = $this->actingAs($maintenance)
            ->get(route('tickets.index', ['view' => 'mine']));

        $response->assertOk();
        $response->assertSeeText('Mesa asignada');
        $response->assertDontSeeText('PC disponible');
    }

    public function test_available_tab_scopes_to_claimable_tickets(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');

        $mine = $this->createTicket('Mesa asignada', $reporter, ['state' => 'in_progress']);
        $mine->forceFill(['assigned_to' => $maintenance->id])->save();

        $this->createTicket('PC disponible', $reporter, ['state' => 'open']);

        $response = $this->actingAs($maintenance)
            ->get(route('tickets.index', ['view' => 'available']));

        $response->assertOk();
        $response->assertSeeText('PC disponible');
        $response->assertDontSeeText('Mesa asignada');
        $response->assertSee('Tomar');
    }

    public function test_priority_chip_filters_the_queue(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $reporter = $this->createUserWithRole('reporter');

        $this->createTicket('Incidencia critica', $reporter, ['state' => 'open', 'priority' => 'high']);
        $this->createTicket('Incidencia menor', $reporter, ['state' => 'open', 'priority' => 'low']);

        $response = $this->actingAs($maintenance)
            ->get(route('tickets.index', ['view' => 'all', 'priority' => 'high']));

        $response->assertOk();
        $response->assertSeeText('Incidencia critica');
        $response->assertDontSeeText('Incidencia menor');
    }

    public function test_invalid_view_falls_back_to_all(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $response = $this->actingAs($maintenance)
            ->get(route('tickets.index', ['view' => 'bogus']));

        $response->assertOk();
        $this->assertSame('all', $response->viewData('board')->view);
    }

    // ── Fixtures ─────────────────────────────────────────────────

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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTicket(string $title, User $reporter, array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'title' => $title,
            'description' => 'Descripción de '.$title,
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => 'open',
            'priority' => 'medium',
            'assignment_locked' => false,
        ], $overrides));
    }

    private function location(): Location
    {
        return Location::firstOrCreate(
            ['room_code' => 'LAB-1'],
            [
                'name' => 'Laboratorio 1',
                'building' => 'Edificio A',
                'floor' => '1',
                'qr_token' => 'qr-'.Str::uuid()->toString(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'Hardware'],
            ['icon' => 'monitor', 'description' => 'Equipos físicos'],
        );
    }
}
