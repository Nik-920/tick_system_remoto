<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community comments now show the commenter's display name + role badge
 * instead of the generic "Reporter de la comunidad" label (product decision:
 * the ticket/post author stays anonymous, but comment authorship does not).
 *
 * Coverage:
 *   A) Author label + role per role type, for root comments and replies.
 *   B) Fallback label for a deleted/roleless commenter (user_id null).
 *   C) Avatar initials derived from the display name.
 *   D) Privacy: no email, no user_id/reporter_id/assigned_to keys.
 *   E) No N+1: the users batch lookup does not scale with ticket/comment count.
 */
class CommunityCommentAuthorDisplayTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Display name + role ──────────────────────────────────────────────

    public function test_reporter_comment_shows_full_display_name(): void
    {
        $viewer = $this->reporter();
        $commenter = $this->reporter();
        $commenter->update(['name' => 'Nik Denilson', 'last_name' => 'Ramirez']);
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $commenter);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Nik Denilson Ramirez', false)
            ->assertSee('comm-comment-v2__role--reporter', false);
    }

    public function test_maintenance_comment_shows_full_display_name(): void
    {
        $viewer = $this->reporter();
        $tech = $this->createUserWithRole('maintenance');
        $tech->update(['name' => 'Maintenance', 'last_name' => 'Admin']);
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $tech);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Maintenance Admin', false)
            ->assertSee('comm-comment-v2__role--maintenance', false);
    }

    public function test_admin_comment_shows_full_display_name(): void
    {
        $viewer = $this->reporter();
        $admin = $this->createUserWithRole('admin');
        $admin->update(['name' => 'Admin', 'last_name' => 'Sistema']);
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $admin);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Admin Sistema', false)
            ->assertSee('comm-comment-v2__role--admin', false);
    }

    public function test_commenter_without_last_name_shows_first_name_only(): void
    {
        $viewer = $this->reporter();
        $commenter = $this->reporter();
        $commenter->update(['name' => 'SoloNombre', 'last_name' => null]);
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $commenter);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('SoloNombre', false);
    }

    public function test_reply_shows_display_name_and_role(): void
    {
        $viewer = $this->reporter();
        $rootAuthor = $this->reporter();
        $replyAuthor = $this->createUserWithRole('maintenance');
        $replyAuthor->update(['name' => 'Tecnico', 'last_name' => 'Mantenimiento']);
        $ticket = $this->visibleTicket();
        $root = $this->makeComment($ticket, $rootAuthor);
        $this->makeReply($ticket, $root, $replyAuthor);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Tecnico Mantenimiento', false)
            ->assertSee('comm-comment-v2__role--maintenance', false);
    }

    public function test_own_comment_still_shows_tu_regardless_of_display_name(): void
    {
        $viewer = $this->reporter();
        $viewer->update(['name' => 'Nombre', 'last_name' => 'Propio']);
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $viewer);

        $response = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk();

        $response->assertSee('Tú', false);
        $response->assertDontSee('Nombre Propio', false);
    }

    // ── B. Fallback for deleted/roleless commenter ────────────────────────────

    public function test_comment_with_no_user_shows_generic_fallback(): void
    {
        $viewer = $this->reporter();
        $ticket = $this->visibleTicket();

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'body' => 'Comentario de usuario eliminado.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Usuario de la comunidad', false)
            ->assertSee('comm-comment-v2__avatar--unknown', false)
            ->assertSee('comm-comment-v2__role--unknown', false)
            ->assertSee('UC', false);
    }

    // ── C. Avatar initials ────────────────────────────────────────────────────

    public function test_avatar_shows_initials_derived_from_display_name(): void
    {
        $viewer = $this->reporter();
        $commenter = $this->reporter();
        $commenter->update(['name' => 'Nik', 'last_name' => 'Denilson']);
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $commenter);

        $html = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/comm-comment-v2__avatar--reporter"\s*aria-hidden="true">\s*ND\s*</',
            (string) $html
        );
    }

    // ── D. Privacy / no-leak ───────────────────────────────────────────────────

    public function test_does_not_expose_commenter_email(): void
    {
        $viewer = $this->reporter();
        $commenter = User::factory()->create(['email' => 'oculto-autor@test.test']);
        $commenter->assignRole('reporter');
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $commenter);

        $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('oculto-autor@test.test', false);
    }

    public function test_does_not_expose_internal_id_keys(): void
    {
        $viewer = $this->reporter();
        $commenter = $this->reporter();
        $ticket = $this->visibleTicket();
        $this->makeComment($ticket, $commenter);

        $html = $this->actingAs($viewer)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('"user_id"', (string) $html);
        $this->assertStringNotContainsString('"reporter_id"', (string) $html);
        $this->assertStringNotContainsString('"assigned_to"', (string) $html);
    }

    // ── E. No N+1 on the commenter batch lookup ───────────────────────────────

    public function test_commenter_batch_lookup_does_not_grow_with_ticket_count(): void
    {
        $viewer = $this->reporter();

        $this->visibleTicket('Ticket batch autor A');
        $ticketWithComment = $this->visibleTicket('Ticket batch autor B');
        $this->makeComment($ticketWithComment, $this->reporter());

        DB::enableQueryLog();
        $this->actingAs($viewer)->get(route('reporter.community'))->assertOk();
        $baseline = $this->countUsersTableQueries(DB::getQueryLog());
        DB::flushQueryLog();

        foreach (range(1, 4) as $i) {
            $ticket = $this->visibleTicket("Ticket batch autor extra {$i}");
            $this->makeComment($ticket, $this->reporter(), "Comentario extra {$i}.");
        }

        $this->actingAs($viewer)->get(route('reporter.community'))->assertOk();
        $afterMoreTickets = $this->countUsersTableQueries(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($baseline, $afterMoreTickets);
    }

    /**
     * @param  list<array{query: string}>  $log
     */
    private function countUsersTableQueries(array $log): int
    {
        return collect($log)
            ->filter(fn ($entry) => preg_match('/from ["`]users["`]/i', (string) $entry['query']) === 1)
            ->count();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function reporter(): User
    {
        return $this->createUserWithRole('reporter');
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

    private function visibleTicket(string $title = 'Ticket autor display CADT'): Ticket
    {
        $reporter = $this->reporter();

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de test de autoría de comentarios.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeComment(
        Ticket $ticket,
        User $user,
        string $body = 'Comentario de prueba de autoría.',
    ): CommunityComment {
        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => $body,
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    private function makeReply(
        Ticket $ticket,
        CommunityComment $parent,
        User $user,
        string $body = 'Respuesta de prueba de autoría.',
    ): CommunityComment {
        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'parent_id' => $parent->id,
            'user_id' => $user->id,
            'body' => $body,
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Sala Autor Display Test',
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'CD-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de autoría de comentarios',
        ]);
    }
}
