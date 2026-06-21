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
 * Reporter "Mis tickets" comments modal — render, JSON API, store, no-leak.
 *
 * Security contract under test:
 *  - Ownership: only the reporter who filed the ticket can fetch its comments.
 *  - No-leak: foreign ticket → 404 (identical to non-existent).
 *  - Only STATUS_VISIBLE comments/replies reach the client.
 *  - Response never includes PII (email, names, reporter_id, assigned_to,
 *    assigned_by, hidden_reason, previous_body, new_body).
 *  - Comment creation mirrors community rules (community_visible + state).
 */
class ReporterTicketCommentsModalTest extends TestCase
{
    use RefreshDatabase;

    // ── Render ───────────────────────────────────────────────────────────────

    public function test_board_renders_comment_trigger_button_per_card(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertSee('data-reporter-ticket-comments-trigger', false);
    }

    public function test_comment_trigger_has_aria_haspopup_dialog(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertSee('aria-haspopup="dialog"', false);
    }

    public function test_modal_partial_rendered_once(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me);
        $this->ticketFor($me);

        $html = $this->actingAs($me)
            ->get(route('reporter.tickets.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count((string) $html, 'id="reporter-ticket-comments-modal"'));
    }

    public function test_modal_has_role_dialog(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
    }

    public function test_modal_has_close_button_with_aria_label(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertSee('aria-label="Cerrar comentarios"', false);
    }

    public function test_trigger_carries_comments_url_data_attribute(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me);

        $response = $this->actingAs($me)->get(route('reporter.tickets.index'));

        $response->assertOk();
        $response->assertSee(
            route('reporter.tickets.comments.index', $ticket->id),
            false
        );
    }

    public function test_html_page_does_not_expose_raw_reporter_id(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me);

        $html = $this->actingAs($me)
            ->get(route('reporter.tickets.index'))
            ->assertOk()
            ->getContent();

