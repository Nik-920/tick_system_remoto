<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community Report Card v2 — priority rail colour suite.
 *
 * The left accent rail of .comm-post-v2 changes colour via the
 * comm-post-v2--priority-{tone} modifier class, derived from priority_tone.
 *
 * TicketBoardHelpers::priorityTone() collapses 4 DB priorities into 3 tones:
 *   critical → 'high'   (shares high's red rail)
 *   high     → 'high'
 *   medium   → 'medium'
 *   low      → 'low'
 */
class CommunityReportCardV2PriorityRailTest extends TestCase
{
    use RefreshDatabase;

    // ── Priority class rendering ──────────────────────────────────────────────

    public function test_low_priority_ticket_renders_priority_low_class(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Rail low RAIL-LOW', 'low');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2--priority-low', (string) $html);
    }

    public function test_medium_priority_ticket_renders_priority_medium_class(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Rail medium RAIL-MED', 'medium');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2--priority-medium', (string) $html);
    }

    public function test_high_priority_ticket_renders_priority_high_class(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Rail high RAIL-HIGH', 'high');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2--priority-high', (string) $html);
    }

    /**
     * DB priority 'critical' maps to tone 'high' via TicketBoardHelpers::priorityTone().
     * Both critical and high priority tickets therefore render the same red rail.
     */
    public function test_critical_priority_ticket_renders_priority_high_class(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Rail critical RAIL-CRIT', 'critical');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        // critical → high tone (see TicketBoardHelpers::priorityTone())
        $this->assertStringContainsString('comm-post-v2--priority-high', (string) $html);
        $this->assertStringNotContainsString('comm-post-v2--priority-critical', (string) $html);
    }

    public function test_three_distinct_rail_classes_across_four_priorities(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Rail all low RAIL-A-LOW', 'low');
        $this->makeVisibleTicket($reporter, 'Rail all med RAIL-A-MED', 'medium');
        $this->makeVisibleTicket($reporter, 'Rail all high RAIL-A-HIGH', 'high');
        // critical maps to high tone, so no 4th distinct rail class
        $this->makeVisibleTicket($reporter, 'Rail all crit RAIL-A-CRIT', 'critical');

        $html = (string) $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2--priority-low', $html);
        $this->assertStringContainsString('comm-post-v2--priority-medium', $html);
        $this->assertStringContainsString('comm-post-v2--priority-high', $html);
    }

    // ── Base class preserved ──────────────────────────────────────────────────

    public function test_base_comm_post_v2_class_is_still_present(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Rail base class RAIL-BASE', 'high');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="comm-post-v2', (string) $html);
    }

    public function test_state_tone_class_is_still_present_alongside_priority_class(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Rail state+priority RAIL-STATE', 'high');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2--open', (string) $html);
        $this->assertStringContainsString('comm-post-v2--priority-high', (string) $html);
    }

    // ── Priority badge preserved ──────────────────────────────────────────────

    public function test_priority_badge_still_renders_for_low(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Badge low RAIL-BDG-LOW', 'low');

        $html = (string) $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-badge--priority', $html);
        $this->assertStringContainsString('comm-badge--priority-low', $html);
    }

    /**
     * 'critical' DB priority maps to 'high' tone, so the badge class is
     * comm-badge--priority-high (not comm-badge--priority-critical).
     */
    public function test_priority_badge_for_critical_ticket_uses_high_tone(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Badge critical RAIL-BDG-CRIT', 'critical');

        $html = (string) $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-badge--priority-high', $html);
    }

    // ── Proxy / actions / no legacy ───────────────────────────────────────────

    public function test_media_proxy_not_broken_by_priority_rail(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Rail proxy RAIL-PROXY', 'high');

        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_path' => 'tickets/'.Str::uuid().'/rail-image.jpg',
            'file_url' => 'https://storage.example.com/raw-rail-image.jpg',
            'file_type' => 'image/jpeg',
            'file_name' => 'rail-image.jpg',
            'file_size' => 2048,
            'position' => 0,
        ]);

        $html = (string) $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('raw-rail-image.jpg', $html);
        $this->assertStringNotContainsString('storage.example.com', $html);
        $this->assertStringContainsString('community/media', $html);
    }

    public function test_social_action_data_attrs_preserved_with_priority_rail(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Rail social RAIL-SOCIAL', 'medium');

        $html = (string) $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-community-social-form', $html);
        $this->assertStringContainsString('data-community-action', $html);
        $this->assertStringContainsString('aria-pressed', $html);
    }

    public function test_no_legacy_v1_classes_with_priority_rail(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Rail no legacy RAIL-NOLEG', 'critical');

        $html = (string) $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('comm-post__thumb-placeholder', $html);
        $this->assertStringNotContainsString('comm-post__thumb-img', $html);
        $this->assertStringNotContainsString('comm-post__overlay-btn', $html);
    }

    // ── CSS source checks ─────────────────────────────────────────────────────

    public function test_css_uses_css_variable_for_rail_not_hardcoded_red(): void
    {
        $css = file_get_contents(
            base_path('resources/css/modules/reporter-community.css'),
        );

        $this->assertStringContainsString(
            'background: var(--comm-post-v2-rail',
            $css,
            'Rail must use CSS variable, not hardcoded #e5484d',
        );
    }

    public function test_css_defines_all_active_priority_rail_modifiers(): void
    {
        $css = file_get_contents(
            base_path('resources/css/modules/reporter-community.css'),
        );

        $this->assertStringContainsString('.comm-post-v2--priority-low', $css);
        $this->assertStringContainsString('.comm-post-v2--priority-medium', $css);
        $this->assertStringContainsString('.comm-post-v2--priority-high', $css);
    }

    public function test_blade_orchestrator_emits_priority_class(): void
    {
        $src = file_get_contents(
            resource_path('views/reporter/community/partials/post-card.blade.php'),
        );

        $this->assertStringContainsString(
            "comm-post-v2--priority-{{ \$post['priority_tone']",
            $src,
            'Orchestrator article must include priority modifier class',
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
    }

    private function makeReporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeVisibleTicket(
        User $reporter,
        string $title,
        string $priority = 'medium',
        ?Location $location = null,
        ?Category $category = null,
    ): Ticket {
        return Ticket::create([
            'title' => $title,
            'description' => 'Test de rail de prioridad para la card v2.',
            'reporter_id' => $reporter->id,
            'location_id' => ($location ?? $this->makeLocation())->id,
            'category_id' => ($category ?? $this->makeCategory())->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => $priority,
            'community_visible' => true,
        ]);
    }

    private function makeLocation(
        string $roomCode = '',
        string $building = 'Edificio Rail',
        string $floor = '1',
    ): Location {
        return Location::create([
            'name' => 'Sala RAIL '.Str::upper(Str::random(4)),
            'building' => $building,
            'floor' => $floor,
            'room_code' => $roomCode !== '' ? $roomCode : 'RAIL-'.Str::upper(Str::random(5)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(string $name = ''): Category
    {
        return Category::create([
            'name' => $name !== '' ? $name : 'CatRail-'.Str::lower(Str::random(5)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de rail de prioridad',
        ]);
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
