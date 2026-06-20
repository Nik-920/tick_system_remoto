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
 * Community Moderation Queue — admin-only central panel for managing community visibility.
 *
 * Coverage:
 *   A) Access: guest / reporter / maintenance are blocked; admin / super_admin pass.
 *   B) Render: title, visible tickets, hidden tickets, reason, link to show.
 *   C) Filters: q, category, building, visibility, state, period.
 *   D) Actions: hide and restore from the queue (reuses existing controller routes).
 *   E) No-leak: moderation metadata does not bleed into reporter community feed.
 */
class CommunityModerationQueueTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Access ─────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.community.moderation'))
            ->assertRedirect(route('login'));
    }

    public function test_reporter_cannot_access_moderation_queue(): void
    {
        $this->actingAs($this->createUserWithRole('reporter'))
            ->get(route('admin.community.moderation'))
            ->assertForbidden();
    }

    public function test_maintenance_cannot_access_moderation_queue(): void
    {
        $this->actingAs($this->createUserWithRole('maintenance'))
            ->get(route('admin.community.moderation'))
            ->assertForbidden();
    }

    public function test_admin_can_access_moderation_queue(): void
    {
        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk();
    }

    public function test_super_admin_can_access_moderation_queue(): void
    {
        $this->actingAs($this->createUserWithRole('super_admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk();
    }

    // ── B. Render ─────────────────────────────────────────────────────────────

    public function test_queue_shows_title_moderacion_de_comunidad(): void
    {
        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Moderación de Comunidad', false);
    }

    public function test_queue_shows_visible_tickets_by_default(): void
    {
        $ticket = $this->makeVisibleTicket(title: 'Grifo roto visible CMQTEST001');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('CMQTEST001', false);
    }

    public function test_queue_shows_hidden_tickets_when_filter_visibility_hidden(): void
    {
        $ticket = $this->makeHiddenTicket(title: 'Ticket oculto CMQTEST002');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee('CMQTEST002', false);
    }

    public function test_queue_shows_visibility_reason_for_hidden_tickets(): void
    {
        $this->makeHiddenTicket(reason: 'Contenido sensible CMQTEST003R');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee('Contenido sensible CMQTEST003R', false);
    }

    public function test_queue_shows_link_to_ticket_show(): void
    {
        $ticket = $this->makeVisibleTicket(title: 'Enlace a ficha CMQTEST004');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee(route('tickets.show', $ticket), false);
    }

    public function test_queue_shows_hide_form_for_visible_tickets(): void
    {
        $this->makeVisibleTicket(title: 'Ticket para ocultar CMQTEST005');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Ocultar', false);
    }

    public function test_queue_shows_restore_form_for_hidden_tickets(): void
    {
        $this->makeHiddenTicket(title: 'Ticket para restaurar CMQTEST006');

        $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee('Restaurar', false);
    }

    // ── C. Filters ────────────────────────────────────────────────────────────

    public function test_filter_by_search_query_matches_title(): void
    {
        $this->makeVisibleTicket(title: 'Grieta en pared CMQFILTER001');
        $this->makeVisibleTicket(title: 'Cable suelto CMQFILTER002');

        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['q' => 'Grieta']));

        $response->assertOk()
            ->assertSee('CMQFILTER001', false)
            ->assertDontSee('CMQFILTER002', false);
    }

    public function test_filter_by_category(): void
    {
        $catA = $this->makeCategory('Eléctrica-CMQF');
        $catB = $this->makeCategory('Plomería-CMQF');

        $this->makeVisibleTicket(title: 'Ticket cat A CMQFILTER003', category: $catA);
        $this->makeVisibleTicket(title: 'Ticket cat B CMQFILTER004', category: $catB);

        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['category' => $catA->id]));

        $response->assertOk()
            ->assertSee('CMQFILTER003', false)
            ->assertDontSee('CMQFILTER004', false);
    }

    public function test_filter_by_building(): void
    {
        $locA = $this->makeLocation(building: 'Edificio-A-CMQF');
        $locB = $this->makeLocation(building: 'Edificio-B-CMQF');

        $this->makeVisibleTicket(title: 'Ticket edificio A CMQFILTER005', location: $locA);
        $this->makeVisibleTicket(title: 'Ticket edificio B CMQFILTER006', location: $locB);

        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['building' => 'Edificio-A-CMQF']));

        $response->assertOk()
            ->assertSee('CMQFILTER005', false)
            ->assertDontSee('CMQFILTER006', false);
    }

    public function test_filter_visibility_visible_shows_only_visible_tickets(): void
    {
        $this->makeVisibleTicket(title: 'Solo visible CMQFILTER007');
        $this->makeHiddenTicket(title: 'Solo oculto CMQFILTER008');

        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['visibility' => 'visible']));

        $response->assertOk()
            ->assertSee('CMQFILTER007', false)
            ->assertDontSee('CMQFILTER008', false);
    }

    public function test_filter_visibility_hidden_shows_only_hidden_tickets(): void
    {
        $this->makeVisibleTicket(title: 'Visible excluido CMQFILTER009');
        $this->makeHiddenTicket(title: 'Oculto incluido CMQFILTER010');

        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']));

        $response->assertOk()
            ->assertDontSee('CMQFILTER009', false)
            ->assertSee('CMQFILTER010', false);
    }

    public function test_filter_by_state(): void
    {
        $this->makeVisibleTicket(title: 'Open CMQFILTER011', state: Ticket::STATE_OPEN);
        $this->makeVisibleTicket(title: 'Resolved CMQFILTER012', state: Ticket::STATE_RESOLVED);

        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['state' => 'open']));

        $response->assertOk()
            ->assertSee('CMQFILTER011', false)
            ->assertDontSee('CMQFILTER012', false);
    }

    public function test_filter_by_period_excludes_old_tickets(): void
    {
        $oldTicket = $this->makeVisibleTicket(title: 'Old ticket CMQFILTER013');
        $oldTicket->forceFill(['created_at' => now()->subDays(40)])->save();

        $newTicket = $this->makeVisibleTicket(title: 'New ticket CMQFILTER014');

        $response = $this->actingAs($this->createUserWithRole('admin'))
            ->get(route('admin.community.moderation', ['period' => '30d']));

        $response->assertOk()
            ->assertSee('CMQFILTER014', false)
            ->assertDontSee('CMQFILTER013', false);
    }

    // ── D. Actions ────────────────────────────────────────────────────────────

    public function test_admin_can_hide_ticket_from_queue_using_existing_route(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Test desde queue'])
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'community_visible' => false,
        ]);
    }

    public function test_admin_can_restore_ticket_from_queue_using_existing_route(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeHiddenTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket))
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'community_visible' => true,
        ]);
    }

    public function test_hide_reason_is_required_from_queue(): void
    {
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket();

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    public function test_hidden_ticket_disappears_from_reporter_community_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeVisibleTicket(title: 'Desaparece del feed CMQACTION001', reporter: $reporter);

        $this->actingAs($admin)
            ->patch(route('tickets.community.hide', $ticket), ['reason' => 'Test queue feed']);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('CMQACTION001', false);
    }

    public function test_restored_ticket_returns_to_reporter_community_feed(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeHiddenTicket(title: 'Vuelve al feed CMQACTION002', reporter: $reporter);

        $this->actingAs($admin)
            ->patch(route('tickets.community.restore', $ticket));

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('CMQACTION002', false);
    }

    public function test_cancelled_ticket_does_not_appear_in_feed_even_after_restore(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $admin = $this->createUserWithRole('admin');
        $ticket = $this->makeHiddenTicket(
            title: 'Cancelado restaurado CMQACTION003',
            reporter: $reporter,
            state: Ticket::STATE_CANCELLED,
        );

        $this->actingAs($admin)->patch(route('tickets.community.restore', $ticket));

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('CMQACTION003', false);
    }

    // ── E. No-leak ────────────────────────────────────────────────────────────

    public function test_reporter_community_does_not_show_visibility_reason(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeHiddenTicket(reason: 'Razón secreta de moderación NOLEAK001');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('NOLEAK001', false);
    }

    public function test_queue_does_not_affect_reporter_community_visibility_rules(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $visibleTicket = $this->makeVisibleTicket(title: 'Sí aparece en feed NOLEAK002', reporter: $reporter);

        // Admin views the queue — this should NOT affect the feed.
        $admin = $this->createUserWithRole('admin');
        $this->actingAs($admin)->get(route('admin.community.moderation'))->assertOk();

        // Reporter feed should still show the visible ticket.
        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('NOLEAK002', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeVisibleTicket(
        string $title = 'Ticket visible CMQTEST',
        string $state = Ticket::STATE_OPEN,
        ?User $reporter = null,
        ?Category $category = null,
        ?Location $location = null,
    ): Ticket {
        $reporter ??= $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de prueba CMQ.',
            'reporter_id' => $reporter->id,
            'location_id' => ($location ?? $this->makeLocation())->id,
            'category_id' => ($category ?? $this->makeCategory())->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeHiddenTicket(
        string $title = 'Ticket oculto CMQTEST',
        string $state = Ticket::STATE_OPEN,
        string $reason = 'Motivo de prueba CMQ',
        ?User $reporter = null,
        ?User $hiddenBy = null,
    ): Ticket {
        $reporter ??= $this->createUserWithRole('reporter');
        $moderator = $hiddenBy ?? $this->createUserWithRole('admin');

        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripción de prueba CMQ oculto.',
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

    private function makeLocation(string $building = 'Edificio Test CMQ'): Location
    {
        return Location::create([
            'name' => 'Sala CMQ-'.Str::upper(Str::random(4)),
            'building' => $building,
            'floor' => '1',
            'room_code' => 'CMQ-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-cmq-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(string $name = ''): Category
    {
        return Category::create([
            'name' => $name !== '' ? $name : 'Cat-CMQ-'.Str::lower(Str::random(5)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests CMQ',
        ]);
    }
}
