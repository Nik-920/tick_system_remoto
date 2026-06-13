<?php

namespace Tests\Feature\Dashboard;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Dashboard\MaintenanceDashboardQuery;
use App\Support\Dashboard\DateRange;
use App\ViewModels\Dashboard\MaintenanceDashboardViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceDashboardQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    }

    public function test_personal_metrics_do_not_leak_other_technicians(): void
    {
        $me = $this->maintenance();
        $other = $this->maintenance();
        $category = $this->createCategory('Hardware');

        $mineResolved = $this->resolvedTicket($me, $this->createLocation('Lab A1', 'A-1'), $category,
            createdAt: '2026-06-10 08:00:00', resolvedAt: '2026-06-10 10:00:00');
        $mineActive = $this->activeTicket($me, $this->createLocation('Lab A2', 'A-2'), $category, 'in_progress', 'high');

        $foreignResolved = $this->resolvedTicket($other, $this->createLocation('Lab B1', 'B-1'), $category,
            createdAt: '2026-06-11 08:00:00', resolvedAt: '2026-06-11 09:00:00');
        $foreignActive = $this->activeTicket($other, $this->createLocation('Lab B2', 'B-2'), $category, 'open', 'critical');

        $vm = MaintenanceDashboardQuery::for($me, DateRange::default());

        $this->assertSame('1', $this->kpi($vm, 'resolved_range'));

        $queueIds = array_map(fn (array $row): string => $row['ticket']->id, $vm->priorityQueue);
        $this->assertContains($mineActive->id, $queueIds);
        $this->assertNotContains($foreignActive->id, $queueIds);

        $resolvedIds = $vm->recentResolved->pluck('id')->all();
        $this->assertContains($mineResolved->id, $resolvedIds);
        $this->assertNotContains($foreignResolved->id, $resolvedIds);

        $locationNames = array_column($vm->topLocations, 'name');
        $this->assertContains('Lab A1', $locationNames);
        $this->assertNotContains('Lab B1', $locationNames);
    }

    public function test_average_resolution_does_not_truncate_sub_hour_durations(): void
    {
        $me = $this->maintenance();

        // 90 minutes from creation to resolution must read as 1.5 h, not 0 h.
        $this->resolvedTicket($me, $this->createLocation('Lab C1', 'C-1'), $this->createCategory('Redes'),
            createdAt: '2026-06-10 08:30:00', resolvedAt: '2026-06-10 10:00:00');

        $vm = MaintenanceDashboardQuery::for($me, DateRange::default());

        $this->assertSame('1', $this->kpi($vm, 'resolved_range'));
        $this->assertSame('1.5 h', $this->kpi($vm, 'avg_resolution'));
    }

    public function test_available_to_claim_counts_open_unassigned_tickets(): void
    {
        $me = $this->maintenance();
        $reporter = $this->createUserWithRole('reporter');
        $category = $this->createCategory('Electricidad');

        foreach (['D-1', 'D-2'] as $room) {
            Ticket::create([
                'title' => 'Disponible '.$room,
                'description' => 'Abierto sin asignar',
                'reporter_id' => $reporter->id,
                'location_id' => $this->createLocation('Lab '.$room, $room)->id,
                'category_id' => $category->id,
                'state' => 'open',
                'priority' => 'medium',
            ]);
        }

        // A ticket already assigned to me must NOT count as available.
        $this->activeTicket($me, $this->createLocation('Lab D3', 'D-3'), $category, 'open', 'low');

        $vm = MaintenanceDashboardQuery::for($me, DateRange::default());

        $this->assertSame(2, $vm->availableToClaimCount);
    }

    public function test_resolved_outside_range_is_excluded(): void
    {
        $me = $this->maintenance();
        $category = $this->createCategory('Climatizacion');

        // Inside default range (last 30 days from 2026-06-15).
        $this->resolvedTicket($me, $this->createLocation('Lab E1', 'E-1'), $category,
            createdAt: '2026-06-01 08:00:00', resolvedAt: '2026-06-01 09:00:00');

        // Well before the range.
        $this->resolvedTicket($me, $this->createLocation('Lab E2', 'E-2'), $category,
            createdAt: '2026-01-01 08:00:00', resolvedAt: '2026-01-01 09:00:00');

        $vm = MaintenanceDashboardQuery::for($me, DateRange::default());

        $this->assertSame('1', $this->kpi($vm, 'resolved_range'));
    }

    public function test_priority_queue_is_ordered_by_difficulty_score(): void
    {
        $me = $this->maintenance();
        $category = $this->createCategory('Mecanica');

        $low = $this->activeTicket($me, $this->createLocation('Lab F1', 'F-1'), $category, 'open', 'low');
        $critical = $this->activeTicket($me, $this->createLocation('Lab F2', 'F-2'), $category, 'in_progress', 'critical');

        $vm = MaintenanceDashboardQuery::for($me, DateRange::default());

        $this->assertNotEmpty($vm->priorityQueue);
        $this->assertSame($critical->id, $vm->priorityQueue[0]['ticket']->id);
        $this->assertGreaterThan(
            $vm->priorityQueue[1]['score'],
            $vm->priorityQueue[0]['score']
        );
        $this->assertSame($low->id, $vm->priorityQueue[1]['ticket']->id);
    }

    public function test_top_categories_are_scoped_to_current_technician(): void
    {
        $me = $this->maintenance();
        $other = $this->maintenance();
        $mine = $this->createCategory('Refrigeracion');
        $foreign = $this->createCategory('Cableado');

        $this->activeTicket($me, $this->createLocation('Lab G1', 'G-1'), $mine, 'open', 'medium');
        $this->activeTicket($me, $this->createLocation('Lab G2', 'G-2'), $mine, 'open', 'high');
        $this->activeTicket($other, $this->createLocation('Lab G3', 'G-3'), $foreign, 'open', 'critical');

        $vm = MaintenanceDashboardQuery::for($me, DateRange::default());

        $names = array_column($vm->topCategories, 'name');
        $this->assertContains('Refrigeracion', $names);
        $this->assertNotContains('Cableado', $names);
    }

    public function test_priority_distribution_counts_my_tickets_by_priority(): void
    {
        $me = $this->maintenance();
        $category = $this->createCategory('Pintura');

        $this->activeTicket($me, $this->createLocation('Lab H1', 'H-1'), $category, 'open', 'critical');
        $this->activeTicket($me, $this->createLocation('Lab H2', 'H-2'), $category, 'open', 'low');

        $vm = MaintenanceDashboardQuery::for($me, DateRange::default());

        $this->assertSame(1, $vm->priorityDistribution['critical'] ?? 0);
        $this->assertSame(1, $vm->priorityDistribution['low'] ?? 0);
    }

    private function kpi(MaintenanceDashboardViewModel $vm, string $key): string
    {
        foreach ($vm->kpis as $card) {
            if ($card['key'] === $key) {
                return $card['value'];
            }
        }

        return '';
    }

    private function maintenance(): User
    {
        return $this->createUserWithRole('maintenance');
    }

    private function activeTicket(User $assignee, Location $location, Category $category, string $state, string $priority): Ticket
    {
        $ticket = Ticket::create([
            'title' => 'Activo '.$location->room_code,
            'description' => 'Ticket activo',
            'reporter_id' => $assignee->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => $state,
            'priority' => $priority,
        ]);
        $ticket->forceFill(['assigned_to' => $assignee->id])->save();

        return $ticket;
    }

    private function resolvedTicket(User $assignee, Location $location, Category $category, string $createdAt, string $resolvedAt): Ticket
    {
        $ticket = Ticket::create([
            'title' => 'Resuelto '.$location->room_code,
            'description' => 'Ticket resuelto',
            'reporter_id' => $assignee->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'resolved',
            'priority' => 'medium',
        ]);
        $ticket->forceFill([
            'assigned_to' => $assignee->id,
            'created_at' => Carbon::parse($createdAt),
            'resolved_at' => Carbon::parse($resolvedAt),
        ])->save();

        return $ticket;
    }

    private function createUserWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function createLocation(string $name, string $roomCode): Location
    {
        return Location::create([
            'name' => $name,
            'building' => 'Edificio Z',
            'floor' => '1',
            'room_code' => $roomCode,
            'qr_token' => 'qr-'.strtolower(str_replace([' ', '_'], '-', $roomCode)),
            'is_active' => true,
        ]);
    }

    private function createCategory(string $name): Category
    {
        return Category::create([
            'name' => $name,
            'icon' => 'wrench',
            'description' => 'Categoria de prueba',
        ]);
    }
}
