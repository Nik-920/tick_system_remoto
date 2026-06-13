<?php

namespace Tests\Unit\Policies;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\TicketPolicy;
use App\Services\Tickets\TicketStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 0 — Parity test: TicketPolicy.updateState vs TicketStateService.
 *
 * Verifica que la Policy y el Service NO divergen en reglas críticas.
 * Si la Policy dice que un usuario puede cambiar estado, el Service debe
 * aceptar al menos una transición. Si la Policy lo bloquea, el Service
 * no debe permitir ninguna transición.
 *
 * Enfoque: verificar casos concretos, no combinatoria bruta, para
 * evitar tests frágiles dependientes de implementación interna.
 */
class TicketPolicyVsServiceParityTest extends TestCase
{
    use RefreshDatabase;

    private TicketPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureRolesExist();
        $this->policy = new TicketPolicy;
    }

    // ──────────────────────────────────────────────────────────────────
    // Maintenance: assigned → puede
    // ──────────────────────────────────────────────────────────────────

    public function test_maintenance_assigned_can_update_state_in_policy_and_service(): void
    {
        Event::fake();

        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('open', assignedTo: $maintenance);

        // Policy permite
        $this->assertTrue(
            $this->policy->updateState($maintenance, $ticket),
            'Policy should allow maintenance to updateState on own ticket.'
        );

        // Service permite (al menos una transición disponible)
        $available = $this->service()->availableTransitionsFor($ticket, $maintenance);
        $this->assertNotEmpty(
            $available,
            'Service should return available transitions for maintenance on own ticket.'
        );
    }

    public function test_maintenance_assigned_in_progress_can_resolve_in_both_layers(): void
    {
        Event::fake();

        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('in_progress', assignedTo: $maintenance);

        // Policy permite
        $this->assertTrue($this->policy->updateState($maintenance, $ticket));

        // Service permite resolved
        $available = $this->service()->availableTransitionsFor($ticket, $maintenance);
        $this->assertContains('resolved', $available);

        // Service ejecución real
        $result = $this->service()->transition($ticket, $maintenance, 'resolved', 'Reparado.');
        $this->assertSame('resolved', $result->state);
    }

    // ──────────────────────────────────────────────────────────────────
    // Maintenance: unassigned → no puede
    // ──────────────────────────────────────────────────────────────────

    public function test_maintenance_unassigned_blocked_in_both_layers(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('open'); // sin asignar

        // Policy bloquea (maintenance solo actúa sobre assigned_to === self)
        $this->assertFalse(
            $this->policy->updateState($maintenance, $ticket),
            'Policy should block maintenance on unassigned ticket.'
        );

        // Service bloquea
        $available = $this->service()->availableTransitionsFor($ticket, $maintenance);
        $this->assertSame([], $available, 'Service should return no transitions for maintenance on unassigned ticket.');
    }

    // ──────────────────────────────────────────────────────────────────
    // Maintenance: ticket ajeno → no puede
    // ──────────────────────────────────────────────────────────────────

    public function test_maintenance_other_user_ticket_blocked_in_both_layers(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $otherMaintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('open', assignedTo: $otherMaintenance);

        // Policy bloquea
        $this->assertFalse(
            $this->policy->updateState($maintenance, $ticket),
            'Policy should block maintenance on ticket assigned to another user.'
        );

        // Service bloquea
        $available = $this->service()->availableTransitionsFor($ticket, $maintenance);
        $this->assertSame([], $available);
    }

    // ──────────────────────────────────────────────────────────────────
    // Admin: open/in_progress → puede
    // ──────────────────────────────────────────────────────────────────

    public function test_admin_can_update_state_open_in_both_layers(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('open');

        $this->assertTrue($this->policy->updateState($admin, $ticket));

        $available = $this->service()->availableTransitionsFor($ticket, $admin);
        $this->assertContains('in_progress', $available);
    }

    public function test_admin_can_update_state_in_progress_in_both_layers(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('in_progress');

        $this->assertTrue($this->policy->updateState($admin, $ticket));

        $available = $this->service()->availableTransitionsFor($ticket, $admin);
        $this->assertContains('resolved', $available);
        $this->assertContains('rejected', $available);
    }

    // ──────────────────────────────────────────────────────────────────
    // Admin: resolved → no puede reabrir
    // ──────────────────────────────────────────────────────────────────

    public function test_admin_cannot_reopen_resolved_in_both_layers(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('resolved');

        // Policy bloquea (solo super_admin)
        $this->assertFalse(
            $this->policy->updateState($admin, $ticket),
            'Policy should block admin from reopening resolved ticket.'
        );

        // Service bloquea
        $available = $this->service()->availableTransitionsFor($ticket, $admin);
        $this->assertSame([], $available);
    }

    // ──────────────────────────────────────────────────────────────────
    // Super admin: resolved → puede reabrir
    // ──────────────────────────────────────────────────────────────────

    public function test_super_admin_can_reopen_resolved_in_both_layers(): void
    {
        Event::fake();

        $superAdmin = $this->createUserWithRole('super_admin');
        $ticket = $this->createTicket('resolved');

        // Policy permite
        $this->assertTrue($this->policy->updateState($superAdmin, $ticket));

        // Service permite
        $available = $this->service()->availableTransitionsFor($ticket, $superAdmin);
        $this->assertContains('open', $available);

        // Service ejecución real
        $result = $this->service()->transition($ticket, $superAdmin, 'open', 'Revisión.');
        $this->assertSame('open', $result->state);
    }

    // ──────────────────────────────────────────────────────────────────
    // Admin: rejected → puede reabrir
    // ──────────────────────────────────────────────────────────────────

    public function test_admin_can_reopen_rejected_in_both_layers(): void
    {
        Event::fake();

        $admin = $this->createUserWithRole('admin');
        $ticket = $this->createTicket('rejected');

        // Policy permite
        $this->assertTrue($this->policy->updateState($admin, $ticket));

        // Service permite
        $available = $this->service()->availableTransitionsFor($ticket, $admin);
        $this->assertContains('open', $available);
    }

    // ──────────────────────────────────────────────────────────────────
    // Reporter: nunca puede
    // ──────────────────────────────────────────────────────────────────

    public function test_reporter_cannot_update_state_in_any_layer(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        foreach (['open', 'in_progress', 'resolved', 'rejected'] as $state) {
            $ticket = $this->createTicket($state);

            $this->assertFalse(
                $this->policy->updateState($reporter, $ticket),
                "Policy should block reporter from updateState on '{$state}' ticket."
            );

            $available = $this->service()->availableTransitionsFor($ticket, $reporter);
            $this->assertSame(
                [],
                $available,
                "Service should return no transitions for reporter on '{$state}' ticket."
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    private function service(): TicketStateService
    {
        return $this->app->make(TicketStateService::class);
    }

    private function createTicket(string $state, ?User $assignedTo = null): Ticket
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $ticket = Ticket::create([
            'title' => 'Parity test '.Str::random(6),
            'description' => 'Test de paridad Policy↔Service.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => $state,
            'priority' => 'medium',
        ]);

        if ($assignedTo !== null) {
            $ticket->forceFill(['assigned_to' => $assignedTo->id])->save();
        }

        return $ticket;
    }

    private function createUserWithRole(string $role): User
    {
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
            'name' => 'Loc Parity '.Str::upper(Str::random(4)),
            'building' => 'Edificio Parity',
            'floor' => '1',
            'room_code' => 'PAR-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-par-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Cat Parity '.Str::lower(Str::random(6)),
            'icon' => 'bolt',
            'description' => 'Categoría de paridad',
        ]);
    }
}
