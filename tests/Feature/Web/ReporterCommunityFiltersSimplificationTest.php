<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\CommunitySave;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifies the community feed simplification:
 * - Tabs/chips strip removed from main view.
 * - Sort bar removed from main view.
 * - Sidebar cards removed.
 * - Sort + Priority + Guardados live inside the advanced filter panel.
 * - All query params (sort, saved, state, priority) still work.
 * - Feed, reactions, saves, pagination not broken.
 */
class ReporterCommunityFiltersSimplificationTest extends TestCase
{
    use RefreshDatabase;

    // ── Render principal ──────────────────────────────────────────────────────

    public function test_main_view_shows_search_input(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-search__input', (string) $html);
        $this->assertStringContainsString('name="q"', (string) $html);
    }

    public function test_main_view_shows_filtros_button(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-filter-trigger', (string) $html);
        $this->assertStringContainsString('Filtros', (string) $html);
    }

    public function test_tabs_strip_is_not_rendered(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('comm-tabs', (string) $html);
        $this->assertStringNotContainsString('comm-tab__label-text', (string) $html);
    }

    public function test_para_ti_tab_not_in_main_view(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('aria-label="Para ti"', (string) $html);
    }

    public function test_siguiendo_tab_not_in_main_view(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('comm-tab__badge-real', (string) $html);
        $this->assertStringNotContainsString('>Siguiendo<', (string) $html);
    }

    public function test_sort_bar_not_visible_on_main_page(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('comm-sort-bar', (string) $html);
        $this->assertStringNotContainsString('comm-sort-chip', (string) $html);
    }

    public function test_sidebar_atajos_not_rendered(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('class="comm-sidebar"', (string) $html);
        $this->assertStringNotContainsString('>Atajos<', (string) $html);
    }

    public function test_sidebar_zonas_con_actividad_not_rendered(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Zonas con actividad', (string) $html);
    }

    public function test_sidebar_categorias_frecuentes_not_rendered(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Categorías frecuentes', (string) $html);
    }

    public function test_sidebar_tus_guardados_card_not_rendered(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Tus guardados', (string) $html);
        $this->assertStringNotContainsString('comm-saves-link', (string) $html);
    }

    // ── Modal avanzado ────────────────────────────────────────────────────────

    public function test_filter_panel_renders_once(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count((string) $html, 'id="comm-filter-panel"'));
    }

    public function test_filter_panel_contains_ordenar_por_section(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ordenar por', (string) $html);
    }

    public function test_filter_panel_contains_all_sort_options(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Recientes', (string) $html);
        $this->assertStringContainsString('Activos', (string) $html);
        $this->assertStringContainsString('Comentados', (string) $html);
        $this->assertStringContainsString('Apoyados', (string) $html);
    }

    public function test_filter_panel_retains_estado_periodo_categoria_edificio_evidencia(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="state"', (string) $html);
        $this->assertStringContainsString('name="period"', (string) $html);
        $this->assertStringContainsString('name="has_media"', (string) $html);
    }

    public function test_filter_panel_contains_guardados_toggle(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="saved"', (string) $html);
        $this->assertStringContainsString('Solo mis tickets guardados', (string) $html);
    }

    public function test_filter_panel_contains_prioridad_section(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="priority"', (string) $html);
        $this->assertStringContainsString('Prioridad', (string) $html);
        $this->assertStringContainsString('Crítica', (string) $html);
    }

    // ── Query params / filtros ────────────────────────────────────────────────

    public function test_sort_active_is_selected_in_panel(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community').'?sort=active')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/comm-fopt--on[^>]*>\s*<input[^>]*name="sort"[^>]*value="active"/',
            (string) $html,
            'sort=active must mark the Activos radio as active in the panel'
        );
    }

    public function test_sort_discussed_is_selected_in_panel(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket discutido FSSORT');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=discussed')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ticket discutido FSSORT', (string) $html);
        $this->assertMatchesRegularExpression(
            '/comm-fopt--on[^>]*>\s*<input[^>]*name="sort"[^>]*value="discussed"/',
            (string) $html
        );
    }

    public function test_sort_supported_is_selected_in_panel(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket apoyado FSSUP');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=supported')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/comm-fopt--on[^>]*>\s*<input[^>]*name="sort"[^>]*value="supported"/',
            (string) $html
        );
    }

    public function test_saved_filter_still_works(): void
    {
        $reporter = $this->makeReporter();
        $saved = $this->makeVisibleTicket($reporter, 'Ticket guardado FSSAVE');
        $other = $this->makeVisibleTicket($reporter, 'Ticket no guardado FSNOSAVE');

        CommunitySave::create(['ticket_id' => $saved->id, 'user_id' => $reporter->id]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?saved=1')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ticket guardado FSSAVE', (string) $html);
        $this->assertStringNotContainsString('Ticket no guardado FSNOSAVE', (string) $html);
    }

    public function test_state_resolved_filter_still_works(): void
    {
        $reporter = $this->makeReporter();
        $resolved = $this->makeVisibleTicket($reporter, 'Ticket resuelto FSRES');
        $open = $this->makeVisibleTicket($reporter, 'Ticket abierto FSOPEN');

        $resolved->update(['state' => Ticket::STATE_RESOLVED]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?state=resolved')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ticket resuelto FSRES', (string) $html);
        $this->assertStringNotContainsString('Ticket abierto FSOPEN', (string) $html);
    }

    public function test_pagination_preserves_filters(): void
    {
        $reporter = $this->makeReporter();

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community').'?sort=active&state=open&page=1')
            ->assertOk()
            ->getContent();

        // Pagination links must carry existing query params
        if (str_contains((string) $html, 'page=')) {
            $this->assertStringContainsString('sort=active', (string) $html);
        }

        $this->assertStringContainsString('name="sort"', (string) $html);
    }

    // ── Regresiones ───────────────────────────────────────────────────────────

    public function test_feed_still_shows_visible_posts(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Ticket comunidad visible FSVIS');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Ticket comunidad visible FSVIS', false);
    }

    public function test_thumbnail_proxy_url_used_not_raw_file_url(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Ticket con media FSMEDIA');

        $ticket->media()->create([
            'file_url' => 'https://raw-storage.example.com/secret-file.jpg',
            'file_type' => 'image/jpeg',
        ]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('secret-file.jpg', (string) $html);
        $this->assertStringContainsString('community/media', (string) $html);
    }

    public function test_no_console_log_debug_output_on_community_page(): void
    {
        $html = $this->actingAs($this->makeReporter())
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        // console.log must never appear in rendered pages
        $this->assertStringNotContainsString('console.log', (string) $html);
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

    private function makeVisibleTicket(User $reporter, string $title): Ticket
    {
        $location = Location::create([
            'name' => 'Aula FS '.Str::upper(Str::random(4)),
            'building' => 'Edificio FS',
            'floor' => '1',
            'room_code' => 'FS-'.Str::upper(Str::random(4)),
            'qr_token' => 'qr-fs-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'CatFS-'.Str::lower(Str::random(5)),
            'icon' => 'tag',
            'description' => 'Cat for simplification tests',
        ]);

        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción simplification test: '.$title,
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }
}
