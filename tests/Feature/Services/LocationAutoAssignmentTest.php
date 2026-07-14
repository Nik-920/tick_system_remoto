<?php

namespace Tests\Feature\Services;

use App\Events\TicketAssigned;
use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Observability\TicketQrLogger;
use App\Services\Storage\TicketMediaStorageService;
use App\Services\Tickets\TicketCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Auto-asignación por Jefe de Práctica de la ubicación
 * (tickets.auto_assign_by_location + locations.responsible_user_id).
 *
 * Contrato bajo prueba:
 * 1. Flag ON + ubicación con responsable maintenance → ticket asignado a ese
 *    usuario con assignment_source = location_auto y evento TicketAssigned
 *    (action 'assigned') hacia el asignado.
 * 2. Flag ON + ubicación sin responsable → comportamiento actual (pool).
 * 3. Flag OFF → comportamiento actual aunque exista responsable.
 * 4. Responsable sin rol maintenance → NO se asigna (guard del servicio) y la
 *    creación del ticket no falla.
 */
class LocationAutoAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private TicketCreationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('maintenance', 'web');
        Role::findOrCreate('reporter', 'web');

        $this->service = new TicketCreationService(
            $this->createStub(TicketQrLogger::class),
            $this->createStub(TicketMediaStorageService::class),
        );
    }

    public function test_ticket_is_auto_assigned_to_location_responsible_when_flag_is_on(): void
    {
        config(['tickets.auto_assign_by_location' => true]);
        Event::fake([TicketAssigned::class]);

        $reporter = $this->makeReporter();
        $responsible = $this->makeMaintenanceUser();
        $location = $this->makeLocation($responsible->id);
        $category = $this->makeCategory();

        $result = $this->service->create($reporter, $this->payload($location, $category));
        $ticket = Ticket::findOrFail($result['ticket']->id);

        $this->assertSame($responsible->id, $ticket->assigned_to);
        $this->assertSame(Ticket::ASSIGNMENT_SOURCE_LOCATION, $ticket->assignment_source);
        $this->assertSame($reporter->id, $ticket->assigned_by);
        $this->assertFalse((bool) $ticket->assignment_locked);
        $this->assertNotNull($ticket->assigned_at);

        Event::assertDispatched(TicketAssigned::class, function (TicketAssigned $event) use ($ticket, $responsible): bool {
            return $event->ticket->id === $ticket->id
                && $event->action === 'assigned'
                && $event->newAssignee?->id === $responsible->id;
        });

        $this->assertTrue(
            StateHistory::query()
                ->where('ticket_id', $ticket->id)
                ->where('comment', 'like', '%asignado automáticamente%')
                ->exists()
        );
    }

    public function test_ticket_stays_in_pool_when_location_has_no_responsible(): void
    {
        config(['tickets.auto_assign_by_location' => true]);
        Event::fake([TicketAssigned::class]);

        $reporter = $this->makeReporter();
        $location = $this->makeLocation(null);
        $category = $this->makeCategory();

        $result = $this->service->create($reporter, $this->payload($location, $category));
        $ticket = Ticket::findOrFail($result['ticket']->id);

        $this->assertNull($ticket->assigned_to);
        $this->assertNull($ticket->assignment_source);
        Event::assertNotDispatched(TicketAssigned::class);
    }

    public function test_ticket_stays_in_pool_when_flag_is_off(): void
    {
        config(['tickets.auto_assign_by_location' => false]);
        Event::fake([TicketAssigned::class]);

        $reporter = $this->makeReporter();
        $responsible = $this->makeMaintenanceUser();
        $location = $this->makeLocation($responsible->id);
        $category = $this->makeCategory();

        $result = $this->service->create($reporter, $this->payload($location, $category));
        $ticket = Ticket::findOrFail($result['ticket']->id);

        $this->assertNull($ticket->assigned_to);
        Event::assertNotDispatched(TicketAssigned::class);
    }

    public function test_creation_survives_when_responsible_lacks_maintenance_role(): void
    {
        config(['tickets.auto_assign_by_location' => true]);
        Event::fake([TicketAssigned::class]);

        $reporter = $this->makeReporter();
        $notMaintenance = User::factory()->create();
        $location = $this->makeLocation($notMaintenance->id);
        $category = $this->makeCategory();

        $result = $this->service->create($reporter, $this->payload($location, $category));
        $ticket = Ticket::findOrFail($result['ticket']->id);

        $this->assertTrue($result['created']);
        $this->assertNull($ticket->assigned_to);
        Event::assertNotDispatched(TicketAssigned::class);
    }

    private function makeReporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeMaintenanceUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('maintenance');

        return $user;
    }

    private function makeLocation(?string $responsibleUserId): Location
    {
        return Location::create([
            'name' => 'Lab '.Str::uuid(),
            'building' => 'Pabellón A',
            'floor' => '2',
            'room_code' => 'LAB-'.Str::uuid(),
            'responsible_user_id' => $responsibleUserId,
            'qr_token' => 'qr-'.Str::uuid(),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Hardware-'.Str::uuid(),
            'icon' => 'cpu',
            'description' => 'Categoría de prueba',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Location $location, Category $category): array
    {
        return [
            'title' => 'Proyector no enciende',
            'description' => 'El proyector del laboratorio no responde.',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'priority' => 'medium',
        ];
    }
}
