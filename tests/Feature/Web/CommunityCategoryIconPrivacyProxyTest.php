<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Category Icon Privacy Proxy — security and integration tests.
 *
 * Verifies that /reporter/community no longer exposes raw Supabase URLs for
 * category icons, and that the proxy endpoint applies SSRF hardening identical
 * to the existing community media thumbnail proxy.
 */
class CommunityCategoryIconPrivacyProxyTest extends TestCase
{
    use RefreshDatabase;

    private const SUPABASE_HOST = 'test.supabase.co';

    private const ICON_URL = 'https://test.supabase.co/storage/v1/object/public/TicketCategoria/categories/icons/test-cat/icon.jpg';

    private const FAKE_IMAGE_BYTES = 'FAKEJPEG';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
    }

    // ── HTML Render: proxy URL in feed, no raw Supabase URL ──────────────────

    public function test_community_feed_uses_proxy_url_for_image_category_icon(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->makeTicket($reporter, $category);

        $response = $this->actingAs($reporter)->get(route('reporter.community'));

        $response->assertOk();
        $response->assertSee('/reporter/community/categories/', false);
        $response->assertSee('/icon', false);
        $response->assertSee('comm-post-v2__cat-icon-img', false);
    }

    public function test_community_feed_does_not_expose_raw_supabase_url_in_html(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->makeTicket($reporter, $category);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee(self::SUPABASE_HOST, false);
    }

    public function test_community_feed_does_not_expose_raw_icon_url(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->makeTicket($reporter, $category);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee(self::ICON_URL, false);
    }

    public function test_community_feed_does_not_contain_lucide_https_or_lucide_http(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->makeTicket($reporter, $category);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('lucide-https', false)
            ->assertDontSee('lucide-http', false);
    }

    public function test_lucide_icon_category_renders_without_img_tag(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory('wifi');
        $this->makeTicket($reporter, $category);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('comm-post-v2__cat-icon-img', false)
            ->assertDontSee('lucide-https', false);
    }

    // ── Proxy access control ─────────────────────────────────────────────────

    public function test_authenticated_reporter_can_access_icon_proxy(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->configureSupabase(self::SUPABASE_HOST);
        Http::fake([self::ICON_URL => Http::response(self::FAKE_IMAGE_BYTES, 200, ['Content-Type' => 'image/jpeg'])]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertOk();
    }

    public function test_unauthenticated_user_is_redirected_from_icon_proxy(): void
    {
        $category = $this->makeCategory(self::ICON_URL);

        $this->get(route('reporter.community.categories.icon', $category))
            ->assertRedirect();
    }

    public function test_non_reporter_user_cannot_access_icon_proxy(): void
    {
        $user = User::factory()->create();
        $user->assignRole('maintenance');
        $category = $this->makeCategory(self::ICON_URL);

        $this->actingAs($user)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertForbidden();
    }

    public function test_category_with_lucide_icon_returns_404_from_proxy(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory('wrench');

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    public function test_category_with_empty_icon_returns_404_from_proxy(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory('');

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    // ── SSRF / URL security ──────────────────────────────────────────────────

    public function test_javascript_uri_icon_returns_404(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory('javascript:alert(1)');

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    public function test_data_uri_icon_returns_404(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory('data:image/png;base64,abc123');

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    public function test_file_uri_icon_returns_404(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory('file:///etc/passwd');

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    public function test_loopback_ip_url_returns_404(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory('http://127.0.0.1/secret');

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    public function test_localhost_url_returns_404(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory('http://localhost/secret');

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    public function test_url_with_userinfo_returns_404(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(
            'https://user:pass@test.supabase.co/storage/v1/object/public/TicketCategoria/categories/icons/icon.jpg',
        );

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    public function test_non_allowlisted_host_returns_404(): void
    {
        $reporter = $this->makeReporter();
        $this->configureSupabase(self::SUPABASE_HOST);
        $category = $this->makeCategory(
            'https://evil.example.com/storage/v1/object/public/TicketCategoria/categories/icons/icon.jpg',
        );

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    public function test_path_targeting_wrong_bucket_returns_404(): void
    {
        $reporter = $this->makeReporter();
        $this->configureSupabase(self::SUPABASE_HOST);
        $category = $this->makeCategory(
            'https://test.supabase.co/storage/v1/object/public/OTHER_BUCKET/categories/icons/icon.jpg',
        );

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    public function test_html_content_type_response_returns_404(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->configureSupabase(self::SUPABASE_HOST);
        Http::fake([self::ICON_URL => Http::response('<html>evil</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    public function test_oversized_file_via_content_length_returns_404(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->configureSupabase(self::SUPABASE_HOST);
        Http::fake([self::ICON_URL => Http::response('x', 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => (string) (3 * 1024 * 1024), // 3 MB > 2 MB limit
        ])]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertNotFound();
    }

    // ── Successful proxy fetch ────────────────────────────────────────────────

    public function test_allowlisted_supabase_url_returns_200(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->configureSupabase(self::SUPABASE_HOST);
        Http::fake([self::ICON_URL => Http::response(self::FAKE_IMAGE_BYTES, 200, ['Content-Type' => 'image/jpeg'])]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertOk();
    }

    public function test_response_has_image_content_type(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->configureSupabase(self::SUPABASE_HOST);
        Http::fake([self::ICON_URL => Http::response(self::FAKE_IMAGE_BYTES, 200, ['Content-Type' => 'image/jpeg'])]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category));

        $response->assertOk();
        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type', ''));
    }

    public function test_response_has_x_content_type_options_nosniff(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->configureSupabase(self::SUPABASE_HOST);
        Http::fake([self::ICON_URL => Http::response(self::FAKE_IMAGE_BYTES, 200, ['Content-Type' => 'image/jpeg'])]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_response_has_private_cache_control(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->configureSupabase(self::SUPABASE_HOST);
        Http::fake([self::ICON_URL => Http::response(self::FAKE_IMAGE_BYTES, 200, ['Content-Type' => 'image/jpeg'])]);

        $response = $this->actingAs($reporter)
            ->get(route('reporter.community.categories.icon', $category));

        $response->assertOk();
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
    }

    // ── Regressions ───────────────────────────────────────────────────────────

    public function test_community_feed_page_loads_without_errors(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory(self::ICON_URL);
        $this->makeTicket($reporter, $category);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk();
    }

    public function test_feed_does_not_expose_raw_ticket_media_file_url(): void
    {
        $reporter = $this->makeReporter();
        $category = $this->makeCategory('tag');
        $location = $this->makeLocation();

        $ticket = Ticket::create([
            'title' => 'Ticket con evidencia privada',
            'description' => 'Descripción.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);

        $mediaUrl = 'https://'.self::SUPABASE_HOST.'/storage/v1/object/public/TableTicket/tickets/media/secret.jpg';
        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => $mediaUrl,
            'file_type' => 'image/jpeg',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee($mediaUrl, false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeReporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeCategory(string $icon): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => $icon,
            'description' => 'Categoría de prueba proxy',
        ]);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Loc-'.Str::random(4),
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'RM-'.Str::upper(Str::random(4)),
            'qr_token' => 'qr-'.Str::lower(Str::random(10)),
            'is_active' => true,
        ]);
    }

    private function makeTicket(User $reporter, Category $category): Ticket
    {
        $location = $this->makeLocation();

        return Ticket::create([
            'title' => 'Ticket proxy test '.Str::random(5),
            'description' => 'Descripción del ticket de prueba.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ]);
    }

    private function configureSupabase(string $host): void
    {
        config([
            'community.media.remote_allowed_hosts' => [$host],
            'community.category_icons.supabase_bucket' => 'TicketCategoria',
        ]);
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
