<?php

namespace Tests\Unit\Policies;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\TicketPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reporter edit/cancel window — TicketPolicy.update / cancelAsReporter.
 *
 * Centralised business rule (single source of truth for the reporter board):
 * a reporter may edit OR cancel their own request ONLY while it is still
 * open + unassigned + unlocked. The moment maintenance takes it (assigned),
 * it is locked, or it moves to in_progress/resolved/rejected, the ticket
 * belongs to the operational flow and both abilities are denied.
 *
 * Cancellation is NOT rejection and NOT a hard delete: this phase only gates
 * the ability; no `cancelled` domain state is invented here.
 */
class TicketPolicyReporterTest extends TestCase
{
    use RefreshDatabase;

    private TicketPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureRolesExist();
        $this->policy = new TicketPolicy;
    }

    // ── update: the allowed window ───────────────────────────────

    public function test_reporter_can_update_own_open_unassigned_unlocked_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket('open', reporter: $reporter);

        $this->assertTrue($this->policy->update($reporter, $ticket));
        $this->assertTrue($this->policy->cancelAsReporter($reporter, $ticket));
    }

    public function test_reporter_cannot_update_own_non_open_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        foreach (['in_progress', 'resolved', 'rejected', 'cancelled'] as $state) {
            $ticket = $this->createTicket($state, reporter: $reporter);

            $this->assertFalse(
                $this->policy->update($reporter, $ticket),
                "update must be denied on '{$state}'."
            );
            $this->assertFalse(
                $this->policy->cancelAsReporter($reporter, $ticket),
                "cancel must be denied on '{$state}' (no re-cancel)."
            );
        }
    }

    public function test_reporter_cannot_update_own_open_ticket_assigned_to_maintenance(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $tech = $this->createUserWithRole('maintenance');
        $ticket = $this->createTicket('open', reporter: $reporter, assignedTo: $tech);

        $this->assertFalse($this->policy->update($reporter, $ticket));
        $this->assertFalse($this->policy->cancelAsReporter($reporter, $ticket));
    }

    public function test_reporter_cannot_update_own_open_locked_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket('open', reporter: $reporter, locked: true);

        $this->assertFalse($this->policy->update($reporter, $ticket));
        $this->assertFalse($this->policy->cancelAsReporter($reporter, $ticket));
    }

    public function test_reporter_cannot_update_another_reporters_open_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->createTicket('open', reporter: $other);

        $this->assertFalse($this->policy->update($reporter, $ticket));
        $this->assertFalse($this->policy->cancelAsReporter($reporter, $ticket));
    }

    public function test_non_reporter_roles_cannot_update_or_cancel_as_reporter(): void
    {
        $ticket = $this->createTicket('open');

        foreach (['maintenance', 'admin', 'super_admin'] as $role) {
            $user = $this->createUserWithRole($role);

            $this->assertFalse(
                $this->policy->update($user, $ticket),
                "update (reporter rule) must be denied for '{$role}'."
            );
            $this->assertFalse(
                $this->policy->cancelAsReporter($user, $ticket),
                "cancelAsReporter must be denied for '{$role}'."
            );
        }
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function createTicket(
        string $state,
        ?User $reporter = null,
        ?User $assignedTo = null,
        bool $locked = false,
    ): Ticket {
        $reporter ??= $this->createUserWithRole('reporter');

        $ticket = Ticket::create([
            'title' => 'Policy test '.Str::random(6),
            'description' => 'Regla reporter edit/cancel.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->createLocation()->id,
            'category_id' => $this->createCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'assignment_locked' => $locked,
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
            'name' => 'Loc Policy '.Str::upper(Str::random(4)),
            'building' => 'Edificio Policy',
            'floor' => '1',
            'room_code' => 'POL-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-pol-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Cat Policy '.Str::lower(Str::random(6)),
            'icon' => 'bolt',
            'description' => 'Categoría policy',
        ]);
    }
}
