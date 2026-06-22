<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunitySave;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Queries\Community\CommunityFeedQuery;
use App\ViewModels\Community\CommunityFeedViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifies that sidebar/tabs/sort-bar data is no longer computed or rendered
 * after the community sidebar query cleanup:
 * - shortcuts(), activeLocations(), hotCategories() removed from CommunityFeedQuery.
 * - CommunityFeedViewModel no longer carries those properties.
 * - Orphaned partials (tabs, sidebar) deleted.
 * - Dead CSS classes absent from rendered output.
 * - Hero, feed, modal, and all query params remain functional.
 */
class CommunitySidebarQueryCleanupTest extends TestCase
{
    use RefreshDatabase;

    // ── No sidebar/tabs rendered ──────────────────────────────────────────────

    public function test_sidebar_atajos_not_rendered(): void
    {
        $this->assertStringNotContainsString(
            'Atajos',
            $this->communityHtml()
        );
    }

    public function test_sidebar_zonas_con_actividad_not_rendered(): void
    {
        $this->assertStringNotContainsString(
            'Zonas con actividad',
            $this->communityHtml()
        );
    }

    public function test_sidebar_categorias_frecuentes_not_rendered(): void
    {
        $this->assertStringNotContainsString(
            'Categorías frecuentes',
            $this->communityHtml()
        );
    }

    public function test_sidebar_tus_guardados_card_not_rendered(): void
    {
        $html = $this->communityHtml();

        $this->assertStringNotContainsString('Tus guardados', $html);
        $this->assertStringNotContainsString('comm-saves-link', $html);
    }

    public function test_comm_sidebar_class_not_rendered(): void
    {
        $this->assertStringNotContainsString(
            'class="comm-sidebar"',
            $this->communityHtml()
        );
    }

    public function test_tabs_strip_not_rendered(): void
    {
        $html = $this->communityHtml();

        $this->assertStringNotContainsString('comm-tabs', $html);
        $this->assertStringNotContainsString('comm-tab__label-text', $html);
        $this->assertStringNotContainsString('comm-tab__badge-real', $html);
    }

    public function test_para_ti_not_rendered(): void
    {
        $this->assertStringNotContainsString(
            '>Para ti<',
            $this->communityHtml()
        );
    }

    public function test_sort_bar_not_rendered(): void
    {
        $html = $this->communityHtml();

        $this->assertStringNotContainsString('comm-sort-bar', $html);
        $this->assertStringNotContainsString('comm-sort-chip', $html);
    }

    // ── Hero and feed still work ──────────────────────────────────────────────

    public function test_hero_shows_quick_summary(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket activo SQHERO');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        // Hero stat cards are still present
        $this->assertStringContainsString('comm-hero__stat-value', (string) $html);
        $this->assertStringContainsString('comm-hero__stat-label', (string) $html);
    }

