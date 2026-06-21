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
 * Community — Optimistic Social Actions (JSON + progressive-enhancement layer).
 *
 * Coverage:
 *   A) Backend JSON — reactions store/destroy return correct payload.
 *   B) Backend JSON — saves store/destroy return correct payload.
 *   C) Idempotency via JSON — duplicate requests stay safe.
 *   D) Visibility guards — hidden/cancelled/rejected tickets return 404 on JSON.
 *   E) Validation — invalid reaction type returns JSON error.
 *   F) No-PII — JSON payloads never include personal data.
 *   G) Fallback — without Accept:json the routes still redirect back.
 *   H) HTML attributes — Blade renders data-* and ARIA attributes for JS.
 *   I) Regression — existing CommunityReactionsAndSavesTest scenarios hold.
 */
class CommunityOptimisticSocialActionsTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Reaction JSON ──────────────────────────────────────────────────────

    public function test_reaction_store_with_json_accept_returns_json_ok(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->postJson(
                route('reporter.community.reactions.store', $ticket),
                ['type' => 'interested']
            )
            ->assertOk()
            ->assertJson(['ok' => true, 'kind' => 'reaction']);
    }

    public function test_reaction_store_json_returns_active_true_and_real_count(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $other = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $other->id,
            'type' => 'interested',
        ]);

        $response = $this->actingAs($reporter)
            ->postJson(
                route('reporter.community.reactions.store', $ticket),
                ['type' => 'interested']
            )
            ->assertOk();

        $data = $response->json();

        $this->assertTrue($data['active']);
        $this->assertSame('interested', $data['type']);
        $this->assertSame(2, $data['count']);
        $this->assertSame(2, $data['counts']['interested']);
    }

    public function test_reaction_destroy_json_returns_active_false_and_real_count(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'interested',
        ]);

        $response = $this->actingAs($reporter)
            ->deleteJson(
                route('reporter.community.reactions.destroy', [$ticket, 'interested'])
            )
            ->assertOk();

        $data = $response->json();

        $this->assertFalse($data['active']);
        $this->assertSame('interested', $data['type']);
        $this->assertSame(0, $data['count']);
        $this->assertSame(0, $data['counts']['interested']);
    }

    public function test_reaction_json_response_includes_all_counts(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $response = $this->actingAs($reporter)
            ->postJson(
                route('reporter.community.reactions.store', $ticket),
                ['type' => 'also_happens']
            )
            ->assertOk();

        $data = $response->json();

        $this->assertArrayHasKey('counts', $data);
        $this->assertArrayHasKey('interested', $data['counts']);
        $this->assertArrayHasKey('also_happens', $data['counts']);
        $this->assertArrayHasKey('seen', $data['counts']);
    }

    // ── B. Save JSON ──────────────────────────────────────────────────────────

    public function test_save_store_json_returns_active_true_and_real_count(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $response = $this->actingAs($reporter)
            ->postJson(route('reporter.community.saves.store', $ticket))
            ->assertOk();

        $data = $response->json();

        $this->assertTrue($data['ok']);
        $this->assertSame('save', $data['kind']);
        $this->assertTrue($data['active']);
        $this->assertSame(1, $data['count']);
    }

    public function test_save_destroy_json_returns_active_false_and_real_count(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        CommunitySave::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
        ]);

        $response = $this->actingAs($reporter)
            ->deleteJson(route('reporter.community.saves.destroy', $ticket))
            ->assertOk();

        $data = $response->json();

        $this->assertFalse($data['active']);
        $this->assertSame(0, $data['count']);
    }

    // ── C. Idempotency ────────────────────────────────────────────────────────

    public function test_duplicate_reaction_store_json_is_idempotent(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->postJson(
                route('reporter.community.reactions.store', $ticket),
                ['type' => 'interested']
            );

        $response = $this->actingAs($reporter)
            ->postJson(
                route('reporter.community.reactions.store', $ticket),
                ['type' => 'interested']
            )
            ->assertOk();

        $this->assertSame(1, $response->json('count'));
        $this->assertSame(
            1,
            CommunityReaction::where('ticket_id', $ticket->id)->where('type', 'interested')->count()
        );
    }

    public function test_duplicate_save_store_json_is_idempotent(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->postJson(route('reporter.community.saves.store', $ticket));

        $response = $this->actingAs($reporter)
            ->postJson(route('reporter.community.saves.store', $ticket))
            ->assertOk();

        $this->assertSame(1, $response->json('count'));
        $this->assertSame(
            1,
            CommunitySave::where('ticket_id', $ticket->id)->count()
        );
    }

    // ── D. Visibility guards on JSON ─────────────────────────────────────────

    public function test_hidden_ticket_json_mutation_returns_404(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeHiddenTicket($reporter);

        $this->actingAs($reporter)
            ->postJson(
                route('reporter.community.reactions.store', $ticket),
                ['type' => 'interested']
            )
            ->assertNotFound();
    }

    public function test_cancelled_ticket_json_reaction_returns_404(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter, state: Ticket::STATE_CANCELLED);

        $this->actingAs($reporter)
            ->postJson(
                route('reporter.community.reactions.store', $ticket),
                ['type' => 'interested']
            )
            ->assertNotFound();
    }

    public function test_rejected_ticket_json_save_returns_404(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter, state: Ticket::STATE_REJECTED);

        $this->actingAs($reporter)
            ->postJson(route('reporter.community.saves.store', $ticket))
            ->assertNotFound();
    }

    // ── E. Validation ─────────────────────────────────────────────────────────

    public function test_invalid_reaction_type_returns_json_validation_error(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->postJson(
                route('reporter.community.reactions.store', $ticket),
                ['type' => 'not_a_real_type']
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    // ── F. No-PII in JSON ─────────────────────────────────────────────────────

    public function test_reaction_store_json_does_not_expose_pii(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $secret = User::factory()->create([
            'name' => 'SecretPersonOPT',
            'email' => 'secret-opt@test.test',
        ]);
        $ticket = $this->makeVisibleTicket($reporter);

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $secret->id,
            'type' => 'interested',
        ]);

        $response = $this->actingAs($reporter)
            ->postJson(
                route('reporter.community.reactions.store', $ticket),
                ['type' => 'interested']
            )
            ->assertOk();

        $body = $response->content();

        $this->assertStringNotContainsString('SecretPersonOPT', $body);
        $this->assertStringNotContainsString('secret-opt@test.test', $body);
        $this->assertStringNotContainsString('reporter_id', $body);
        $this->assertStringNotContainsString('assigned_to', $body);
        $this->assertStringNotContainsString('user_id', $body);
    }

    public function test_save_store_json_does_not_expose_pii(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $response = $this->actingAs($reporter)
            ->postJson(route('reporter.community.saves.store', $ticket))
            ->assertOk();

        $body = $response->content();

        $this->assertStringNotContainsString('reporter_id', $body);
        $this->assertStringNotContainsString('assigned_to', $body);
        $this->assertStringNotContainsString('user_id', $body);
        $this->assertStringNotContainsString('@', $body);
    }

    // ── G. Fallback redirect (no Accept: application/json) ────────────────────

    public function test_reaction_store_without_json_accept_redirects_back(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->post(
                route('reporter.community.reactions.store', $ticket),
                ['type' => 'interested']
            )
            ->assertRedirect();
    }

    public function test_reaction_destroy_without_json_accept_redirects_back(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'seen',
        ]);

        $this->actingAs($reporter)
            ->delete(route('reporter.community.reactions.destroy', [$ticket, 'seen']))
            ->assertRedirect();
    }

    public function test_save_store_without_json_accept_redirects_back(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        $this->actingAs($reporter)
            ->post(route('reporter.community.saves.store', $ticket))
            ->assertRedirect();
    }

    public function test_save_destroy_without_json_accept_redirects_back(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter);

        CommunitySave::create(['ticket_id' => $ticket->id, 'user_id' => $reporter->id]);

        $this->actingAs($reporter)
            ->delete(route('reporter.community.saves.destroy', $ticket))
            ->assertRedirect();
    }

    // ── H. HTML attributes / progressive enhancement ─────────────────────────

    public function test_feed_renders_data_community_social_form_attributes(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeVisibleTicket($reporter, 'Ticket OPT attr DCSF');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('data-community-social-form', false);
    }

    public function test_feed_renders_data_community_action_button(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeVisibleTicket($reporter, 'Ticket OPT btn DCAB');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('data-community-action-button', false);
    }

    public function test_feed_renders_aria_pressed_false_for_inactive_reaction(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeVisibleTicket($reporter, 'Ticket OPT aria ARPR');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('aria-pressed="false"', false);
    }

    public function test_feed_renders_aria_pressed_true_for_active_reaction(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket OPT active aria ARPA');

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => 'interested',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('aria-pressed="true"', false);
    }

    public function test_feed_renders_store_and_destroy_url_data_attributes(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeVisibleTicket($reporter, 'Ticket OPT urls DURLS');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('data-store-url', false)
            ->assertSee('data-destroy-url', false);
    }

    public function test_save_form_renders_data_active_false_when_not_saved(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeVisibleTicket($reporter, 'Ticket OPT save data SDAF');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->content();

        $this->assertStringContainsString('data-community-action="save"', $html);
    }

    public function test_buttons_render_without_disabled_attribute(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeVisibleTicket($reporter, 'Ticket OPT nodisable NDIS');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->content();

        // JS-managed loading/disabled — no disabled attr on render.
        $this->assertStringNotContainsString('<button type="submit" disabled', $html);
    }

    public function test_feed_renders_aria_live_polite_status_region(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeVisibleTicket($reporter, 'Ticket OPT arialive ALIV');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('aria-live="polite"', false)
            ->assertSee('data-community-social-status', false);
    }

    // ── I. Regression ─────────────────────────────────────────────────────────

    public function test_saved_filter_still_works_after_optimistic_changes(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $saved = $this->makeVisibleTicket($reporter, 'Ticket guardado OPT SVOPT');
        $notSaved = $this->makeVisibleTicket($reporter, 'Ticket libre OPT NSVOPT');

        CommunitySave::create(['ticket_id' => $saved->id, 'user_id' => $reporter->id]);

        $this->actingAs($reporter)
            ->get(route('reporter.community').'?saved=1')
            ->assertOk()
            ->assertSee('Ticket guardado OPT SVOPT', false)
            ->assertDontSee('Ticket libre OPT NSVOPT', false);
    }

    public function test_counts_in_feed_are_aggregate_only_and_do_not_expose_who_reacted(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $secret = User::factory()->create([
            'name' => 'ReactorHiddenOPT',
            'email' => 'reactor-hidden-opt@test.test',
        ]);
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket aggregate OPT AGGOPT');

        CommunityReaction::create([
            'ticket_id' => $ticket->id,
            'user_id' => $secret->id,
            'type' => 'interested',
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('ReactorHiddenOPT', false)
            ->assertDontSee('reactor-hidden-opt@test.test', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleTicket(
        ?User $reporter = null,
        string $title = 'Ticket OPT visible TEST',
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        $reporter ??= $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción para test optimistic social.',
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
            'title' => 'Ticket oculto OPT TEST',
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
            'community_visibility_reason' => 'Test ocultamiento OPT',
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
            'name' => 'Sala OPT Test',
            'building' => 'Edificio OPT',
            'floor' => '1',
            'room_code' => 'OPT-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-opt-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'CatOPT-'.Str::lower(Str::random(5)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests optimistic',
        ]);
    }
}
