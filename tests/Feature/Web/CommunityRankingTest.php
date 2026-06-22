<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunityComment;
use App\Models\CommunityReaction;
use App\Models\CommunityReport;
use App\Models\CommunitySave;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunityRankingTest extends TestCase
{
    use RefreshDatabase;

    // ── Sort=recent (default) ─────────────────────────────────────────────────

    public function test_default_sort_is_recent_by_updated_at(): void
    {
        $reporter = $this->makeReporter();

        $older = $this->makeTicket($reporter, 'Ticket antiguo RANKOLD');
        $older->updated_at = now()->subHours(5);
        $older->saveQuietly();

        $newer = $this->makeTicket($reporter, 'Ticket reciente RANKNEW');
        $newer->updated_at = now()->subMinutes(10);
        $newer->saveQuietly();

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $posOld = strpos((string) $html, 'Ticket antiguo RANKOLD');
        $posNew = strpos((string) $html, 'Ticket reciente RANKNEW');

        $this->assertNotFalse($posOld);
        $this->assertNotFalse($posNew);
        $this->assertLessThan($posOld, $posNew, 'Newer ticket should appear before older');
    }

    public function test_sort_recent_explicit_orders_by_updated_at_desc(): void
    {
        $reporter = $this->makeReporter();

        $older = $this->makeTicket($reporter, 'Ticket viejo SORTRECOLD');
        $older->updated_at = now()->subHours(3);
        $older->saveQuietly();

        $newer = $this->makeTicket($reporter, 'Ticket nuevo SORTRECNEW');
        $newer->updated_at = now()->subMinutes(5);
        $newer->saveQuietly();

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=recent')
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos((string) $html, 'Ticket viejo SORTRECOLD'),
            strpos((string) $html, 'Ticket nuevo SORTRECNEW'),
            'sort=recent: newer must appear first'
        );
    }

    public function test_invalid_sort_falls_back_to_recent(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicket($reporter, 'Ticket fallback sort INVXYZ');

        $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=bogus_invalid')
            ->assertOk()
            ->assertSee('Ticket fallback sort INVXYZ', false);
    }

    // ── Sort=discussed ────────────────────────────────────────────────────────

    public function test_sort_discussed_prioritises_tickets_with_more_visible_comments(): void
    {
        $reporter = $this->makeReporter();
        $commenter = $this->makeReporter();

        $popular = $this->makeTicket($reporter, 'Ticket muy comentado DISCPOP');
        $this->makeTicket($reporter, 'Ticket sin comentarios DISCQUIET');

        // 3 visible comments on popular ticket
        foreach (range(1, 3) as $i) {
            CommunityComment::create([
                'ticket_id' => $popular->id,
                'user_id' => $commenter->id,
                'body' => "Comentario {$i} en ticket popular",
                'status' => CommunityComment::STATUS_VISIBLE,
            ]);
        }

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=discussed')
            ->assertOk()
            ->getContent();

        $posPop = strpos((string) $html, 'Ticket muy comentado DISCPOP');
        $posQuiet = strpos((string) $html, 'Ticket sin comentarios DISCQUIET');

        $this->assertNotFalse($posPop);
        $this->assertNotFalse($posQuiet);
        $this->assertLessThan($posQuiet, $posPop, 'sort=discussed: popular ticket must appear first');
    }

    public function test_sort_discussed_hidden_comments_do_not_boost_ranking(): void
    {
        $reporter = $this->makeReporter();
        $commenter = $this->makeReporter();

        $withHidden = $this->makeTicket($reporter, 'Ticket con comentarios ocultos DISCHID');
        $withVisible = $this->makeTicket($reporter, 'Ticket con comentario visible DISCVIS');

        // 5 hidden comments on first ticket
        foreach (range(1, 5) as $i) {
            CommunityComment::create([
                'ticket_id' => $withHidden->id,
                'user_id' => $commenter->id,
                'body' => "Oculto {$i}",
                'status' => CommunityComment::STATUS_HIDDEN,
            ]);
        }

        // 1 visible comment on second ticket
        CommunityComment::create([
            'ticket_id' => $withVisible->id,
            'user_id' => $commenter->id,
            'body' => 'Comentario visible unico',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=discussed')
            ->assertOk()
            ->getContent();

        $posVisible = strpos((string) $html, 'Ticket con comentario visible DISCVIS');
        $posHidden = strpos((string) $html, 'Ticket con comentarios ocultos DISCHID');

        $this->assertNotFalse($posVisible);
        $this->assertNotFalse($posHidden);
        $this->assertLessThan($posHidden, $posVisible, 'Ticket with visible comments should rank higher than one with only hidden comments');
    }

    public function test_sort_discussed_replies_count_as_visible_comments(): void
    {
        $reporter = $this->makeReporter();
        $commenter = $this->makeReporter();

        $withReplies = $this->makeTicket($reporter, 'Ticket con replies DISCREPLY');
        $this->makeTicket($reporter, 'Ticket sin replies DISCNOREPLY');

        $root = CommunityComment::create([
            'ticket_id' => $withReplies->id,
            'user_id' => $commenter->id,
            'body' => 'Comentario raiz',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        // 2 visible replies
        foreach (range(1, 2) as $i) {
            CommunityComment::create([
                'ticket_id' => $withReplies->id,
                'parent_id' => $root->id,
                'user_id' => $commenter->id,
                'body' => "Reply {$i}",
                'status' => CommunityComment::STATUS_VISIBLE,
            ]);
        }

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=discussed')
            ->assertOk()
            ->getContent();

        $posReplies = strpos((string) $html, 'Ticket con replies DISCREPLY');
        $posNone = strpos((string) $html, 'Ticket sin replies DISCNOREPLY');

        $this->assertNotFalse($posReplies);
        $this->assertLessThan($posNone, $posReplies, 'Ticket with 3 visible items (root+replies) must rank higher than ticket with 0');
    }

    // ── Sort=supported ────────────────────────────────────────────────────────

    public function test_sort_supported_prioritises_tickets_with_more_reactions(): void
    {
        $reporter = $this->makeReporter();
        $reactor1 = $this->makeReporter();
        $reactor2 = $this->makeReporter();

        $popular = $this->makeTicket($reporter, 'Ticket muy apoyado SUPPOP');
        $this->makeTicket($reporter, 'Ticket poco apoyado SUPQUIET');

        // 2 reactions (interested + also_happens) on popular
        CommunityReaction::create(['ticket_id' => $popular->id, 'user_id' => $reactor1->id, 'type' => CommunityReaction::TYPE_INTERESTED]);
        CommunityReaction::create(['ticket_id' => $popular->id, 'user_id' => $reactor2->id, 'type' => CommunityReaction::TYPE_ALSO_HAPPENS]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=supported')
            ->assertOk()
            ->getContent();

        $posPop = strpos((string) $html, 'Ticket muy apoyado SUPPOP');
        $posQuiet = strpos((string) $html, 'Ticket poco apoyado SUPQUIET');

        $this->assertNotFalse($posPop);
        $this->assertNotFalse($posQuiet);
        $this->assertLessThan($posQuiet, $posPop, 'sort=supported: ticket with more reactions must appear first');
    }

    public function test_sort_supported_includes_saves_in_ranking(): void
    {
        $reporter = $this->makeReporter();
        $saver1 = $this->makeReporter();
        $saver2 = $this->makeReporter();

        $saved = $this->makeTicket($reporter, 'Ticket guardado mucho SUPSAVE');
        $unsaved = $this->makeTicket($reporter, 'Ticket no guardado SUPNOSAVE');

        CommunitySave::create(['ticket_id' => $saved->id, 'user_id' => $saver1->id]);
        CommunitySave::create(['ticket_id' => $saved->id, 'user_id' => $saver2->id]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=supported')
            ->assertOk()
            ->getContent();

        $posSaved = strpos((string) $html, 'Ticket guardado mucho SUPSAVE');
        $posUnsaved = strpos((string) $html, 'Ticket no guardado SUPNOSAVE');

        $this->assertNotFalse($posSaved);
        $this->assertLessThan($posUnsaved, $posSaved, 'sort=supported: saved tickets must rank higher');
    }

    public function test_sort_supported_seen_reaction_has_lower_weight_than_interested(): void
    {
        $reporter = $this->makeReporter();
        $u1 = $this->makeReporter();
        $u2 = $this->makeReporter();
        $u3 = $this->makeReporter();

        $interested = $this->makeTicket($reporter, 'Ticket con interested SUPINT');
        $seenOnly = $this->makeTicket($reporter, 'Ticket solo seen SUPSEEN');

        // interested ticket has 1 "interested" reaction
        CommunityReaction::create(['ticket_id' => $interested->id, 'user_id' => $u1->id, 'type' => CommunityReaction::TYPE_INTERESTED]);

        // seen-only ticket has 2 "seen" reactions — but seen is excluded from supported score
        CommunityReaction::create(['ticket_id' => $seenOnly->id, 'user_id' => $u2->id, 'type' => CommunityReaction::TYPE_SEEN]);
        CommunityReaction::create(['ticket_id' => $seenOnly->id, 'user_id' => $u3->id, 'type' => CommunityReaction::TYPE_SEEN]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=supported')
            ->assertOk()
            ->getContent();

        $posInterested = strpos((string) $html, 'Ticket con interested SUPINT');
        $posSeen = strpos((string) $html, 'Ticket solo seen SUPSEEN');

        $this->assertNotFalse($posInterested);
        $this->assertNotFalse($posSeen);
        // supported = interested + also_happens + saves only; seen is not counted
        $this->assertLessThan($posSeen, $posInterested, 'Ticket with 1 interested should rank higher than ticket with 2 seen-only');
    }

    // ── Sort=active ───────────────────────────────────────────────────────────

    public function test_sort_active_combines_reactions_saves_and_comments(): void
    {
        $reporter = $this->makeReporter();
        $u1 = $this->makeReporter();
        $u2 = $this->makeReporter();

        $active = $this->makeTicket($reporter, 'Ticket activo ACTPOP');
        $this->makeTicket($reporter, 'Ticket tranquilo ACTQUIET');

        // active ticket: 1 interested + 1 comment
        CommunityReaction::create(['ticket_id' => $active->id, 'user_id' => $u1->id, 'type' => CommunityReaction::TYPE_INTERESTED]);
        CommunityComment::create([
            'ticket_id' => $active->id,
            'user_id' => $u2->id,
            'body' => 'Comentario en ticket activo',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=active')
            ->assertOk()
            ->getContent();

        $posActive = strpos((string) $html, 'Ticket activo ACTPOP');
        $posQuiet = strpos((string) $html, 'Ticket tranquilo ACTQUIET');

        $this->assertNotFalse($posActive);
        $this->assertNotFalse($posQuiet);
        $this->assertLessThan($posQuiet, $posActive, 'sort=active: ticket with reactions+comments must appear first');
    }

    public function test_sort_active_pending_reports_penalise_ticket(): void
    {
        $reporter = $this->makeReporter();
        $u1 = $this->makeReporter();
        $u2 = $this->makeReporter();

        // Ticket A: 1 visible comment (score +5)
        $ticketA = $this->makeTicket($reporter, 'Ticket solo comentado ACTCMNT');
        CommunityComment::create([
            'ticket_id' => $ticketA->id,
            'user_id' => $u1->id,
            'body' => 'Comentario unico',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        // Ticket B: 3 pending reports (score -12) and no other engagement
        $ticketB = $this->makeTicket($reporter, 'Ticket penalizado ACTPENALISE');
        foreach ([$u1, $u2, $reporter] as $reporter_user) {
            CommunityReport::create([
                'ticket_id' => $ticketB->id,
                'reported_by' => $reporter_user->id,
                'reason' => 'spam',
                'status' => CommunityReport::STATUS_PENDING,
            ]);
        }

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=active')
            ->assertOk()
            ->getContent();

        $posA = strpos((string) $html, 'Ticket solo comentado ACTCMNT');
        $posB = strpos((string) $html, 'Ticket penalizado ACTPENALISE');

        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        $this->assertLessThan($posB, $posA, 'sort=active: ticket with comment (+5) must rank above ticket with 3 pending reports (-12)');
    }

    // ── Sort with saved=1 filter ──────────────────────────────────────────────

    public function test_sort_works_with_saved_filter(): void
    {
        $reporter = $this->makeReporter();
        $saver = $this->makeReporter();
        $commenter = $this->makeReporter();

        $savedA = $this->makeTicket($reporter, 'Guardado con comentarios SAVEDA');
        $savedB = $this->makeTicket($reporter, 'Guardado sin comentarios SAVEDB');

        CommunitySave::create(['ticket_id' => $savedA->id, 'user_id' => $saver->id]);
        CommunitySave::create(['ticket_id' => $savedB->id, 'user_id' => $saver->id]);

        CommunityComment::create([
            'ticket_id' => $savedA->id,
            'user_id' => $commenter->id,
            'body' => 'Comentario en guardado A',
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        // Unsaved ticket — should not appear in saved=1 feed regardless of engagement
        $this->makeTicket($reporter, 'No guardado SAVEDNONE');

        $html = $this->actingAs($saver)
            ->get(route('reporter.community').'?saved=1&sort=discussed')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Guardado con comentarios SAVEDA', (string) $html);
        $this->assertStringContainsString('Guardado sin comentarios SAVEDB', (string) $html);
        $this->assertStringNotContainsString('No guardado SAVEDNONE', (string) $html);

        $posA = strpos((string) $html, 'Guardado con comentarios SAVEDA');
        $posB = strpos((string) $html, 'Guardado sin comentarios SAVEDB');
        $this->assertLessThan($posB, $posA, 'Saved ticket with comments must rank first in discussed sort within saved filter');
    }

    public function test_sort_works_with_category_filter(): void
    {
        $reporter = $this->makeReporter();
        $loc = $this->makeLocation();
        $catA = Category::create(['name' => 'CatAlpha-'.Str::random(4), 'icon' => 'tag', 'description' => 'A']);
        $catB = Category::create(['name' => 'CatBeta-'.Str::random(4), 'icon' => 'tag', 'description' => 'B']);

        Ticket::create([
            'title' => 'Ticket categoria A RANKCAT',
            'description' => 'En categoria A',
            'reporter_id' => $reporter->id,
            'location_id' => $loc->id,
            'category_id' => $catA->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);

        Ticket::create([
            'title' => 'Ticket categoria B RANKCAT',
            'description' => 'En categoria B',
            'reporter_id' => $reporter->id,
            'location_id' => $loc->id,
            'category_id' => $catB->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community').'?category='.$catA->id.'&sort=active')
            ->assertOk();

        $response->assertSee('Ticket categoria A RANKCAT', false);
        $response->assertDontSee('Ticket categoria B RANKCAT', false);
    }

    public function test_sort_works_with_state_filter(): void
    {
        $reporter = $this->makeReporter();

        $this->makeTicket($reporter, 'Ticket abierto para sort STATESORT');
        $ticketResolved = $this->makeTicket($reporter, 'Ticket resuelto para sort STATESORT');
        $ticketResolved->update(['state' => Ticket::STATE_RESOLVED]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community').'?state=resolved&sort=supported')
            ->assertOk();

        $response->assertSee('Ticket resuelto para sort STATESORT', false);
        $response->assertDontSee('Ticket abierto para sort STATESORT', false);
    }

    // ── UI ────────────────────────────────────────────────────────────────────

    public function test_feed_shows_sort_controls(): void
    {
        $reporter = $this->makeReporter();

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        // Sort controls now live inside the advanced filter panel (modal), not as a visible bar
        $this->assertStringContainsString('name="sort"', (string) $html);
        $this->assertStringContainsString('Recientes', (string) $html);
        $this->assertStringContainsString('Activos', (string) $html);
        $this->assertStringContainsString('Comentados', (string) $html);
        $this->assertStringContainsString('Apoyados', (string) $html);
    }

    public function test_default_sort_chip_is_marked_active(): void
    {
        $reporter = $this->makeReporter();

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        // Sort radios in the filter panel: default (recent) radio is checked and label has comm-fopt--on
        $activePattern = '/comm-fopt--on[^>]*>\s*<input[^>]*name="sort"[^>]*value="recent"/';
        $this->assertMatchesRegularExpression($activePattern, (string) $html, '"Recientes" sort option must be active by default');
    }

    public function test_active_sort_chip_is_marked_when_sort_active(): void
    {
        $reporter = $this->makeReporter();

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=active')
            ->assertOk()
            ->getContent();

        // Sort radio for "active" must be checked and its label must have comm-fopt--on
        $activePattern = '/comm-fopt--on[^>]*>\s*<input[^>]*name="sort"[^>]*value="active"/';
        $this->assertMatchesRegularExpression($activePattern, (string) $html, '"Activos" sort option must be active when sort=active');
    }

    public function test_sort_links_are_present_as_get_links(): void
    {
        $reporter = $this->makeReporter();

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        // Sort options are now radio inputs inside the filter panel form
        $this->assertStringContainsString('value="active"', (string) $html);
        $this->assertStringContainsString('value="discussed"', (string) $html);
        $this->assertStringContainsString('value="supported"', (string) $html);
    }

    public function test_feed_does_not_show_raw_score(): void
    {
        $reporter = $this->makeReporter();
        $u1 = $this->makeReporter();
        $u2 = $this->makeReporter();

        $ticket = $this->makeTicket($reporter, 'Ticket con score oculto NOSCORE');
        CommunityReaction::create(['ticket_id' => $ticket->id, 'user_id' => $u1->id, 'type' => CommunityReaction::TYPE_INTERESTED]);
        CommunitySave::create(['ticket_id' => $ticket->id, 'user_id' => $u2->id]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=active')
            ->assertOk()
            ->getContent();

        // Must not show a "score" UI element — check CSS class names we'd add if we did
        $this->assertStringNotContainsString('comm-score', (string) $html);
        $this->assertStringNotContainsString('Puntaje:', (string) $html);
        $this->assertStringNotContainsString('Puntuación:', (string) $html);
        $this->assertStringNotContainsString('Score IA:', (string) $html);
    }

    // ── Security / no-leak ────────────────────────────────────────────────────

    public function test_ranking_ui_does_not_expose_reporter_pii(): void
    {
        $reporter = $this->makeReporter();
        $reporter->update(['name' => 'NombreSecreto', 'last_name' => 'ApellidoSecreto', 'email' => 'secretrank@test.test']);

        $viewer = $this->makeReporter();
        $u1 = $this->makeReporter();

        $ticket = $this->makeTicket($reporter, 'Ticket con PII oculta RANKPII');
        CommunityReaction::create(['ticket_id' => $ticket->id, 'user_id' => $u1->id, 'type' => CommunityReaction::TYPE_INTERESTED]);

        $response = $this->actingAs($viewer)
            ->get(route('reporter.community').'?sort=active');

        $response->assertOk();
        $response->assertDontSee('NombreSecreto', false);
        $response->assertDontSee('ApellidoSecreto', false);
        $response->assertDontSee('secretrank@test.test', false);
    }

    public function test_ranking_ui_does_not_expose_report_reasons(): void
    {
        $reporter = $this->makeReporter();
        $u1 = $this->makeReporter();

        $ticket = $this->makeTicket($reporter, 'Ticket reportado con reason RANKRPT');
        CommunityReport::create([
            'ticket_id' => $ticket->id,
            'reported_by' => $u1->id,
            'reason' => 'inappropriate_content_very_secret',
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=active');

        $response->assertOk();
        $response->assertDontSee('inappropriate_content_very_secret', false);
    }

    public function test_hidden_community_ticket_excluded_regardless_of_sort(): void
    {
        $reporter = $this->makeReporter();
        $u1 = $this->makeReporter();

        $hiddenTicket = $this->makeTicket($reporter, 'Ticket oculto por moderacion RANKMOD');
        $hiddenTicket->forceFill([
            'community_visible' => false,
            'community_hidden_at' => now(),
            'community_hidden_by' => $u1->id,
        ])->save();

        // Add engagement so it would rank high if visibility was not enforced
        CommunityReaction::create(['ticket_id' => $hiddenTicket->id, 'user_id' => $u1->id, 'type' => CommunityReaction::TYPE_ALSO_HAPPENS]);

        foreach (['recent', 'active', 'discussed', 'supported'] as $sort) {
            $this->actingAs($reporter)
                ->get(route('reporter.community').'?sort='.$sort)
                ->assertOk()
                ->assertDontSee('Ticket oculto por moderacion RANKMOD', false);
        }
    }

    public function test_cancelled_ticket_excluded_regardless_of_sort(): void
    {
        $reporter = $this->makeReporter();

        $cancelled = $this->makeTicket($reporter, 'Ticket cancelado ranking RANKCANCELL');
        $cancelled->update(['state' => Ticket::STATE_CANCELLED]);

        foreach (['recent', 'active', 'discussed', 'supported'] as $sort) {
            $this->actingAs($reporter)
                ->get(route('reporter.community').'?sort='.$sort)
                ->assertOk()
                ->assertDontSee('Ticket cancelado ranking RANKCANCELL', false);
        }
    }

    public function test_rejected_ticket_excluded_regardless_of_sort(): void
    {
        $reporter = $this->makeReporter();

        $rejected = $this->makeTicket($reporter, 'Ticket rechazado ranking RANKREJ');
        $rejected->update(['state' => Ticket::STATE_REJECTED]);

        foreach (['recent', 'active', 'discussed', 'supported'] as $sort) {
            $this->actingAs($reporter)
                ->get(route('reporter.community').'?sort='.$sort)
                ->assertOk()
                ->assertDontSee('Ticket rechazado ranking RANKREJ', false);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeReporter(): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Aula Ranking Test',
            'building' => 'Edificio Rank',
            'floor' => '1',
            'room_code' => 'RNK-'.Str::upper(Str::random(4)),
            'qr_token' => 'qr-rank-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-Rank-'.Str::lower(Str::random(5)),
            'icon' => 'tag',
            'description' => 'Categoría para tests de ranking',
        ]);
    }

    private function makeTicket(User $reporter, string $title): Ticket
    {
        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de prueba para ranking: '.$title,
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }
}
