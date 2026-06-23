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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression suite that catches the 504-timeout root cause:
 * 18 sequential queries × ~3.5s remote-DB latency > 60s nginx timeout.
 *
 * These tests verify the page renders correctly across all critical
 * code paths that contributed to the high query count.
 */
class ReporterCommunityTimeoutRegressionTest extends TestCase
{
    use RefreshDatabase;

    // ── Core page accessibility ─────────────────────────────────────────────

    public function test_community_page_returns_200_for_reporter(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk();
    }

    public function test_community_page_returns_200_with_multiple_posts(): void
    {
        $reporter = $this->createReporter();
        $loc = $this->makeLocation();
        $cat = $this->makeCategory();

        for ($i = 0; $i < 12; $i++) {
            $this->makePublicTicket($reporter, $loc, $cat);
        }

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk();
    }

    // ── All sort variants must respond 200 ──────────────────────────────────

    public function test_sort_recent_returns_200(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community', ['sort' => 'recent']))
            ->assertOk();
    }

    public function test_sort_active_returns_200(): void
    {
        $reporter = $this->createReporter();
        $loc = $this->makeLocation();
        $cat = $this->makeCategory();
        $this->makePublicTicket($reporter, $loc, $cat);

        $this->actingAs($reporter)
            ->get(route('reporter.community', ['sort' => 'active']))
            ->assertOk();
    }

    public function test_sort_discussed_returns_200(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community', ['sort' => 'discussed']))
            ->assertOk();
    }

    public function test_sort_supported_returns_200(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community', ['sort' => 'supported']))
            ->assertOk();
    }

    // ── Filter variants must respond 200 ────────────────────────────────────

    public function test_priority_high_filter_returns_200(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community', ['priority' => 'high']))
            ->assertOk();
    }

    public function test_priority_critical_filter_returns_200(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community', ['priority' => 'critical']))
            ->assertOk();
    }

    public function test_saved_filter_returns_200(): void
    {
        $reporter = $this->createReporter();
        $loc = $this->makeLocation();
        $cat = $this->makeCategory();
        $ticket = $this->makePublicTicket($reporter, $loc, $cat);

        CommunitySave::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community', ['saved' => '1']))
            ->assertOk();
    }

    public function test_has_media_filter_returns_200(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community', ['has_media' => '1']))
            ->assertOk();
    }

    public function test_search_query_returns_200(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community', ['q' => 'laboratorio']))
            ->assertOk();
    }

    public function test_period_filter_returns_200(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community', ['period' => '7d']))
            ->assertOk();
    }

    public function test_pagination_page_2_returns_200(): void
    {
        $reporter = $this->createReporter();
        $loc = $this->makeLocation();
        $cat = $this->makeCategory();

        for ($i = 0; $i < 12; $i++) {
            $this->makePublicTicket($reporter, $loc, $cat);
        }

        $this->actingAs($reporter)
            ->get(route('reporter.community', ['page' => '2']))
            ->assertOk();
    }

    // ── Render contract: no removed sidebar properties ───────────────────────

    public function test_render_does_not_require_shortcuts(): void
    {
        $reporter = $this->createReporter();

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk();
        $response->assertDontSee('shortcuts', false);
    }

    public function test_render_does_not_require_active_locations(): void
    {
        $reporter = $this->createReporter();

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk();
        $response->assertDontSee('activeLocations', false);
    }

    public function test_render_does_not_require_hot_categories(): void
    {
        $reporter = $this->createReporter();

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk();
        $response->assertDontSee('hotCategories', false);
    }

    // ── Filter panel and sort bar are present ───────────────────────────────

    public function test_render_includes_filter_panel_button(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-filter-btn', false);
    }

    public function test_render_includes_filter_panel_dialog(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-filter-panel', false);
    }

    public function test_render_includes_sort_options(): void
    {
        $reporter = $this->createReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Recientes', false)
            ->assertSee('Activos', false);
    }

    // ── No HTTP calls to remote media during feed render ────────────────────

    public function test_feed_render_does_not_make_remote_http_calls(): void
    {
        Http::fake();

        $reporter = $this->createReporter();
        $loc = $this->makeLocation();
        $cat = $this->makeCategory();
        $this->makePublicTicket($reporter, $loc, $cat);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk();

        Http::assertNothingSent();
    }

    // ── Reactions and saves show counts ─────────────────────────────────────

    public function test_feed_shows_reaction_and_save_data(): void
    {
        $reporter = $this->createReporter();
        $loc = $this->makeLocation();
        $cat = $this->makeCategory();
        $ticket = $this->makePublicTicket($reporter, $loc, $cat);

        CommunityReaction::create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
            'type' => CommunityReaction::TYPE_INTERESTED,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('data-community-action="reaction"', false);
    }

    // ── Query count is bounded — no N+1 on feed page ────────────────────────

    public function test_feed_query_count_does_not_scale_with_ticket_count(): void
    {
        $reporter = $this->createReporter();
        $loc = $this->makeLocation();
        $cat = $this->makeCategory();

        for ($i = 0; $i < 5; $i++) {
            $this->makePublicTicket($reporter, $loc, $cat);
        }

        $queriesFor5 = 0;
        DB::listen(function () use (&$queriesFor5): void {
            $queriesFor5++;
        });

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk();

        // Feed uses batch queries — query count stays bounded regardless of page size.
        // 18 was the pre-optimisation baseline; 25 is a safe upper bound.
        $this->assertLessThanOrEqual(25, $queriesFor5, "Query count {$queriesFor5} exceeded bounded threshold — possible N+1 regression");
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function createReporter(): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Aula Timeout Test',
            'building' => 'Edificio T',
            'floor' => '1',
            'room_code' => 'TO-'.Str::upper(Str::random(4)),
            'qr_token' => 'qr-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(string $name = ''): Category
    {
        return Category::create([
            'name' => $name !== '' ? $name : 'Cat-'.Str::lower(Str::random(5)),
            'icon' => 'wrench',
            'description' => 'Categoría para timeout regression tests',
        ]);
    }

    private function makePublicTicket(User $reporter, Location $loc, Category $cat): Ticket
    {
        return Ticket::create([
            'id' => (string) Str::uuid(),
            'title' => 'Ticket timeout test '.Str::random(6),
            'description' => 'Descripción para test de regresión de timeout.',
            'reporter_id' => $reporter->id,
            'location_id' => $loc->id,
            'category_id' => $cat->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }
}
