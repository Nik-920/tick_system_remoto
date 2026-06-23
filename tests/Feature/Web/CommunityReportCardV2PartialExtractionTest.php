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
 * Community Report Card v2 — partial extraction regression suite.
 *
 * Verifies that splitting post-card.blade.php into 9 focused sub-partials
 * did not change the rendered output, break social actions, media proxy,
 * comments, or any data-attribute contract.
 */
class CommunityReportCardV2PartialExtractionTest extends TestCase
{
    use RefreshDatabase;

    // ── 1. Architecture ───────────────────────────────────────────────────────

    public function test_orchestrator_is_small_and_includes_all_partials(): void
    {
        $path = resource_path('views/reporter/community/partials/post-card.blade.php');
        $src = file_get_contents($path);

        $this->assertStringContainsString(
            'reporter.community.partials.post-card.topbar',
            $src,
            'Orchestrator must include topbar partial',
        );
        $this->assertStringContainsString(
            'reporter.community.partials.post-card.body',
            $src,
            'Orchestrator must include body partial',
        );
        $this->assertStringContainsString(
            'reporter.community.partials.post-card.actions',
            $src,
            'Orchestrator must include actions partial',
        );
        $this->assertStringContainsString(
            'reporter.community.partials.post-card.comments-toggle',
            $src,
            'Orchestrator must include comments-toggle partial',
        );
        $this->assertStringContainsString(
            'reporter.community.partials.post-card.footer',
            $src,
            'Orchestrator must include footer partial',
        );

        $lineCount = substr_count($src, "\n");
        $this->assertLessThanOrEqual(40, $lineCount, 'Orchestrator should be ≤40 lines');
    }

    public function test_all_partial_files_exist(): void
    {
        $base = resource_path('views/reporter/community/partials/post-card');
        $partials = [
            'topbar', 'body', 'meta-chips', 'description',
            'media', 'actions', 'report-action', 'comments-toggle', 'footer',
        ];

        foreach ($partials as $partial) {
            $this->assertFileExists(
                "{$base}/{$partial}.blade.php",
                "Partial '{$partial}' must exist after extraction",
            );
        }
    }

    public function test_orchestrator_has_single_php_block(): void
    {
        $src = file_get_contents(resource_path('views/reporter/community/partials/post-card.blade.php'));
        $count = substr_count($src, '@php');
        $this->assertSame(1, $count, 'Orchestrator must have exactly one @php block');
    }

    public function test_no_debug_in_partials(): void
    {
        $base = resource_path('views/reporter/community/partials/post-card');

        foreach (glob("{$base}/*.blade.php") as $file) {
            $src = file_get_contents($file);
            $this->assertStringNotContainsString('console.log', $src, basename($file));
            $this->assertStringNotContainsString('dd(', $src, basename($file));
            $this->assertStringNotContainsString('dump(', $src, basename($file));
            $this->assertStringNotContainsString('TODO', $src, basename($file));
            $this->assertStringNotContainsString('FIXME', $src, basename($file));
        }
    }

    // ── 2. Rendered output ────────────────────────────────────────────────────

