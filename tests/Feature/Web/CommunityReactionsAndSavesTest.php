<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityReaction;
use App\Models\CommunitySave;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community v2 — Reactions and Saves.
 *
 * Coverage:
 *   A) Access/authorization: guest, reporter, maintenance, admin.
 *   B) Reactions: add/remove interested, also_happens, seen; idempotency; type validation.
 *   C) Saves: save/unsave; idempotency; ownership isolation.
 *   D) Visibility restrictions: hidden/cancelled/rejected tickets are not interactable.
 *   E) Feed UI/counts: aggregates, active state, saved state, no-identity leak.
 *   F) Regression/no-leak: PII not exposed; moderation logs not exposed.
 */
class CommunityReactionsAndSavesTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Access / authorization ─────────────────────────────────────────────

    public function test_guest_cannot_react(): void
    {
        $ticket = $this->makeVisibleTicket();

        $this->post(route('reporter.community.reactions.store', $ticket), ['type' => 'interested'])
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_save(): void
    {
        $ticket = $this->makeVisibleTicket();

        $this->post(route('reporter.community.saves.store', $ticket))
            ->assertRedirect(route('login'));
    }

    public function test_reporter_can_react_to_visible_community_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'interested'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reactions', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'interested',
        ]);
    }

    public function test_reporter_can_save_visible_community_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.saves.store', $ticket))
            ->assertRedirect();

        $this->assertDatabaseHas('community_saves', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
        ]);
    }

    public function test_maintenance_cannot_use_reporter_reaction_routes(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $maintenance = $this->createUserWithRole('maintenance');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($maintenance)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'interested'])
            ->assertForbidden();
    }

    public function test_admin_cannot_use_reporter_reaction_routes(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($admin)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'interested'])
            ->assertForbidden();
    }

    // ── B. Reactions ──────────────────────────────────────────────────────────

    public function test_reporter_can_add_interested_reaction(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'interested'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reactions', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'interested',
        ]);
    }

    public function test_reporter_can_add_also_happens_reaction(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'also_happens'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reactions', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'also_happens',
        ]);
    }

    public function test_reporter_can_add_seen_reaction(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'seen'])
            ->assertRedirect();

        $this->assertDatabaseHas('community_reactions', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'seen',
        ]);
    }

    public function test_duplicate_reaction_is_idempotent(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'interested']);
        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'interested']);

        $this->assertSame(1, CommunityReaction::where('ticket_id', $ticket->id)
            ->where('user_id', $reporter->id)
            ->where('type', 'interested')
            ->count());
    }

    public function test_reporter_can_remove_own_reaction(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'interested',
        ]);

        $this->actingAs($reporter)
            ->delete(route('reporter.community.reactions.destroy', [$ticket, 'interested']))
            ->assertRedirect();

        $this->assertDatabaseMissing('community_reactions', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'interested',
        ]);
    }

    public function test_reporter_cannot_remove_another_users_reaction(): void
    {
        $reporterA = $this->createUserWithRole('reporter');
        $reporterB = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporterA);

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporterA->id,
            'type' => 'interested',
        ]);

        // Reporter B's delete only affects B's own rows — A's reaction stays intact.
        $this->actingAs($reporterB)
            ->delete(route('reporter.community.reactions.destroy', [$ticket, 'interested']))
            ->assertRedirect();

        $this->assertDatabaseHas('community_reactions', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporterA->id,
            'type' => 'interested',
        ]);
    }

    public function test_invalid_reaction_type_is_rejected(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'invalid_type'])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseMissing('community_reactions', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
        ]);
    }

    // ── C. Saves ──────────────────────────────────────────────────────────────

    public function test_reporter_can_save_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.saves.store', $ticket))
            ->assertRedirect();

        $this->assertDatabaseHas('community_saves', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
        ]);
    }

    public function test_duplicate_save_does_not_create_duplicate_row(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.saves.store', $ticket));
        $this->actingAs($reporter)
            ->post(route('reporter.community.saves.store', $ticket));

        $this->assertSame(1, CommunitySave::where('ticket_id', $ticket->id)
            ->where('user_id', $reporter->id)
            ->count());
    }

    public function test_reporter_can_unsave_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        CommunitySave::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->delete(route('reporter.community.saves.destroy', $ticket))
            ->assertRedirect();

        $this->assertDatabaseMissing('community_saves', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
        ]);
    }

    public function test_reporter_cannot_unsave_another_users_save(): void
    {
        $reporterA = $this->createUserWithRole('reporter');
        $reporterB = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporterA);

        CommunitySave::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporterA->id,
        ]);

        // Reporter B's delete only deletes their own saves — A's save stays intact.
        $this->actingAs($reporterB)
            ->delete(route('reporter.community.saves.destroy', $ticket))
            ->assertRedirect();

        $this->assertDatabaseHas('community_saves', [
            'ticket_id' => $ticket->id,
            'user_id' => $reporterA->id,
        ]);
    }

    // ── D. Visibility restrictions ────────────────────────────────────────────

    public function test_cannot_react_to_community_hidden_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeHiddenTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'interested'])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_reactions', [
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_cannot_save_community_hidden_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeHiddenTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.saves.store', $ticket))
            ->assertNotFound();

        $this->assertDatabaseMissing('community_saves', [
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_cannot_react_to_cancelled_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter, state: Ticket::STATE_CANCELLED);

        $this->actingAs($reporter)
            ->post(route('reporter.community.reactions.store', $ticket), ['type' => 'interested'])
            ->assertNotFound();

        $this->assertDatabaseMissing('community_reactions', [
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_cannot_save_rejected_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter, state: Ticket::STATE_REJECTED);

        $this->actingAs($reporter)
            ->post(route('reporter.community.saves.store', $ticket))
            ->assertNotFound();

        $this->assertDatabaseMissing('community_saves', [
            'ticket_id' => $ticket->id,
        ]);
    }

    // ── E. Feed UI / counts ───────────────────────────────────────────────────

    public function test_feed_shows_reaction_count(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket con reacciones RXCNT');

        CommunityReaction::create(['ticket_id' => $ticket->id, 'user_id' => $reporter->id, 'type' => 'interested']);
        CommunityReaction::create(['ticket_id' => $ticket->id, 'user_id' => $other->id, 'type' => 'interested']);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-action-btn__count', false);
    }

    public function test_feed_shows_active_state_for_current_users_reaction(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket activo interesa RXACT');

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'interested',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Quitar reacción Me interesa', false);
    }

    public function test_feed_shows_saved_state_for_current_user(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket guardado SVSTATE');

        CommunitySave::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Quitar de guardados', false);
    }

    public function test_feed_does_not_show_who_reacted(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = User::factory()->create([
            'name' => 'ReactorNameSecret',
            'email' => 'reactor-secret@test.test',
        ]);
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket noleak reactions RXNL');

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $other->id,
            'type' => 'interested',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('ReactorNameSecret', false)
            ->assertDontSee('reactor-secret@test.test', false);
    }

    public function test_saved_filter_shows_only_current_user_saved_visible_tickets(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');

        $saved = $this->makeVisibleTicket($reporter, 'Ticket guardado SVSAVED');
        $notSaved = $this->makeVisibleTicket($reporter, 'Ticket no guardado SVNOT');
        $otherSaved = $this->makeVisibleTicket($other, 'Ticket otro guardado SVOTR');

        CommunitySave::create(['ticket_id' => $saved->id, 'user_id' => $reporter->id]);
        CommunitySave::create(['ticket_id' => $otherSaved->id, 'user_id' => $other->id]);

        $this->actingAs($reporter)
            ->get(route('reporter.community').'?saved=1')
            ->assertOk()
            ->assertSee('Ticket guardado SVSAVED', false)
            ->assertDontSee('Ticket no guardado SVNOT', false)
            ->assertDontSee('Ticket otro guardado SVOTR', false);
    }

    // ── F. Regression / no-leak ───────────────────────────────────────────────

    public function test_community_feed_does_not_expose_personal_data_with_reactions(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $secret = User::factory()->create([
            'name' => 'SecretReactionUser',
            'email' => 'secret-reaction@test.test',
        ]);
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket pii noleak RXPII');

        CommunityReaction::create(['ticket_id' => $ticket->id, 'user_id' => $secret->id, 'type' => 'seen']);
        CommunitySave::create(['ticket_id' => $ticket->id, 'user_id' => $secret->id]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('SecretReactionUser', false)
            ->assertDontSee('secret-reaction@test.test', false);
    }

    public function test_hidden_saved_ticket_does_not_appear_in_saved_filter(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket hidden saved SVHIDE');

        CommunitySave::create(['ticket_id' => $ticket->id, 'user_id' => $reporter->id]);

        // Admin hides the ticket from community
        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Ocultamiento test'])
            ->assertRedirect();

        // The saved filter must not expose the now-hidden ticket
        $this->actingAs($reporter)
            ->get(route('reporter.community').'?saved=1')
            ->assertOk()
            ->assertDontSee('Ticket hidden saved SVHIDE', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleTicket(
        ?User $reporter = null,
        string $title = 'Ticket visible comunidad TEST',
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        $reporter ??= $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de test para reacciones y guardados.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeHiddenTicket(?User $reporter = null): Ticket
    {
        $reporter ??= $this->createUserWithRole('reporter');

        $ticket = Ticket::create([
            'title' => 'Ticket oculto TEST',
            'description' => 'Ticket no visible en comunidad.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => false,
        ]);

        $ticket->forceFill([
            'community_hidden_at' => now(),
            'community_hidden_by' => $reporter->id,
            'community_visibility_reason' => 'Test ocultamiento',
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
            'name' => 'Sala Reactions Test',
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'RX-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de reactions/saves',
        ]);
    }
}