    public function test_feed_shows_visible_posts(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket visible SQFEED');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Ticket visible SQFEED', false);
    }

    // ── Filter modal still works ──────────────────────────────────────────────

    public function test_filter_modal_shows_ordenar_por(): void
    {
        $this->assertStringContainsString(
            'Ordenar por',
            $this->communityHtml()
        );
    }

    public function test_filter_modal_contains_existing_filters(): void
    {
        $html = $this->communityHtml();

        $this->assertStringContainsString('name="state"', $html);
        $this->assertStringContainsString('name="period"', $html);
        $this->assertStringContainsString('name="has_media"', $html);
        $this->assertStringContainsString('name="priority"', $html);
        $this->assertStringContainsString('name="saved"', $html);
    }

    // ── Query params still work ───────────────────────────────────────────────

    public function test_sort_active_param_works(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket sort SQSORT');

        $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=active')
            ->assertOk()
            ->assertSee('Ticket sort SQSORT', false);
    }

    public function test_saved_filter_param_works(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket guardado SQSAVE');
        $other = $this->makeVisibleTicket($reporter, 'No guardado SQNOSAVE');

        CommunitySave::create([
            'ticket_id' => $ticket->id,
            'user_id' => $reporter->id,
        ]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?saved=1')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ticket guardado SQSAVE', (string) $html);
        $this->assertStringNotContainsString('No guardado SQNOSAVE', (string) $html);
    }

    public function test_priority_high_param_works(): void
    {
        $reporter = $this->makeReporter();
        $high = $this->makeVisibleTicket($reporter, 'Ticket alta SQPRI', 'high');
        $low = $this->makeVisibleTicket($reporter, 'Ticket baja SQPRILOW', 'low');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?priority=high')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ticket alta SQPRI', (string) $html);
        $this->assertStringNotContainsString('Ticket baja SQPRILOW', (string) $html);
    }

    public function test_pagination_preserves_filters(): void
    {
        $reporter = $this->makeReporter();

        $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=discussed&state=open&page=1')
            ->assertOk();
    }

    // ── No debug output ───────────────────────────────────────────────────────

    public function test_no_raw_file_url_exposed(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket media SQMEDIA');
        $ticket->media()->create([
            'file_url' => 'https://raw-storage.example.com/secret-sq.jpg',
            'file_type' => 'image/jpeg',
        ]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('secret-sq.jpg', (string) $html);
        $this->assertStringContainsString('community/media', (string) $html);
    }

    public function test_no_console_log_debug(): void
    {
        $this->assertStringNotContainsString(
            'console.log',
            $this->communityHtml()
        );
    }

    // ── ViewModel shape ───────────────────────────────────────────────────────

    public function test_viewmodel_has_no_shortcuts_property(): void
    {
        $vm = $this->resolveViewModel();

        $this->assertFalse(
            property_exists($vm, 'shortcuts'),
            'CommunityFeedViewModel must not expose shortcuts after cleanup'
        );
    }

    public function test_viewmodel_has_no_active_locations_property(): void
    {
        $vm = $this->resolveViewModel();

        $this->assertFalse(
            property_exists($vm, 'activeLocations'),
            'CommunityFeedViewModel must not expose activeLocations after cleanup'
        );
    }

    public function test_viewmodel_has_no_hot_categories_property(): void
    {
        $vm = $this->resolveViewModel();

        $this->assertFalse(
            property_exists($vm, 'hotCategories'),
            'CommunityFeedViewModel must not expose hotCategories after cleanup'
        );
    }

    public function test_viewmodel_retains_required_properties(): void
    {
        $vm = $this->resolveViewModel();

        $this->assertTrue(property_exists($vm, 'posts'));
        $this->assertTrue(property_exists($vm, 'filters'));
        $this->assertTrue(property_exists($vm, 'paginator'));
        $this->assertTrue(property_exists($vm, 'quickSummary'));
        $this->assertTrue(property_exists($vm, 'buildings'));
        $this->assertTrue(property_exists($vm, 'categories'));
        $this->assertTrue(property_exists($vm, 'currentSort'));
        $this->assertTrue(property_exists($vm, 'sortOptions'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function communityHtml(): string
    {
        return (string) $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();
    }

    private function resolveViewModel(): CommunityFeedViewModel
    {
        $reporter = $this->makeReporter();
        $query = app(CommunityFeedQuery::class);

        return $query->forReporter($reporter, []);
    }

    private function makeReporter(): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeVisibleTicket(User $reporter, string $title, string $priority = 'medium'): Ticket
    {
        $location = Location::create([
            'name' => 'Aula SQ '.Str::upper(Str::random(4)),
            'building' => 'Edificio SQ',
            'floor' => '1',
            'room_code' => 'SQ-'.Str::upper(Str::random(4)),
            'qr_token' => 'qr-sq-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'CatSQ-'.Str::lower(Str::random(5)),
            'icon' => 'tag',
            'description' => 'Cat for sidebar cleanup tests',
        ]);

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción sidebar cleanup test: '.$title,
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => $priority,
            'community_visible' => true,
        ]);
    }
}
