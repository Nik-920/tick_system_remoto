<?php

declare(strict_types=1);

namespace Tests\Feature\Locks;

use App\Events\TicketResolved;
use App\Events\TicketStateChanged;
use App\Exceptions\TicketLockUnavailableException;
use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketAssignmentService;
use App\Services\Tickets\TicketCancellationService;
use App\Services\Tickets\TicketStateService;
use App\Support\Locks\TicketLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifies that TicketLock::mutation() protects TicketAssignmentService,
 * TicketStateService, and TicketCancellationService against concurrent mutations
 * on the same ticket.
 *
 * Strategy: hold the Redis mutation lock on a ticket, then assert the service
 * throws TicketLockUnavailableException and leaves DB state untouched.
 *
 * Uses CACHE_STORE=array (phpunit.xml) — no Redis required.
 */
class TicketMutationLockTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────
    // TicketAssignmentService
    // ──────────────────────────────────────────────────────────────────────

    public function test_claim_throws_when_mutation_lock_is_held(): void
    {
        Event::fake();

        $maintenance = $this->userWithRole('maintenance');
        $ticket = $this->openTicket();
        $thrown = false;

        TicketLock::mutation((string) $ticket->id)->get(function () use ($ticket, $maintenance, &$thrown): void {
            try {
                $this->app->make(TicketAssignmentService::class)->claimByMaintenance($ticket, $maintenance);
            } catch (TicketLockUnavailableException) {
                $thrown = true;
            }
        });

        $this->assertTrue($thrown, 'Expected TicketLockUnavailableException when lock is held');
        $this->assertNull($ticket->fresh()->assigned_to, 'Ticket must not be mutated when lock is unavailable');
    }

    public function test_claim_does_not_write_state_history_when_lock_is_held(): void
    {
        Event::fake();

        $maintenance = $this->userWithRole('maintenance');
        $ticket = $this->openTicket();
        $countBefore = StateHistory::where('ticket_id', $ticket->id)->count();

        TicketLock::mutation((string) $ticket->id)->get(function () use ($ticket, $maintenance): void {
            try {
                $this->app->make(TicketAssignmentService::class)->claimByMaintenance($ticket, $maintenance);
            } catch (TicketLockUnavailableException) {
            }
        });

        $this->assertSame($countBefore, StateHistory::where('ticket_id', $ticket->id)->count());
    }

    public function test_assign_by_admin_throws_when_mutation_lock_is_held(): void
    {
        Event::fake();

        $admin = $this->userWithRole('admin');
        $maintenance = $this->userWithRole('maintenance');
        $ticket = $this->openTicket();
        $thrown = false;

        TicketLock::mutation((string) $ticket->id)->get(function () use ($ticket, $admin, $maintenance, &$thrown): void {
            try {
                $this->app->make(TicketAssignmentService::class)->assignByAdmin($ticket, $admin, $maintenance);
            } catch (TicketLockUnavailableException) {
                $thrown = true;
            }
        });

        $this->assertTrue($thrown);
        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_unassign_by_admin_throws_when_mutation_lock_is_held(): void
    {
        Event::fake();

        $admin = $this->userWithRole('admin');
        $maintenance = $this->userWithRole('maintenance');
        $ticket = $this->openTicket();
        $ticket->forceFill(['assigned_to' => $maintenance->id, 'assignment_locked' => false])->save();
        $thrown = false;

        TicketLock::mutation((string) $ticket->id)->get(function () use ($ticket, $admin, &$thrown): void {
            try {
                $this->app->make(TicketAssignmentService::class)->unassignByAdmin($ticket, $admin);
            } catch (TicketLockUnavailableException) {
                $thrown = true;
            }
        });

        $this->assertTrue($thrown);
        $this->assertSame($maintenance->id, $ticket->fresh()->assigned_to);
    }

    public function test_claim_succeeds_after_lock_is_released(): void
    {
        Event::fake();

        $maintenance = $this->userWithRole('maintenance');
        $ticket = $this->openTicket();

        // Hold the lock briefly, then release it
        TicketLock::mutation((string) $ticket->id)->get(fn () => null);

        // Now it should succeed
        $result = $this->app->make(TicketAssignmentService::class)->claimByMaintenance($ticket, $maintenance);

        $this->assertSame($maintenance->id, $result->assigned_to);
    }

    // ──────────────────────────────────────────────────────────────────────
    // TicketStateService
    // ──────────────────────────────────────────────────────────────────────

    public function test_transition_throws_when_mutation_lock_is_held(): void
    {
        Event::fake();

        $admin = $this->userWithRole('admin');
        $ticket = $this->openTicket();
        $thrown = false;

        TicketLock::mutation((string) $ticket->id)->get(function () use ($ticket, $admin, &$thrown): void {
            try {
                $this->app->make(TicketStateService::class)->transition($ticket, $admin, 'in_progress');
            } catch (TicketLockUnavailableException) {
                $thrown = true;
            }
        });

        $this->assertTrue($thrown, 'Expected TicketLockUnavailableException when lock is held');
        $this->assertSame('open', $ticket->fresh()->state, 'State must not change when lock is unavailable');
    }

    public function test_transition_does_not_write_state_history_when_lock_is_held(): void
    {
        Event::fake();

        $admin = $this->userWithRole('admin');
        $ticket = $this->openTicket();
        $countBefore = StateHistory::where('ticket_id', $ticket->id)->count();

        TicketLock::mutation((string) $ticket->id)->get(function () use ($ticket, $admin): void {
            try {
                $this->app->make(TicketStateService::class)->transition($ticket, $admin, 'in_progress');
            } catch (TicketLockUnavailableException) {
            }
        });

        $this->assertSame($countBefore, StateHistory::where('ticket_id', $ticket->id)->count());
    }

    public function test_transition_does_not_dispatch_domain_events_when_lock_is_held(): void
    {
        // Only fake the domain events we care about (pre-lock validations fire Spatie
        // model events which are infrastructure noise, not business logic).
        Event::fake([TicketStateChanged::class, TicketResolved::class]);

        $admin = $this->userWithRole('admin');
        $ticket = $this->openTicket();

        TicketLock::mutation((string) $ticket->id)->get(function () use ($ticket, $admin): void {
            try {
                $this->app->make(TicketStateService::class)->transition($ticket, $admin, 'in_progress');
            } catch (TicketLockUnavailableException) {
            }
        });

        Event::assertNothingDispatched();
    }

    public function test_same_state_transition_does_not_acquire_lock(): void
    {
        // same-state is a no-op and skips the lock entirely.
        // Even while holding the lock, the same-state call must return immediately.
        Event::fake();

        $admin = $this->userWithRole('admin');
        $ticket = $this->openTicket();
        $returned = false;

        TicketLock::mutation((string) $ticket->id)->get(function () use ($ticket, $admin, &$returned): void {
            // open → open: no mutation, no lock needed
            $this->app->make(TicketStateService::class)->transition($ticket, $admin, 'open');
            $returned = true;
        });

        $this->assertTrue($returned, 'Same-state transition should skip the lock and return normally');
    }

    public function test_transition_succeeds_after_lock_is_released(): void
    {
        Event::fake();

        $admin = $this->userWithRole('admin');
        $ticket = $this->openTicket();

        TicketLock::mutation((string) $ticket->id)->get(fn () => null);

        $result = $this->app->make(TicketStateService::class)->transition($ticket, $admin, 'in_progress');

        $this->assertSame('in_progress', $result->state);
    }

    // ──────────────────────────────────────────────────────────────────────
    // TicketCancellationService
    // ──────────────────────────────────────────────────────────────────────

    public function test_cancel_throws_when_mutation_lock_is_held(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $thrown = false;

        TicketLock::mutation((string) $ticket->id)->get(function () use ($ticket, $reporter, &$thrown): void {
            try {
                $this->app->make(TicketCancellationService::class)->cancelByReporter($ticket, $reporter);
            } catch (TicketLockUnavailableException) {
                $thrown = true;
            }
        });

        $this->assertTrue($thrown, 'Expected TicketLockUnavailableException when lock is held');
        $this->assertSame('open', $ticket->fresh()->state, 'State must not change when lock is unavailable');
    }

    public function test_cancel_does_not_write_state_history_when_lock_is_held(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);
        $countBefore = StateHistory::where('ticket_id', $ticket->id)->count();

        TicketLock::mutation((string) $ticket->id)->get(function () use ($ticket, $reporter): void {
            try {
                $this->app->make(TicketCancellationService::class)->cancelByReporter($ticket, $reporter);
            } catch (TicketLockUnavailableException) {
            }
        });

        $this->assertSame($countBefore, StateHistory::where('ticket_id', $ticket->id)->count());
    }

    public function test_cancel_succeeds_after_lock_is_released(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->openTicketFor($reporter);

        TicketLock::mutation((string) $ticket->id)->get(fn () => null);

        $result = $this->app->make(TicketCancellationService::class)->cancelByReporter($ticket, $reporter);

        $this->assertSame(Ticket::STATE_CANCELLED, $result->state);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Cross-service mutual exclusion
    // ──────────────────────────────────────────────────────────────────────

    public function test_assignment_and_state_transition_are_mutually_exclusive_via_mutation_lock(): void
    {
        // Demonstrates that a claim and a state transition on the same ticket
        // cannot race: both use TicketLock::mutation(), so whichever acquires
        // first blocks the other.
        Event::fake();

        $admin = $this->userWithRole('admin');
        $ticket = $this->openTicket();
        $stateThrown = false;

        // Simulate "claim in progress" — hold the mutation lock
        TicketLock::mutation((string) $ticket->id)->get(function () use ($ticket, $admin, &$stateThrown): void {
            try {
                // State transition tries to acquire same lock → must fail
                $this->app->make(TicketStateService::class)->transition($ticket, $admin, 'in_progress');
            } catch (TicketLockUnavailableException) {
                $stateThrown = true;
            }
        });

        $this->assertTrue($stateThrown, 'State transition must fail while assignment lock is held');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function openTicket(): Ticket
    {
        return $this->openTicketFor($this->userWithRole('reporter'));
    }

    private function openTicketFor(User $reporter): Ticket
    {
        $location = Location::create([
            'name' => 'Loc '.Str::upper(Str::random(4)),
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'TST-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat '.Str::lower(Str::random(6)),
            'icon' => 'bolt',
            'description' => 'Categoría de prueba',
        ]);

        return Ticket::create([
            'title' => 'Ticket lock test '.Str::random(6),
            'description' => 'Descripcion de prueba para lock test.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
        ]);
    }

    private function userWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
