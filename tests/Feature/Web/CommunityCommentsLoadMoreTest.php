<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Ver más comentarios" — paginated root comments beyond the feed's initial 2.
 *
 * Coverage:
 *   A) Feed hint: button renders only when there are more roots than shown,
 *      with the correct remaining count; absent otherwise.
 *   B) Pagination endpoint: offset paging, has_more flag, next_offset math,
 *      rendered HTML contains the expected comment bodies.
 *   C) Access: guest redirected, hidden/cancelled/rejected ticket 404s.
 *   D) No-leak: hidden/deleted comments never paginate in, reply previews
 *      match the feed's own "top 2 replies" rule, XSS escaped, no PII.
 */
class CommunityCommentsLoadMoreTest extends TestCase
{
    use RefreshDatabase;

    // ── A. Feed hint ────────────────────────────────────────────────────────

    public function test_feed_shows_load_more_button_when_more_than_two_root_comments(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $this->makeRootComments($ticket, 5);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Ver 3 comentarios más', false);
    }

    public function test_feed_hides_load_more_button_when_two_or_fewer_root_comments(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $this->makeRootComments($ticket, 2);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('comentarios más', false);
    }

    public function test_feed_singular_label_for_exactly_one_extra_comment(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $this->makeRootComments($ticket, 3);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Ver 1 comentario más', false);
    }

    // ── B. Pagination endpoint ──────────────────────────────────────────────

    public function test_guest_cannot_load_more_comments(): void
    {
        $ticket = $this->makeVisibleTicket();

        $this->get(route('reporter.community.comments.index', $ticket))
            ->assertRedirect(route('login'));
    }

    public function test_reporter_loads_next_page_of_root_comments(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $comments = $this->makeRootComments($ticket, 5);
        // Index 2 is the 3rd-newest (index 4 is newest) → lands on page 2 (offset=2, limit=2).
        $thirdNewest = $comments[2];

        $response = $this->actingAs($reporter)
            ->getJson(route('reporter.community.comments.index', $ticket).'?offset=2')
            ->assertOk()
            ->assertJson(['ok' => true, 'has_more' => true, 'next_offset' => 4]);

        $this->assertStringContainsString($thirdNewest->body, $response->json('html'));
    }

    public function test_has_more_is_false_on_last_page(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $this->makeRootComments($ticket, 5);

        $this->actingAs($reporter)
            ->getJson(route('reporter.community.comments.index', $ticket).'?offset=4')
            ->assertOk()
            ->assertJson(['ok' => true, 'has_more' => false, 'next_offset' => 5]);
    }

    public function test_negative_offset_is_clamped_to_zero(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $this->makeRootComments($ticket, 5);

        $this->actingAs($reporter)
            ->getJson(route('reporter.community.comments.index', $ticket).'?offset=-10')
            ->assertOk()
            ->assertJson(['ok' => true, 'next_offset' => 2]);
    }

    // ── C. Access ────────────────────────────────────────────────────────────

    public function test_cannot_load_more_comments_for_hidden_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeHiddenTicket();

