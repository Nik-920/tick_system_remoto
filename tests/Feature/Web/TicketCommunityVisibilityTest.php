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

/**
 * Community Moderation Admin UI — hide/restore tickets in the community feed.
 *
 * Coverage:
 *   A) Authorization: guest / reporter / maintenance are blocked; admin / super_admin pass.
 *   B) DB updates: hide writes visible=false + metadata; restore clears them.
 *   C) Feed effect: hidden tickets disappear from /reporter/community; restored ones return.
 *   D) UI: show view renders the section with the correct form depending on state.
 *   E) Validation: reason required; reason max 255.
 */
class TicketCommunityVisibilityTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Authorization ──────────────────────────────────────────────────────

    public function test_guest_cannot_hide_ticket_from_community(): void
    {
        $ticket = $this->makeVisibleTicket();

        $this->patch(route('tickets.community.hide', $ticket), ['reason' => 'Motivo'])
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_restore_ticket_in_community(): void
    {
        $ticket = $this->makeHiddenTicket();

        $this->patch(route('tickets.community.restore', $ticket))
            ->assertRedirect(route('login'));
    }

    public function test_reporter_cannot_hide_ticket_from_community(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Motivo'])
            ->assertForbidden();
    }

    public function test_reporter_cannot_restore_ticket_in_community(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeHiddenTicket($reporter);

        $this->actingAs($reporter)
            ->patch(route('tickets.community.restore', $ticket))
            ->assertForbidden();
    }

    public function test_maintenance_cannot_hide_ticket_from_community(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($maintenance)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Motivo'])
            ->assertForbidden();
    }

    public function test_maintenance_cannot_restore_ticket_in_community(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->makeHiddenTicket($reporter);

        $this->actingAs($maintenance)
            ->patch(route('tickets.community.restore', $ticket))
            ->assertForbidden();
    }

    public function test_admin_can_hide_ticket_from_community(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Información sensible'])
            ->assertRedirect(route('tickets.show', $ticket));
    }

    public function test_super_admin_can_hide_ticket_from_community(): void
    {
        $superAdmin = $this->createUserWithRole('super_admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($superAdmin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Reporte duplicado'])
            ->assertRedirect(route('tickets.show', $ticket));
    }

    public function test_admin_can_restore_ticket_in_community(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeHiddenTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket))
            ->assertRedirect(route('tickets.show', $ticket));
    }

    // ── B. DB updates ─────────────────────────────────────────────────────────

    public function test_hide_sets_community_visible_false_and_metadata(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Evidencia no apta']);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'community_visible' => false,
            'community_hidden_by' => $admin->id,
            'community_visibility_reason' => 'Evidencia no apta',
        ]);

        $ticket->refresh();
        $this->assertNotNull($ticket->community_hidden_at);
    }

    public function test_restore_clears_community_hidden_metadata(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeHiddenTicket(reason: 'Solicitud del reporter', hiddenBy: $admin);

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'community_visible' => true,
            'community_hidden_by' => null,
            'community_visibility_reason' => null,
        ]);

        $ticket->refresh();
        $this->assertNull($ticket->community_hidden_at);
    }

    public function test_hide_does_not_change_ticket_operational_state(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();
        $originalState = $ticket->state;

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Otro motivo']);

        $ticket->refresh();
        $this->assertSame($originalState, $ticket->state);
    }

    // ── C. Feed effect ────────────────────────────────────────────────────────

    public function test_hidden_ticket_does_not_appear_in_reporter_community_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket($reporter, 'Filtro de agua roto MODTEST001');

        // Admin hides it.
        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Test ocultamiento']);

        // Reporter checks the feed — ticket must not appear.
        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Filtro de agua roto MODTEST001', false);
    }

    public function test_restored_ticket_reappears_in_reporter_community_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeHiddenTicket($reporter, 'Grieta en techo MODTEST002');

        // Admin restores it.
        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket));

        // Reporter checks the feed — ticket must appear (state is open → visible).
        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Grieta en techo MODTEST002', false);
    }

    public function test_cancelled_ticket_does_not_appear_in_feed_even_if_restored(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');

        $ticket = $this->makeHiddenTicket(
            reporter: $reporter,
            title: 'Cancelado restaurado MODTEST003',
            state: Ticket::STATE_CANCELLED,
        );

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket));

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Cancelado restaurado MODTEST003', false);
    }

    public function test_rejected_ticket_does_not_appear_in_feed_even_if_restored(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');

        $ticket = $this->makeHiddenTicket(
            reporter: $reporter,
            title: 'Rechazado restaurado MODTEST004',
            state: Ticket::STATE_REJECTED,
        );

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket));

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('Rechazado restaurado MODTEST004', false);
    }

    // ── D. UI ─────────────────────────────────────────────────────────────────

    public function test_admin_sees_community_visibility_section_in_ticket_show(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Visibilidad en Comunidad', false);
    }

    public function test_admin_sees_hide_form_when_ticket_is_visible(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Ocultar de Comunidad', false)
            ->assertDontSee('Restaurar en Comunidad', false);
    }

    public function test_admin_sees_restore_form_when_ticket_is_hidden(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeHiddenTicket(reason: 'Contenido sensible');

        $this->actingAs($admin)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Restaurar en Comunidad', false)
            ->assertSee('Contenido sensible', false)
            ->assertDontSee('Ocultar de Comunidad', false);
    }

    public function test_reporter_does_not_see_community_visibility_controls(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        // Reporter can view their own ticket but must not see moderation controls.
        $this->actingAs($reporter)
            ->get(route('reporter.tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('Visibilidad en Comunidad', false)
            ->assertDontSee('Ocultar de Comunidad', false)
            ->assertDontSee('Restaurar en Comunidad', false);
    }

    // ── E. Validation ─────────────────────────────────────────────────────────

    public function test_hide_requires_reason(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    public function test_hide_reason_cannot_exceed_255_characters(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => str_repeat('x', 256)])
            ->assertSessionHasErrors('reason');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleTicket(
        ?User $reporter = null,
        string $title = 'Ticket visible en comunidad TEST',
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        $reporter ??= $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de prueba para visibilidad en comunidad.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeHiddenTicket(
        ?User $reporter = null,
        string $title = 'Ticket oculto de comunidad TEST',
        string $state = Ticket::STATE_OPEN,
        string $reason = 'Motivo de prueba',
        ?User $hiddenBy = null,
    ): Ticket {
        $reporter ??= $this->createUserWithRole('reporter');
        $moderator = $hiddenBy ?? $this->createUserWithRole('admin');

        // community_hidden_at/by/reason are not in $fillable (admin-only, set via
        // forceFill in the controller). Use a two-step create + forceFill here.
        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripción de prueba para ticket oculto.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => false,
        ]);

        $ticket->forceFill([
            'community_hidden_at' => now(),
            'community_hidden_by' => $moderator->id,
            'community_visibility_reason' => $reason,
        ])->save();

        return $ticket;
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

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Sala CommunityMod Test',
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'CM-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de moderación comunidad',
        ]);
    }
}
