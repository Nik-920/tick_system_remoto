<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Events\TicketCommentCreated;
use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for private core ticket comments (ticket_comments table).
 *
 * A) Authorization gates
 * B) Validation
 * C) Security / no-leak
 * D) Community isolation
 */
class TicketCoreCommentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
    }

    // ── A. Authorization gates ────────────────────────────────────────────────

    public function test_guest_cannot_post_comment(): void
    {
        $ticket = $this->makeTicket($this->makeUser('reporter'));

        $this->post(route('tickets.comments.store', $ticket), ['body' => 'Hola'])
            ->assertRedirect(route('login'));
    }

    public function test_reporter_can_comment_own_ticket(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'Comentario válido.'])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario válido.',
        ]);
    }

    public function test_reporter_cannot_comment_on_another_reporters_ticket(): void
    {
        $reporter = $this->makeUser('reporter');
        $other = $this->makeUser('reporter');
        $ticket = $this->makeTicket($other);

        $this->actingAs($reporter)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'Intrusión.'])
            ->assertForbidden();

        $this->assertDatabaseMissing('ticket_comments', ['ticket_id' => $ticket->id]);
    }

    public function test_maintenance_can_comment_assigned_ticket(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->actingAs($maintenance)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'Revisando el problema.'])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $maintenance->id,
        ]);
    }

    public function test_maintenance_cannot_comment_unassigned_ticket(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicket($reporter);

        $this->actingAs($maintenance)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'Sin asignar.'])
            ->assertForbidden();

        $this->assertDatabaseMissing('ticket_comments', ['ticket_id' => $ticket->id]);
    }

    public function test_admin_can_comment_any_ticket(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        $this->actingAs($admin)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'Intervención admin.'])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_super_admin_can_comment_any_ticket(): void
    {
        $reporter = $this->makeUser('reporter');
        $superAdmin = $this->makeUser('super_admin');
        $ticket = $this->makeTicket($reporter);

        $this->actingAs($superAdmin)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'Nota super admin.'])
            ->assertRedirect();

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $superAdmin->id,
        ]);
    }

    public function test_reporter_cannot_comment_cancelled_ticket(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter, state: Ticket::STATE_CANCELLED);

        $this->actingAs($reporter)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'Cancelado.'])
            ->assertForbidden();
    }

    public function test_reporter_cannot_comment_rejected_ticket(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter, state: Ticket::STATE_REJECTED);

        $this->actingAs($reporter)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'Rechazado.'])
            ->assertForbidden();
    }

    // ── B. Validation ─────────────────────────────────────────────────────────

    public function test_body_is_required(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('tickets.comments.store', $ticket), ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_body_minimum_length(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'x'])
            ->assertSessionHasErrors('body');
    }

    public function test_body_maximum_length(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('tickets.comments.store', $ticket), ['body' => str_repeat('a', 2001)])
            ->assertSessionHasErrors('body');
    }

    // ── C. Security / no-leak ─────────────────────────────────────────────────

    public function test_comment_body_is_escaped_in_tickets_show(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'body' => '<script>alert(1)</script>',
        ]);

        $this->actingAs($reporter)
            ->get(route('tickets.show', $ticket))
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_maintenance_comment_form_not_shown_for_unassigned_ticket(): void
    {
        $reporter = $this->makeUser('reporter');
        $maintenance = $this->makeUser('maintenance');
        $ticket = $this->makeTicket($reporter);

        $this->actingAs($maintenance)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee(route('tickets.comments.store', $ticket), false);
    }

    // ── D. Community isolation ────────────────────────────────────────────────

    public function test_core_comment_does_not_appear_in_community_feed(): void
    {
        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter, communityVisible: true);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario privado nunca público.',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertDontSee('Comentario privado nunca público.');
    }

    public function test_community_comment_does_not_appear_in_core_comments_section(): void
    {
        $reporter = $this->makeUser('reporter');
        $admin = $this->makeUser('admin');
        $ticket = $this->makeTicket($reporter, communityVisible: true);

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Este es un comentario de Comunidad.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $this->assertDatabaseMissing('ticket_comments', ['ticket_id' => $ticket->id]);
    }

    public function test_event_dispatched_on_comment_creation(): void
    {
        Event::fake([TicketCommentCreated::class]);

        $reporter = $this->makeUser('reporter');
        $ticket = $this->makeTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('tickets.comments.store', $ticket), ['body' => 'Disparo de evento.']);

        Event::assertDispatched(TicketCommentCreated::class, function ($event) use ($ticket, $reporter) {
            return (string) $event->ticket->id === (string) $ticket->id
                && (string) $event->actor->id === (string) $reporter->id;
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeTicket(
        User $reporter,
        string $state = Ticket::STATE_OPEN,
        bool $communityVisible = false,
    ): Ticket {
        $location = Location::create([
            'name' => 'Loc tc '.uniqid(),
            'building' => 'B',
            'floor' => '1',
            'room_code' => 'R-'.uniqid(),
            'qr_token' => 'qr-tc-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat tc '.uniqid(),
            'icon' => 'bolt',
            'description' => 'tc',
        ]);

        return Ticket::create([
            'title' => 'Ticket comentario '.uniqid(),
            'description' => 'Desc.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => $communityVisible,
        ]);
    }

    private function makeTicketWithAssignment(User $reporter, User $assignee): Ticket
    {
        $ticket = $this->makeTicket($reporter, state: Ticket::STATE_IN_PROGRESS);
        $ticket->assigned_to = $assignee->id;
        $ticket->save();

        return $ticket;
    }
}