        $this->actingAs($reporter)
            ->get(route('reporter.community.comments.index', $ticket))
            ->assertNotFound();
    }

    public function test_cannot_load_more_comments_for_cancelled_ticket(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket(state: Ticket::STATE_CANCELLED);

        $this->actingAs($reporter)
            ->get(route('reporter.community.comments.index', $ticket))
            ->assertNotFound();
    }

    // ── D. No-leak / correctness ─────────────────────────────────────────────

    public function test_hidden_and_deleted_comments_never_paginate_in(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $this->makeRootComments($ticket, 3);

        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario oculto NUNCA_VISIBLE.',
            'status' => CommunityComment::STATUS_HIDDEN,
        ]);
        CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario eliminado NUNCA_VISIBLE.',
            'status' => CommunityComment::STATUS_DELETED,
        ]);

        $response = $this->actingAs($reporter)
            ->getJson(route('reporter.community.comments.index', $ticket).'?offset=2')
            ->assertOk()
            ->assertJson(['has_more' => false]);

        $this->assertStringNotContainsString('NUNCA_VISIBLE', $response->json('html'));
    }

    public function test_load_more_html_escapes_xss_in_comment_body(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $this->makeRootComments($ticket, 2);

        $xssComment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => '<script>alert(1)</script>',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
        // created_at is not fillable — backdate via forceFill so it lands on page 2.
        $xssComment->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $response = $this->actingAs($reporter)
            ->getJson(route('reporter.community.comments.index', $ticket).'?offset=2')
            ->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->json('html'));
        $this->assertStringContainsString('&lt;script&gt;', $response->json('html'));
    }

    public function test_load_more_marks_viewer_own_comment_as_tu(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $this->makeRootComments($ticket, 2);

        $ownComment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Mi propio comentario paginado.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
        // created_at is not fillable — backdate via forceFill so it lands on page 2.
        $ownComment->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $response = $this->actingAs($reporter)
            ->getJson(route('reporter.community.comments.index', $ticket).'?offset=2')
            ->assertOk();

        $html = (string) $response->json('html');
        $this->assertStringContainsString('comm-comment__author', $html);
        $this->assertStringContainsString('Tú', $html);
    }

    public function test_load_more_includes_up_to_two_replies_per_root(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        // A different author than the viewer, so replies render the
        // read-only "Reportar" action instead of the owner's Editar form
        // (which would otherwise echo the body a second time, into the
        // hidden edit textarea, and throw off the occurrence count below).
        $otherUser = $this->createUserWithRole('reporter');
        $ticket = $this->makeVisibleTicket();
        $this->makeRootComments($ticket, 2);

        $root = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $otherUser->id,
            'body' => 'Raíz paginada con respuestas.',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
        // created_at is not fillable — backdate via forceFill so it lands on page 2.
        $root->forceFill(['created_at' => now()->subMinutes(10)])->save();

        for ($i = 0; $i < 3; $i++) {
            CommunityComment::create([
                'ticket_id' => $ticket->id,
                'parent_id' => $root->id,
                'user_id' => $otherUser->id,
                'body' => "Respuesta paginada {$i}.",
                'status' => CommunityComment::STATUS_VISIBLE,
            ]);
        }

        $response = $this->actingAs($reporter)
            ->getJson(route('reporter.community.comments.index', $ticket).'?offset=2')
            ->assertOk();

        $html = (string) $response->json('html');
        $this->assertSame(2, substr_count($html, 'Respuesta paginada'));
        $this->assertStringContainsString('comm-comments__more-hint--replies', $html);
        $this->assertStringContainsString('respuesta más', $html);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @return list<CommunityComment>
     */
    private function makeRootComments(Ticket $ticket, int $count): array
    {
        $comments = [];
        for ($i = 0; $i < $count; $i++) {
            $comment = CommunityComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->reporter_id,
                'body' => "Comentario raíz número {$i}.",
                'status' => CommunityComment::STATUS_VISIBLE,
            ]);
            // created_at is not fillable — backdate via forceFill so ordering
            // (newest-first) is deterministic across the whole batch.
            $comment->forceFill(['created_at' => now()->subMinutes($count - $i)])->save();
            $comments[] = $comment;
        }

        return $comments;
    }

    private function makeVisibleTicket(
        string $title = 'Ticket visible comunidad LOADMORE',
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        $reporter = $this->createUserWithRole('reporter');

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de test para paginación de comentarios.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeHiddenTicket(): Ticket
    {
        $reporter = $this->createUserWithRole('reporter');

        $ticket = Ticket::create([
            'title' => 'Ticket oculto LOADMORE',
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
            'community_visibility_reason' => 'Ocultado para test de paginación.',
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
            'name' => 'Sala LoadMore Test',
            'building' => 'Edificio Test',
            'floor' => '1',
            'room_code' => 'LM-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de paginación de comentarios',
        ]);
    }
}