    public function test_feed_renders_card_v2_shell_after_extraction(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Extraction test card EXTV2');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2', (string) $html);
        $this->assertStringContainsString('comm-post-v2__topbar', (string) $html);
        $this->assertStringContainsString('comm-post-v2__body', (string) $html);
        $this->assertStringContainsString('comm-post-v2__actions', (string) $html);
        $this->assertStringContainsString('comm-post-v2__footer', (string) $html);
    }

    public function test_topbar_partial_renders_category_and_public_chip(): void
    {
        $reporter = $this->makeReporter();
        $cat = $this->makeCategory('Infraestructura');
        $this->makeVisibleTicket($reporter, 'Topbar extraction EXT-TOPBAR', 'medium', null, $cat);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2__chip--cat', (string) $html);
        $this->assertStringContainsString('Infraestructura', (string) $html);
        $this->assertStringContainsString('comm-post-v2__chip--public', (string) $html);
        $this->assertStringContainsString('Reporte público', (string) $html);
    }

    public function test_topbar_partial_renders_state_and_priority_badges(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Badge extraction EXT-BADGE', 'high');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-badge--state', (string) $html);
        $this->assertStringContainsString('comm-badge--priority', (string) $html);
    }

    public function test_body_partial_renders_title_and_meta_chips(): void
    {
        $reporter = $this->makeReporter();
        $location = $this->makeLocation('EXT-SALA', 'Edificio Extracción', '3');
        $this->makeVisibleTicket($reporter, 'Body extraction EXT-BODY', 'medium', $location);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2__title', (string) $html);
        $this->assertStringContainsString('Body extraction EXT-BODY', (string) $html);
        $this->assertStringContainsString('comm-post-v2__chips', (string) $html);
        $this->assertStringContainsString('comm-post-v2__pill', (string) $html);
    }

    public function test_description_partial_renders_collapsible_details(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Desc extraction EXT-DESC');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2__desc', (string) $html);
    }

    public function test_media_partial_renders_proxy_url_not_raw_file_url(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Media proxy extraction EXT-MEDIA');

        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_path' => 'tickets/'.Str::uuid().'/extraction.jpg',
            'file_url' => 'https://storage.example.com/raw-extraction.jpg',
            'file_type' => 'image/jpeg',
            'file_name' => 'extraction.jpg',
            'file_size' => 2048,
            'position' => 0,
        ]);

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('community/media', (string) $html);
        $this->assertStringNotContainsString('raw-extraction.jpg', (string) $html);
        $this->assertStringNotContainsString('storage.example.com', (string) $html);
    }

    public function test_media_partial_renders_placeholder_when_no_images(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Placeholder extraction EXT-PLACEHOLDER');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2__media-placeholder', (string) $html);
        $this->assertStringContainsString('Sin evidencia', (string) $html);
    }

    public function test_actions_partial_preserves_social_forms_and_data_attributes(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Actions extraction EXT-ACTIONS');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-community-social-form', (string) $html);
        $this->assertStringContainsString('data-community-action="reaction"', (string) $html);
        $this->assertStringContainsString('data-community-action="save"', (string) $html);
        $this->assertStringContainsString('data-store-url', (string) $html);
        $this->assertStringContainsString('data-destroy-url', (string) $html);
        $this->assertStringContainsString('aria-pressed', (string) $html);
        $this->assertStringContainsString('Me interesa', (string) $html);
        $this->assertStringContainsString('También me pasa', (string) $html);
        $this->assertStringContainsString('Lo vi', (string) $html);
        $this->assertStringContainsString('Guardar', (string) $html);
    }

    public function test_report_action_partial_renders_reportar(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Report action extraction EXT-REPORTAR');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-report-summary--danger', (string) $html);
        $this->assertStringContainsString('Reportar', (string) $html);
    }

    public function test_media_partial_preserves_carousel_data_attributes(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeVisibleTicket($reporter, 'Carousel extraction EXT-CAR');

        foreach (range(1, 2) as $i) {
            TicketMedia::create([
                'ticket_id' => $ticket->id,
                'file_path' => 'tickets/'.Str::uuid()."/img{$i}.jpg",
                'file_url' => "https://storage.example.com/img{$i}.jpg",
                'file_type' => 'image/jpeg',
                'file_name' => "img{$i}.jpg",
                'file_size' => 1024,
                'position' => $i - 1,
            ]);
        }

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-comm-car', (string) $html);
        $this->assertStringContainsString('data-car-slide', (string) $html);
        $this->assertStringContainsString('data-community-media-frame', (string) $html);
        $this->assertStringContainsString('data-community-media-img', (string) $html);
        $this->assertStringContainsString('data-community-media-fallback', (string) $html);
        $this->assertStringContainsString('data-car-prev', (string) $html);
        $this->assertStringContainsString('data-car-next', (string) $html);
        $this->assertStringContainsString('data-car-dot', (string) $html);
    }

    public function test_comments_toggle_partial_renders_comments_section(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Comments toggle extraction EXT-COMMENTS');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2__comments', (string) $html);
        $this->assertStringContainsString('comm-comments-details', (string) $html);
        $this->assertStringContainsString('comm-comments-summary', (string) $html);
        $this->assertStringContainsString('Comentarios', (string) $html);
    }

    public function test_footer_partial_renders_verification_and_anonymous(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Footer extraction EXT-FOOTER');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('comm-post-v2__footer', (string) $html);
        $this->assertStringContainsString('Verificación activa', (string) $html);
        $this->assertStringContainsString('Creado por usuario anónimo', (string) $html);
    }

    // ── 3. Cleanup ────────────────────────────────────────────────────────────

    public function test_no_legacy_v1_card_markup_rendered(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'Legacy cleanup EXT-LEGACY');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('comm-post__thumb-placeholder', (string) $html);
        $this->assertStringNotContainsString('comm-post__thumb-img', (string) $html);
        $this->assertStringNotContainsString('comm-post__overlay-btn', (string) $html);
        $this->assertStringNotContainsString('comm-post__tags', (string) $html);
        $this->assertStringNotContainsString('comm-post__summary', (string) $html);
        $this->assertStringNotContainsString('comm-post__loc-pill', (string) $html);
        $this->assertStringNotContainsString('comm-post__title-text', (string) $html);
    }

    public function test_no_legacy_tabs_sidebar_sortbar(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'No legacy tabs EXT-NOTABS');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('comm-tabs', (string) $html);
        $this->assertStringNotContainsString('comm-sort-bar', (string) $html);
        $this->assertStringNotContainsString('class="comm-sidebar"', (string) $html);
    }

    public function test_no_pii_in_rendered_card(): void
    {
        $reporter = $this->makeReporter();
        $this->makeVisibleTicket($reporter, 'No PII extraction EXT-PII');

        $html = $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->getContent();

        // The footer must show anonymous authorship — the reporter's real name / email
        // must NOT appear inside the card (they may legitimately appear in the layout nav,
        // but the ticket card must never leak reporter identity).
        $this->assertStringContainsString('Creado por usuario anónimo', (string) $html);
        $this->assertStringNotContainsString('reporter_id', (string) $html);
        $this->assertStringNotContainsString('firebase-auth-uid', (string) $html);
    }

    public function test_orchestrator_partial_files_have_no_raw_file_url(): void
    {
        $base = resource_path('views/reporter/community/partials/post-card');

        foreach (glob("{$base}/*.blade.php") as $file) {
            $src = file_get_contents($file);
            // Check that file_url is not rendered as a PHP expression.
            // Comments explaining the constraint (e.g. "file_url is never rendered")
            // are allowed and intentionally excluded from this check.
            $this->assertStringNotContainsString(
                "\$post['file_url']",
                $src,
                basename($file).' must not render $post[\'file_url\'] directly',
            );
            $this->assertStringNotContainsString(
                '->file_url',
                $src,
                basename($file).' must not access ->file_url directly',
            );
        }
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
            'description' => 'Descripción de extracción para test de partials.',
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
        string $building = 'Edificio Test',
        string $floor = '1',
    ): Location {
        return Location::create([
            'name' => 'Sala EXT '.Str::upper(Str::random(4)),
            'building' => $building,
            'floor' => $floor,
            'room_code' => $roomCode !== '' ? $roomCode : 'EXT-'.Str::upper(Str::random(5)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(string $name = ''): Category
    {
        return Category::create([
            'name' => $name !== '' ? $name : 'CatExt-'.Str::lower(Str::random(5)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de extracción',
        ]);
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