        // The reporter_id should never appear as a raw data-attribute
        $this->assertStringNotContainsString(
            'data-reporter-id="'.$ticket->reporter_id.'"',
            (string) $html
        );
    }

    // ── JSON GET endpoint ────────────────────────────────────────────────────

    public function test_reporter_can_fetch_comments_for_own_ticket(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me);

        $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk()
            ->assertJsonFragment(['ok' => true])
            ->assertJsonStructure(['ok', 'ticket_id', 'ticket_ref', 'count', 'can_comment', 'comments']);
    }

    public function test_json_includes_ticket_ref_and_count(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me);
        $this->visibleComment($ticket, $me);

        $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk()
            ->assertJsonFragment(['count' => 1])
            ->assertJsonPath('ticket_ref', '#'.strtoupper(substr((string) $ticket->id, 0, 8)));
    }

    public function test_json_includes_only_visible_comments(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me);
        $this->visibleComment($ticket, $me);
        $this->hiddenComment($ticket, $me);
        $this->deletedComment($ticket, $me);

        $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk()
            ->assertJsonFragment(['count' => 1]);
    }

    public function test_json_excludes_hidden_and_deleted_comments(): void
    {
        $me = $this->reporter();
        $other = $this->reporter();
        $ticket = $this->ticketFor($me);
        $hidden = $this->hiddenComment($ticket, $other);
        $deleted = $this->deletedComment($ticket, $other);

        $response = $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk();

        $this->assertStringNotContainsString(
            (string) $hidden->body,
            $response->getContent()
        );
        $this->assertStringNotContainsString(
            (string) $deleted->body,
            $response->getContent()
        );
    }

    public function test_json_includes_visible_replies(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me);
        $parent = $this->visibleComment($ticket, $me);
        $reply = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'parent_id' => $parent->id,
            'user_id' => $me->id,
            'body' => 'Una respuesta visible',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $response = $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk();

        $this->assertStringContainsString('Una respuesta visible', $response->getContent());
    }

    public function test_reporter_cannot_fetch_comments_for_another_reporters_ticket(): void
    {
        $me = $this->reporter();
        $other = $this->reporter();
        $theirs = $this->ticketFor($other);

        $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $theirs->id))
            ->assertNotFound();
    }

    public function test_guest_cannot_fetch_comments(): void
    {
        $ticket = $this->ticketFor($this->reporter());

        $this->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertUnauthorized();
    }

    public function test_json_does_not_expose_email_or_phone(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me);
        $this->visibleComment($ticket, $me);

        $content = $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($me->email, (string) $content);
        $this->assertStringNotContainsString((string) $me->id, (string) $content);
    }

    public function test_json_does_not_expose_hidden_reason_or_edit_log_fields(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me);
        $this->visibleComment($ticket, $me);

        $content = $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('hidden_reason', (string) $content);
        $this->assertStringNotContainsString('previous_body', (string) $content);
        $this->assertStringNotContainsString('new_body', (string) $content);
        $this->assertStringNotContainsString('assigned_to', (string) $content);
        $this->assertStringNotContainsString('assigned_by', (string) $content);
    }

    public function test_author_label_is_tu_for_own_comments(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me);
        $this->visibleComment($ticket, $me);

        $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk()
            ->assertJsonPath('comments.0.author_label', 'Tú')
            ->assertJsonPath('comments.0.owned_by_viewer', true);
    }

    public function test_author_label_is_generic_for_other_reporters(): void
    {
        $me = $this->reporter();
        $other = $this->reporter();
        $ticket = $this->ticketFor($me);
        $this->visibleComment($ticket, $other);

        $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk()
            ->assertJsonPath('comments.0.author_label', 'Reporter de la comunidad')
            ->assertJsonPath('comments.0.owned_by_viewer', false);
    }

    // ── can_comment flag ─────────────────────────────────────────────────────

    public function test_can_comment_true_when_community_visible_and_open(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me, 'open', community_visible: true);

        $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk()
            ->assertJsonPath('can_comment', true);
    }

    public function test_can_comment_false_when_not_community_visible(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me, 'open', community_visible: false);

        $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk()
            ->assertJsonPath('can_comment', false);
    }

    public function test_can_comment_false_when_cancelled(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me, 'cancelled', community_visible: true);

        $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk()
            ->assertJsonPath('can_comment', false);
    }

    // ── POST store ───────────────────────────────────────────────────────────

    public function test_reporter_can_create_comment_on_community_visible_open_ticket(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me, 'open', community_visible: true);

        $this->actingAs($me)
            ->postJson(route('reporter.tickets.comments.store', $ticket->id), ['body' => 'Nuevo comentario'])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('comment.body', 'Nuevo comentario')
            ->assertJsonPath('comment.author_label', 'Tú')
            ->assertJsonPath('comment.owned_by_viewer', true);

        $this->assertDatabaseHas('community_comments', [
            'ticket_id' => $ticket->id,
            'body' => 'Nuevo comentario',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    public function test_created_comment_appears_in_subsequent_fetch(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me, 'open', community_visible: true);

        $this->actingAs($me)
            ->postJson(route('reporter.tickets.comments.store', $ticket->id), ['body' => 'Comentario persistido']);

        $this->actingAs($me)
            ->getJson(route('reporter.tickets.comments.index', $ticket->id))
            ->assertOk()
            ->assertJsonFragment(['body' => 'Comentario persistido']);
    }

    public function test_empty_body_returns_validation_error(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me, 'open', community_visible: true);

        $this->actingAs($me)
            ->postJson(route('reporter.tickets.comments.store', $ticket->id), ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_too_short_body_returns_validation_error(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me, 'open', community_visible: true);

        $this->actingAs($me)
            ->postJson(route('reporter.tickets.comments.store', $ticket->id), ['body' => 'X'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    public function test_store_fails_when_ticket_not_community_visible(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me, 'open', community_visible: false);

        $this->actingAs($me)
            ->postJson(route('reporter.tickets.comments.store', $ticket->id), ['body' => 'Intento'])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false);
    }

    public function test_store_fails_on_cancelled_ticket(): void
    {
        $me = $this->reporter();
        $ticket = $this->ticketFor($me, 'cancelled', community_visible: true);

        $this->actingAs($me)
            ->postJson(route('reporter.tickets.comments.store', $ticket->id), ['body' => 'Intento'])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false);
    }

    public function test_store_cannot_comment_on_another_reporters_ticket(): void
    {
        $me = $this->reporter();
        $other = $this->reporter();
        $theirs = $this->ticketFor($other, 'open', community_visible: true);

        $this->actingAs($me)
            ->postJson(route('reporter.tickets.comments.store', $theirs->id), ['body' => 'Ajeno'])
            ->assertNotFound();
    }

    public function test_guest_cannot_store_comment(): void
    {
        $ticket = $this->ticketFor($this->reporter(), 'open', community_visible: true);

        $this->postJson(route('reporter.tickets.comments.store', $ticket->id), ['body' => 'X Y'])
            ->assertUnauthorized();
    }

    // ── Regression ───────────────────────────────────────────────────────────

    public function test_board_page_still_renders_after_changes(): void
    {
        $me = $this->reporter();
        $this->ticketFor($me);

        $this->actingAs($me)
            ->get(route('reporter.tickets.index'))
            ->assertOk()
            ->assertViewIs('tickets.reporter.index');
    }

    public function test_non_reporter_roles_cannot_access_comments_endpoint(): void
    {
        $ticket = $this->ticketFor($this->reporter());

        foreach (['maintenance', 'admin', 'super_admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->getJson(route('reporter.tickets.comments.index', $ticket->id))
                ->assertForbidden();
        }
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function reporter(): User
    {
        return $this->userWithRole('reporter');
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

    private function ticketFor(User $reporter, string $state = 'open', bool $community_visible = false): Ticket
    {
        $location = Location::firstOrCreate(
            ['room_code' => 'LAB-CM'],
            [
                'name' => 'Laboratorio CM',
                'building' => 'Edificio A',
                'floor' => '1',
                'qr_token' => 'qr-'.Str::uuid()->toString(),
                'is_active' => true,
            ],
        );
        $category = Category::firstOrCreate(
            ['name' => 'Hardware-CM'],
            ['icon' => 'monitor', 'description' => 'Hardware CM'],
        );

        $ticket = Ticket::create([
            'title' => 'Test ticket '.uniqid(),
            'description' => 'Descripción de prueba',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => $community_visible,
        ]);

        return $ticket;
    }

    private function visibleComment(Ticket $ticket, User $user): CommunityComment
    {
        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'parent_id' => null,
            'user_id' => $user->id,
            'body' => 'Comentario visible '.uniqid(),
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);
    }

    private function hiddenComment(Ticket $ticket, User $user): CommunityComment
    {
        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'parent_id' => null,
            'user_id' => $user->id,
            'body' => 'Comentario oculto '.uniqid(),
            'status' => CommunityComment::STATUS_HIDDEN,
        ]);
    }

    private function deletedComment(Ticket $ticket, User $user): CommunityComment
    {
        return CommunityComment::create([
            'ticket_id' => $ticket->id,
            'parent_id' => null,
            'user_id' => $user->id,
            'body' => 'Comentario eliminado '.uniqid(),
            'status' => CommunityComment::STATUS_DELETED,
            'deleted_at' => now(),
        ]);
    }
}
