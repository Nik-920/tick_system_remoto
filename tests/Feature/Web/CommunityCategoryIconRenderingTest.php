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
 * Regression suite for Community category icon rendering.
 *
 * Root cause: $post['category']['icon'] could contain a Supabase image URL,
 * which was passed directly to `'lucide-' . $icon`, producing an invalid
 * component name like `lucide-https://...` and throwing an
 * InvalidArgumentException at render time.
 *
 * Fix: CommunityFeedQuery::normalizeCategory() maps icon to a typed shape
 * (icon_type / icon_name / icon_url) so the Blade partial can branch safely.
 */
class CommunityCategoryIconRenderingTest extends TestCase
{
    use RefreshDatabase;

    private const SUPABASE_ICON_URL = 'https://lxmhnsrvoehqdyndwptz.supabase.co/storage/v1/object/public/TicketCategoria/categories/icons/019ee299-9203-70b0-aecd-bcbec3768ba3/harware.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
    }

    // ── Lucide icons (normal path) ────────────────────────────────────────────

    public function test_category_with_lucide_icon_tag_renders_200(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory($reporter, icon: 'tag');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk();
    }

    public function test_category_with_lucide_icon_box_renders_200(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory($reporter, icon: 'box');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk();
    }

    public function test_lucide_icon_renders_as_svg_not_img(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory($reporter, icon: 'wrench');

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk();
        // Lucide compiles to inline SVG — no img tag with cat-icon-img class.
        $response->assertDontSee('comm-post-v2__cat-icon-img', false);
        // No invalid lucide-URL construction.
        $response->assertDontSee('lucide-https', false);
        $response->assertDontSee('lucide-http', false);
    }

    // ── Supabase image URL ────────────────────────────────────────────────────

    public function test_page_does_not_throw_when_category_icon_is_supabase_url(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory($reporter, icon: self::SUPABASE_ICON_URL);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk();
    }

    public function test_supabase_icon_url_renders_img_tag(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory($reporter, icon: self::SUPABASE_ICON_URL);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk();
        $response->assertSee('<img', false);
        // Raw Supabase URL must not appear; the proxy URL must be used instead.
        $response->assertDontSee(self::SUPABASE_ICON_URL, false);
        $response->assertSee('/reporter/community/categories/', false);
        $response->assertSee('comm-post-v2__cat-icon-img', false);
    }

    public function test_html_does_not_contain_lucide_https(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory($reporter, icon: self::SUPABASE_ICON_URL);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('lucide-https', false)
            ->assertDontSee('lucide-http', false);
    }

    public function test_image_url_is_in_img_src_attribute(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::SUPABASE_ICON_URL);
        $this->makeTicketWithExistingCategory($reporter, $category);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk();
        $content = (string) $response->getContent();

        // The proxy URL (not the raw Supabase URL) must be in the img src.
        $proxyUrl = route('reporter.community.categories.icon', $category);
        $this->assertStringContainsString('src="'.$proxyUrl.'"', $content);
        $this->assertStringNotContainsString(e(self::SUPABASE_ICON_URL), $content);
    }

    // ── Fallback cases ────────────────────────────────────────────────────────

    public function test_empty_icon_falls_back_without_throwing(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory($reporter, icon: '');

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        // Falls back to lucide-tag (SVG, no img), page must not throw.
        $response->assertOk();
        $response->assertDontSee('lucide-https', false);
        $response->assertDontSee('comm-post-v2__cat-icon-img', false);
    }

    public function test_icon_with_path_traversal_falls_back_without_throwing(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory($reporter, icon: '../../bad');

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        // Invalid icon chars are stripped; lucide-tag SVG renders instead.
        $response->assertOk();
        $response->assertDontSee('comm-post-v2__cat-icon-img', false);
    }

    public function test_icon_with_spaces_falls_back_without_throwing(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory($reporter, icon: 'icon with spaces');

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk();
        $response->assertDontSee('comm-post-v2__cat-icon-img', false);
    }

    // ── Security: javascript: URI must not render ─────────────────────────────

    public function test_javascript_uri_icon_does_not_render_as_img_src(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory($reporter, icon: 'javascript:alert(1)');

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        // Must not appear as img src, must not appear in HTML at all.
        $response->assertOk();
        $response->assertDontSee('javascript:alert', false);
        $response->assertDontSee('comm-post-v2__cat-icon-img', false);
    }

    // ── No regression: feed and media still work ─────────────────────────────

    public function test_feed_still_shows_ticket_title_with_url_icon_category(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory(
            $reporter,
            icon: self::SUPABASE_ICON_URL,
            title: 'Incidencia con categoría imagen',
        );

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Incidencia con categoría imagen', false);
    }

    public function test_feed_still_shows_ticket_title_with_lucide_icon_category(): void
    {
        $reporter = $this->makeReporter();
        $this->makeTicketWithCategory(
            $reporter,
            icon: 'wifi',
            title: 'Incidencia con categoría lucide',
        );

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community'));

        $response->assertOk();
        $response->assertSee('Incidencia con categoría lucide', false);
        // Lucide compiles to inline SVG; no img with cat-icon-img class.
        $response->assertDontSee('comm-post-v2__cat-icon-img', false);
        $response->assertDontSee('lucide-https', false);
    }

    public function test_html_does_not_expose_raw_file_url_of_ticket_media(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory('wifi');
        $location = $this->makeLocation();

        $ticket = Ticket::create([
            'title' => 'Ticket con evidencia',
            'description' => 'Descripción con evidencia.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);

        $rawUrl = 'https://lxmhnsrvoehqdyndwptz.supabase.co/storage/v1/object/public/TableTicket/evidence/raw-secret.jpg';

        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => $rawUrl,
            'file_type' => 'image/jpeg',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee($rawUrl, false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeReporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeTicketWithCategory(
        User $reporter,
        string $icon = 'tag',
        string $title = '',
    ): Ticket {
        $category = $this->makeCategory($icon);

        return $this->makeTicketWithExistingCategory($reporter, $category, $title);
    }

    private function makeTicketWithExistingCategory(
        User $reporter,
        Category $category,
        string $title = '',
    ): Ticket {
        $location = $this->makeLocation();

        return Ticket::create([
            'title' => $title !== '' ? $title : 'Ticket cat icon '.Str::random(5),
            'description' => 'Descripción del ticket.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function makeCategory(string $icon = 'tag'): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => $icon,
            'description' => 'Categoría para icon rendering tests',
        ]);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Aula IconTest '.Str::random(4),
            'building' => 'Edificio I',
            'floor' => '1',
            'room_code' => 'IC-'.Str::upper(Str::random(5)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
