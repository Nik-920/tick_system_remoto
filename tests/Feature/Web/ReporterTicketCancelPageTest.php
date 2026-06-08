<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Real "Cancelar solicitud" for the reporter — phase 4 (new `cancelled` state).
 *
 * A reporter may cancel ONLY their own request and ONLY while it is still open,
 * unassigned and unlocked (TicketPolicy@cancelAsReporter). Cancelling moves the
 * state open → cancelled and records a state_history row. It is NOT a rejection
 * (a maintenance/admin decision) and NOT a delete — the ticket row is preserved.
 */
class ReporterTicketCancelPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $ticket = $this->ticketFor($this->userWithRole('reporter'), 'open', 'X');

        $this->patch(route('reporter.tickets.cancel', $ticket->id))->assertRedirect(route('login'));
    }

    public function test_reporter_can_cancel_own_open_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'A cancelar');

        $response = $this->actingAs($me)->patch(route('reporter.tickets.cancel', $ticket->id));

        $response->assertRedirect(route('reporter.tickets.show', $ticket->id));
        $response->assertSessionHas('status');

        $ticket->refresh();
        $this->assertSame(Ticket::STATE_CANCELLED, $ticket->state);

        // The ticket row is preserved (no hard delete).
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'state' => 'cancelled']);

        // A state_history row records the open → cancelled transition by the reporter.
        $this->assertDatabaseHas('state_history', [
            'ticket_id' => $ticket->id,
            'from_state' => 'open',
            'to_state' => 'cancelled',
            'changed_by' => $me->id,
            'comment' => TicketCancellationService::DEFAULT_COMMENT,
        ]);
    }

    public function test_cancellation_stores_optional_reporter_comment(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Con motivo');

        $this->actingAs($me)
            ->patch(route('reporter.tickets.cancel', $ticket->id), ['comment' => 'Reporté el laboratorio equivocado.'])
            ->assertRedirect(route('reporter.tickets.show', $ticket->id));

        $this->assertDatabaseHas('state_history', [
            'ticket_id' => $ticket->id,
            'to_state' => 'cancelled',
            'comment' => 'Reporté el laboratorio equivocado.',
        ]);
    }

    public function test_reporter_gets_404_cancelling_another_reporters_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $foreign = $this->ticketFor($this->userWithRole('reporter'), 'open', 'Ajeno');

        $this->actingAs($me)->patch(route('reporter.tickets.cancel', $foreign->id))->assertNotFound();

        $foreign->refresh();
        $this->assertSame('open', $foreign->state);
        $this->assertDatabaseMissing('state_history', ['ticket_id' => $foreign->id, 'to_state' => 'cancelled']);
    }

    public function test_reporter_gets_404_for_invalid_uuid(): void
    {
        $me = $this->userWithRole('reporter');

        $this->actingAs($me)->patch(route('reporter.tickets.cancel', 'not-a-uuid'))->assertNotFound();
        $this->actingAs($me)->patch(route('reporter.tickets.cancel', Str::uuid()->toString()))->assertNotFound();
    }

    public function test_reporter_cannot_cancel_non_open_tickets(): void
    {
        $me = $this->userWithRole('reporter');

        foreach (['in_progress', 'resolved', 'rejected', 'cancelled'] as $state) {
            $ticket = $this->ticketFor($me, $state, "Estado {$state}");

            $this->actingAs($me)->patch(route('reporter.tickets.cancel', $ticket->id))->assertForbidden();

            $this->assertSame($state, $ticket->refresh()->state);
        }
    }

    public function test_reporter_cannot_cancel_assigned_open_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $tech = $this->userWithRole('maintenance');
        $ticket = $this->ticketFor($me, 'open', 'Tomado', ['assigned_to' => $tech->id]);

        $this->actingAs($me)->patch(route('reporter.tickets.cancel', $ticket->id))->assertForbidden();
        $this->assertSame('open', $ticket->refresh()->state);
    }

    public function test_reporter_cannot_cancel_locked_open_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Bloqueado', ['assignment_locked' => true]);

        $this->actingAs($me)->patch(route('reporter.tickets.cancel', $ticket->id))->assertForbidden();
        $this->assertSame('open', $ticket->refresh()->state);
    }

    public function test_maintenance_admin_super_admin_cannot_use_cancel_route(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($reporter, 'open', 'Solo reporter');

        foreach (['maintenance', 'admin', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->patch(route('reporter.tickets.cancel', $ticket->id))
                ->assertForbidden();
        }

        $this->assertSame('open', $ticket->refresh()->state);
    }

    // ── Fixtures ─────────────────────────────────────────────────

    private function userWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function ticketFor(User $reporter, string $state, string $title, array $attrs = []): Ticket
    {
        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripción de '.$title.' con largo suficiente.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => $state,
            'priority' => 'medium',
            'assignment_locked' => $attrs['assignment_locked'] ?? false,
        ]);

        if (! empty($attrs['assigned_to'])) {
            $ticket->forceFill(['assigned_to' => $attrs['assigned_to']])->save();
        }

        return $ticket;
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
            ['icon' => 'monitor', 'description' => 'Categoría Hardware'],
        );
    }
}
