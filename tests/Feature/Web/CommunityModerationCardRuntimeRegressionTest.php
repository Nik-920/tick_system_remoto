<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\CommunityReport;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Community\CommunityModerationQueueQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Runtime regression suite for the community moderation card view.
 *
 * Verifies:
 *   1. No 500 / Undefined array key errors in any render path.
 *   2. Item shape contract: all required keys present in every ViewModel item.
 *   3. Context block render/hide logic.
 *   4. Hide / restore / report-review form wiring.
 *   5. Filters, access control.
 */
class CommunityModerationCardRuntimeRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ── 1. Runtime / 500 prevention ──────────────────────────────────────────

    public function test_page_returns_200_without_tickets(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk();
    }

    public function test_page_returns_200_with_visible_ticket_and_no_context(): void
    {
        $this->makeVisibleTicket();

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk();
    }

    public function test_each_item_in_viewmodel_has_has_context_key(): void
    {
        $this->makeVisibleTicket(title: 'Shape test RR03');
        $admin = $this->makeAdmin();

        /** @var CommunityModerationQueueQuery $query */
        $query = $this->app->make(CommunityModerationQueueQuery::class);
        $vm = $query->forAdmin($admin, []);

        $this->assertNotEmpty($vm->items);
        foreach ($vm->items as $item) {
            $this->assertArrayHasKey('has_context', $item, 'Item is missing has_context key');
        }
    }

    public function test_each_item_in_viewmodel_has_all_required_contract_keys(): void
    {
        $this->makeVisibleTicket(title: 'Contract test RR04');
        $admin = $this->makeAdmin();

        /** @var CommunityModerationQueueQuery $query */
        $query = $this->app->make(CommunityModerationQueueQuery::class);
        $vm = $query->forAdmin($admin, []);

        $required = [
            'id', 'ref', 'title', 'state', 'state_label', 'state_tone',
            'community_visible', 'community_badge_label', 'state_blocks_feed',
            'has_context', 'community_visibility_reason', 'hidden_by_name',
            'community_hidden_at', 'pending_reports_count', 'latest_pending_report',
            'show_url', 'hide_url', 'restore_url', 'review_report_url',
            'created_at', 'created_at_label',
        ];

        foreach ($vm->items as $item) {
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $item, "Item is missing required key: {$key}");
            }
        }
    }

    public function test_page_does_not_render_undefined_array_key_error(): void
    {
        $this->makeVisibleTicket(title: 'Error-free RR05a');
        $this->makeHiddenTicket(title: 'Error-free RR05b');

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk();

        $this->assertStringNotContainsString('Undefined array key', (string) $response->getContent());
    }

    // ── 2. Cards without context ──────────────────────────────────────────────

    public function test_visible_ticket_without_reports_omits_context_block(): void
    {
        $this->makeVisibleTicket(title: 'No context RR06');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertDontSee('adm-comm-card__context', false);
    }

    public function test_no_em_dash_used_as_empty_placeholder(): void
    {
        $this->makeVisibleTicket(title: 'No dash RR07');

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk();

        $this->assertStringNotContainsString('>—<', (string) $response->getContent());
    }

    public function test_visible_ticket_shows_ver_ficha_and_ocultar_in_footer(): void
    {
        $this->makeVisibleTicket(title: 'Footer actions RR08');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Ver ficha', false)
            ->assertSee('Ocultar', false);
    }

    // ── 3. Hidden context block ───────────────────────────────────────────────

    public function test_hidden_ticket_shows_reason_in_context_block(): void
    {
        $this->makeHiddenTicket(
            title: 'Con motivo RR09',
            reason: 'Violación de normas RR09R',
        );

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee('Violación de normas RR09R', false);
    }

    public function test_hidden_ticket_shows_moderator_name_in_context_block(): void
    {
        $moderator = $this->makeAdmin();
        $this->makeHiddenTicket(
            title: 'Con moderador RR10',
            reason: 'Razón RR10',
            hiddenBy: $moderator,
        );

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee($moderator->name, false);
    }

    public function test_hidden_ticket_shows_restaurar_action_in_footer(): void
    {
        $this->makeHiddenTicket(title: 'Restaurar RR11');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee('Restaurar', false);
    }

    public function test_restaurar_form_contains_csrf_token_and_patch_method(): void
    {
        $ticket = $this->makeHiddenTicket(title: 'CSRF restore RR12');

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk();

        $response->assertSee(route('tickets.community.restore', $ticket), false);
        $response->assertSee('_token', false);
        $response->assertSee('_method', false);
    }

    // ── 4. Report context block ───────────────────────────────────────────────

    public function test_ticket_with_pending_reports_shows_pendiente_count(): void
    {
        $ticket = $this->makeVisibleTicket(title: 'Reports count RR13');
        $this->makePendingReport($ticket);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('pendiente', false);
    }

    public function test_ticket_with_comment_report_shows_comentario_target_label(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket(title: 'Comment report RR14');

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'body' => 'Comentario de prueba RR14',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_INAPPROPRIATE_EVIDENCE,
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Comentario', false);
    }

    public function test_ticket_with_pending_report_shows_resolver_reporte_action(): void
    {
        $ticket = $this->makeVisibleTicket(title: 'Resolver accion RR15');
        $this->makePendingReport($ticket);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('Resolver reporte', false);
    }

    public function test_resolver_reporte_form_contains_csrf_and_patch_method(): void
    {
        $ticket = $this->makeVisibleTicket(title: 'Review form RR16');
        $report = $this->makePendingReport($ticket);

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk();

        $response->assertSee(route('admin.community.reports.review', $report), false);
        $response->assertSee('_token', false);
        $response->assertSee('_method', false);
    }

    // ── 5. Hide / Ocultar form ────────────────────────────────────────────────

    public function test_ocultar_form_contains_reason_text_input(): void
    {
        $this->makeVisibleTicket(title: 'Reason input RR17');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee('name="reason"', false);
    }

    public function test_ocultar_form_reason_input_has_required_attribute(): void
    {
        $this->makeVisibleTicket(title: 'Reason required RR18');

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk();

        $this->assertStringContainsString('name="reason"', (string) $response->getContent());
        $this->assertStringContainsString('required', (string) $response->getContent());
    }

    public function test_ocultar_form_action_uses_tickets_community_hide_route(): void
    {
        $ticket = $this->makeVisibleTicket(title: 'Hide route RR19');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk()
            ->assertSee(route('tickets.community.hide', $ticket), false);
    }

    // ── 6. Filters / pagination ────────────────────────────────────────────────

    public function test_visibility_hidden_filter_returns_200_with_cards(): void
    {
        $this->makeHiddenTicket(title: 'Hidden filter RR20');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation', ['visibility' => 'hidden']))
            ->assertOk()
            ->assertSee('Hidden filter RR20', false);
    }

    public function test_state_open_filter_returns_200(): void
    {
        $this->makeVisibleTicket(title: 'State open RR21', state: Ticket::STATE_OPEN);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation', ['state' => 'open']))
            ->assertOk()
            ->assertSee('State open RR21', false);
    }

    public function test_search_filter_returns_200_and_filters_results(): void
    {
        $this->makeVisibleTicket(title: 'Búsqueda exacta RR22X');
        $this->makeVisibleTicket(title: 'Otro ticket no match RR22Y');

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation', ['q' => 'Búsqueda exacta']))
            ->assertOk()
            ->assertSee('RR22X', false)
            ->assertDontSee('RR22Y', false);
    }

    public function test_combined_filters_return_200(): void
    {
        $this->makeVisibleTicket(title: 'Combined filters RR23', state: Ticket::STATE_OPEN);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation', [
                'visibility' => 'visible',
                'state' => 'open',
                'q' => 'Combined',
            ]))
            ->assertOk()
            ->assertSee('Combined filters RR23', false);
    }

    // ── 7. Access control ─────────────────────────────────────────────────────

    public function test_reporter_cannot_access_moderation_queue(): void
    {
        $this->actingAs($this->makeReporter())
            ->get(route('admin.community.moderation'))
            ->assertForbidden();
    }

    public function test_maintenance_user_cannot_access_moderation_queue(): void
    {
        $this->actingAs($this->makeUserWithRole('maintenance'))
            ->get(route('admin.community.moderation'))
            ->assertForbidden();
    }

    public function test_admin_can_access_moderation_queue(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.community.moderation'))
            ->assertOk();
    }

    public function test_super_admin_can_access_moderation_queue(): void
    {
        $this->actingAs($this->makeUserWithRole('super_admin'))
            ->get(route('admin.community.moderation'))
            ->assertOk();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        return $this->makeUserWithRole('admin');
    }

    private function makeReporter(): User
    {
        return $this->makeUserWithRole('reporter');
    }

    private function makeUserWithRole(string $role): User
    {
        $this->ensureRoles();
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ensureRoles(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $r) {
            Role::findOrCreate($r, 'web');
        }
    }

    private function makeVisibleTicket(
        string $title = 'Ticket visible RR',
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        $reporter = $this->makeReporter();

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción runtime regression.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeHiddenTicket(
        string $title = 'Ticket oculto RR',
        string $reason = 'Motivo RR',
        ?User $hiddenBy = null,
    ): Ticket {
        $reporter = $this->makeReporter();
        $moderator = $hiddenBy ?? $this->makeAdmin();

        $ticket = Ticket::create([
            'title' => $title,
            'description' => 'Descripción oculto runtime regression.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
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

    private function makePendingReport(Ticket $ticket, ?User $reportedBy = null): CommunityReport
    {
        $reporter = $reportedBy ?? $this->makeReporter();

        return CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $reporter->id,
            'reason' => CommunityReport::REASON_SENSITIVE_INFO,
            'status' => CommunityReport::STATUS_PENDING,
        ]);
    }

    private function makeLocation(string $building = 'Edificio RR', string $roomCode = ''): Location
    {
        return Location::create([
            'name' => 'Sala RR-'.Str::upper(Str::random(4)),
            'building' => $building,
            'floor' => '1',
            'room_code' => $roomCode !== '' ? $roomCode : 'RR-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-rr-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(string $name = ''): Category
    {
        return Category::create([
            'name' => $name !== '' ? $name : 'Cat-RR-'.Str::lower(Str::random(5)),
            'icon' => 'wrench',
            'description' => 'Categoría runtime regression',
        ]);
    }
}
