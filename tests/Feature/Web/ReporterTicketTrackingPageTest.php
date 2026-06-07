<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\StateHistory;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use App\ViewModels\Tickets\ReporterTicketTrackingViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Ver seguimiento" is the reporter-only per-ticket tracking screen — LIVE DATA.
 *
 * The ticket is resolved INSIDE the reporter's ownership boundary
 * (reporter_id = them): another reporter's ticket, or a malformed/unknown id,
 * is a 404. These tests pin down that boundary, that the header/stepper/timeline/
 * details/evidence all come from real data, the honest "Pendiente" milestones,
 * the empty states, and that NO maintenance action is exposed.
 */
class ReporterTicketTrackingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($reporter, 'open', 'Para invitado');

        $this->get(route('reporter.tickets.show', $ticket->id))->assertRedirect(route('login'));
    }

    public function test_reporter_can_view_own_ticket_tracking(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'in_progress', 'Incidencia propia');

        $response = $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertViewIs('tickets.reporter.show');
        $this->assertInstanceOf(ReporterTicketTrackingViewModel::class, $response->viewData('tracking'));
    }

    public function test_reporter_cannot_view_another_reporters_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');
        $foreign = $this->ticketFor($other, 'open', 'Ticket ajeno');

        $this->actingAs($me)->get(route('reporter.tickets.show', $foreign->id))->assertNotFound();
    }

    public function test_renders_real_title_and_short_id(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Proyector quemado en laboratorio');

        $response = $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertSeeText('Proyector quemado en laboratorio');
        $response->assertSeeText('#'.strtoupper(substr((string) $ticket->id, 0, 8)));
    }

    public function test_renders_real_status_and_priority(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'in_progress', 'Con prioridad alta', ['priority' => 'high']);

        $response = $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertSeeText('En progreso');
        $response->assertSeeText('Alta');
    }

    public function test_renders_real_location_and_category(): void
    {
        $me = $this->userWithRole('reporter');
        $lab = $this->location('LAB-9', 'Laboratorio 9');
        $cat = $this->category('Redes');
        $ticket = $this->ticketFor($me, 'open', 'Con ubicación', ['location_id' => $lab->id, 'category_id' => $cat->id]);

        $response = $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertSeeText('Laboratorio 9');
        $response->assertSeeText('Redes');
    }

    public function test_stepper_marks_current_stage_for_open_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Recién reportado');

        $response = $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertSeeText('Reportado');
        $response->assertSeeText('Revisado');
        $response->assertSeeText('En progreso');
        // An open ticket has a live "current" step (awaiting review).
        $response->assertSee('aria-current="step"', false);
        // The "Reportado" milestone shows the real creation date, not invented.
        $response->assertSeeText($ticket->created_at->format('d/m/Y · H:i'));
    }

    public function test_stepper_completes_for_resolved_ticket(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'resolved', 'Ya resuelto', [
            'resolved_at' => Carbon::parse('2026-06-02 15:00:00'),
        ]);

        $response = $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id));

        $response->assertOk();
        // Terminal state: no live "current" step.
        $response->assertDontSee('aria-current="step"', false);
        $response->assertSeeText('Resuelto');
    }

    public function test_timeline_uses_real_state_history_with_actor(): void
    {
        $me = $this->userWithRole('reporter');
        $tech = $this->userWithRole('maintenance', 'Tecnico Uno');
        $ticket = $this->ticketFor($me, 'in_progress', 'Con historial');
        $this->transition($ticket, 'open', 'in_progress', $tech, 'Comenzando la revisión', Carbon::parse('2026-06-01 10:00:00'));

        $response = $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertSeeText('Ticket creado');
        $response->assertSeeText('Trabajo iniciado');
        $response->assertSeeText('Tecnico Uno');
        $response->assertSeeText('Comenzando la revisión');
    }

    public function test_timeline_falls_back_to_creation_when_no_history(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Sin historial');

        $response = $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertSeeText('Ticket creado');
        $response->assertDontSeeText('Trabajo iniciado');

        $tracking = $response->viewData('tracking');
        $this->assertCount(1, $tracking->timeline);
    }

    public function test_renders_evidence_when_it_exists(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Con evidencia');
        $this->media($ticket, $me, 'image/png', 'https://cdn.example.test/evidence-1.png');

        $response = $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertSeeText('Evidencias');
        $response->assertSee('https://cdn.example.test/evidence-1.png', false);
        $response->assertDontSeeText('Aún no hay evidencias adjuntas.');
    }

    public function test_renders_evidence_empty_state(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'open', 'Sin evidencia');

        $response = $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertSeeText('Aún no hay evidencias adjuntas.');
    }

    public function test_does_not_expose_maintenance_actions(): void
    {
        $me = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($me, 'in_progress', 'Sin acciones técnicas');

        $response = $this->actingAs($me)->get(route('reporter.tickets.show', $ticket->id));

        $response->assertOk();
        $response->assertDontSeeText('Tomar');
        $response->assertDontSeeText('Liberar');
        $response->assertDontSeeText('Reasignar');
        $response->assertDontSeeText('Resolver ticket');
    }

    public function test_maintenance_cannot_access_the_reporter_tracking_route(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($reporter, 'open', 'Propio del reporter');

        $this->actingAs($this->userWithRole('maintenance'))
            ->get(route('reporter.tickets.show', $ticket->id))
            ->assertForbidden();
    }

    public function test_admin_cannot_access_the_reporter_tracking_route(): void
    {
        $reporter = $this->userWithRole('reporter');
        $ticket = $this->ticketFor($reporter, 'open', 'Propio del reporter');

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('reporter.tickets.show', $ticket->id))
            ->assertForbidden();
    }

    public function test_invalid_ticket_id_returns_404(): void
    {
        $me = $this->userWithRole('reporter');

        $this->actingAs($me)->get(route('reporter.tickets.show', 'not-a-real-uuid'))->assertNotFound();
        $this->actingAs($me)->get(route('reporter.tickets.show', (string) Str::uuid()))->assertNotFound();
    }

    public function test_board_route_remains_intact(): void
    {
        $me = $this->userWithRole('reporter');

        $this->actingAs($me)
            ->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertViewIs('tickets.reporter.index');
    }

    // ── Fixtures ─────────────────────────────────────────────────

    private function userWithRole(string $role, ?string $name = null): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::factory()->create($name !== null ? ['name' => $name] : []);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attrs  location_id, category_id, priority, created_at, resolved_at, assigned_to, assigned_at
     */
    private function ticketFor(User $reporter, string $state, string $title, array $attrs = []): Ticket
    {
        $ticket = Ticket::create([
            'title' => $title,
            'description' => $attrs['description'] ?? ('Descripción de '.$title),
            'reporter_id' => $reporter->id,
            'location_id' => $attrs['location_id'] ?? $this->location()->id,
            'category_id' => $attrs['category_id'] ?? $this->category()->id,
            'state' => $state,
            'priority' => $attrs['priority'] ?? 'medium',
            'assignment_locked' => false,
        ]);

        $force = array_filter([
            'assigned_to' => $attrs['assigned_to'] ?? null,
            'assigned_at' => $attrs['assigned_at'] ?? null,
            'resolved_at' => $attrs['resolved_at'] ?? null,
            'created_at' => $attrs['created_at'] ?? null,
        ], fn ($v): bool => $v !== null);

        if ($force !== []) {
            $ticket->forceFill($force)->save();
        }

        return $ticket;
    }

    private function transition(Ticket $ticket, string $from, string $to, User $by, ?string $comment, Carbon $at): void
    {
        $history = StateHistory::create([
            'ticket_id' => $ticket->id,
            'from_state' => $from,
            'to_state' => $to,
            'changed_by' => $by->id,
            'comment' => $comment,
        ]);

        $history->forceFill(['created_at' => $at])->save();
    }

    private function media(Ticket $ticket, User $uploader, string $type, string $url): TicketMedia
    {
        return TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => $url,
            'file_type' => $type,
            'uploaded_by' => $uploader->id,
        ]);
    }

    private function location(string $code = 'LAB-1', string $name = 'Laboratorio 1'): Location
    {
        return Location::firstOrCreate(
            ['room_code' => $code],
            [
                'name' => $name,
                'building' => 'Edificio A',
                'floor' => '1',
                'qr_token' => 'qr-'.Str::uuid()->toString(),
                'is_active' => true,
            ],
        );
    }

    private function category(string $name = 'Hardware'): Category
    {
        return Category::firstOrCreate(
            ['name' => $name],
            ['icon' => 'monitor', 'description' => 'Categoría '.$name],
        );
    }
}
