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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression suite for Community images not displaying.
 *
 * Root cause: config('community.media.supabase_public_bucket') defaulted to
 * 'tickets' while the actual Supabase bucket is 'TableTicket' (SUPABASE_BUCKET_TICKETS).
 * CommunityRemoteMediaFetcher::isAllowedUrl() rejected every real URL because
 * the path prefix check failed: 'storage/v1/object/public/tickets/' did not
 * match 'storage/v1/object/public/TableTicket/…'.
 *
 * Fix: config now falls back to env('SUPABASE_BUCKET_TICKETS', 'tickets').
 */
class CommunityMediaImagesDisplayRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
    }

    // ── Feed render ───────────────────────────────────────────────────────────

    public function test_feed_renders_proxy_image_urls_for_media_items(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeMediaRecord($ticket, $reporter, 'https://demo.incidex.test/e/img.jpg', 'image');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee(route('reporter.community.media.thumbnail', $media->id), false);
    }

    public function test_feed_does_not_render_raw_file_url(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $this->makeMediaRecord($ticket, $reporter, 'https://demo.incidex.test/e/raw-leak.jpg', 'image');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('https://demo.incidex.test/e/raw-leak.jpg', false);
    }

    public function test_feed_does_not_render_supabase_raw_url(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $this->makeMediaRecord(
            $ticket,
            $reporter,
            'https://lxmhnsrvoehqdyndwptz.supabase.co/storage/v1/object/public/TableTicket/tickets/media/test.jpg',
            'image',
        );

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('lxmhnsrvoehqdyndwptz.supabase.co', false);
    }

    public function test_feed_does_not_render_community_thumbnails_disk_path(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $path = 'ticket-evidence/feed-leak/img.jpg';
        Storage::disk('public')->put($path, $this->minimalJpeg());
        $this->makeMediaRecord($ticket, $reporter, Storage::disk('public')->url($path), 'image');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('community-thumbnails', false);
    }

    public function test_feed_includes_fallback_markup_for_unavailable_preview(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $this->makeMediaRecord($ticket, $reporter, 'https://demo.incidex.test/e/img.jpg', 'image');

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('data-community-media-fallback', false)
            ->assertSee('Vista previa no disponible', false);
    }

    // ── Proxy: local files ────────────────────────────────────────────────────

    public function test_local_image_media_returns_200_image_jpeg(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $path = 'ticket-evidence/regression/local.jpg';
        Storage::disk('public')->put($path, $this->minimalJpeg());
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeMediaRecord($ticket, $reporter, Storage::disk('public')->url($path), 'image');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_local_image_creates_thumbnail_if_missing(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $path = 'ticket-evidence/regression/gen.jpg';
        Storage::disk('public')->put($path, $this->minimalJpeg());
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeMediaRecord($ticket, $reporter, Storage::disk('public')->url($path), 'image');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();

        $this->assertNotEmpty(Storage::disk('public')->allFiles('community-thumbnails'));
    }

    public function test_local_image_uses_cached_thumbnail_on_second_request(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $path = 'ticket-evidence/regression/cached.jpg';
        Storage::disk('public')->put($path, $this->minimalJpeg());
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeMediaRecord($ticket, $reporter, Storage::disk('public')->url($path), 'image');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();

        $filesAfterFirst = Storage::disk('public')->allFiles('community-thumbnails');

        // Second request must not create additional files.
        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();

        $this->assertSame($filesAfterFirst, Storage::disk('public')->allFiles('community-thumbnails'));
    }

    // ── Proxy: Supabase remote (Http::fake) ───────────────────────────────────

    public function test_supabase_allowlisted_image_returns_200_image_jpeg(): void
    {
        Storage::fake('public');
        $host = 'test.supabase.co';
        $this->configureSupabase($host, 'TableTicket');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $fileUrl = "https://{$host}/storage/v1/object/public/TableTicket/tickets/media/test.jpg";
        $media = $this->makeMediaRecord($ticket, $reporter, $fileUrl, 'image');

        Http::fake([
            $fileUrl => Http::response($this->minimalJpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_supabase_missing_config_rejects_remote_url_safely(): void
    {
        Storage::fake('public');
        Http::fake();
        config(['community.media.remote_allowed_hosts' => []]);

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $fileUrl = 'https://test.supabase.co/storage/v1/object/public/TableTicket/tickets/media/test.jpg';
        $media = $this->makeMediaRecord($ticket, $reporter, $fileUrl, 'image');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_supabase_non_image_content_type_returns_404(): void
    {
        Storage::fake('public');
        $host = 'test.supabase.co';
        $this->configureSupabase($host, 'TableTicket');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $fileUrl = "https://{$host}/storage/v1/object/public/TableTicket/tickets/media/doc.pdf";
        $media = $this->makeMediaRecord($ticket, $reporter, $fileUrl, 'image');

        Http::fake([
            $fileUrl => Http::response('%PDF-1.4 content', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_supabase_disallowed_host_is_rejected_and_not_fetched(): void
    {
        Storage::fake('public');
        Http::fake();
        $this->configureSupabase('allowed.supabase.co', 'TableTicket');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeMediaRecord(
            $ticket,
            $reporter,
            'https://evil.example.com/storage/v1/object/public/TableTicket/tickets/media/img.jpg',
            'image',
        );

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    // ── Visibility gates ──────────────────────────────────────────────────────

    public function test_proxy_returns_404_if_ticket_hidden_after_thumbnail_cache_exists(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $path = 'ticket-evidence/gate-hide/img.jpg';
        Storage::disk('public')->put($path, $this->minimalJpeg());
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeMediaRecord($ticket, $reporter, Storage::disk('public')->url($path), 'image');

        // Prime cache.
        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();

        $ticket->forceFill(['community_visible' => false])->save();

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_proxy_returns_404_if_ticket_cancelled_after_thumbnail_cache_exists(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $path = 'ticket-evidence/gate-cancel/img.jpg';
        Storage::disk('public')->put($path, $this->minimalJpeg());
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeMediaRecord($ticket, $reporter, Storage::disk('public')->url($path), 'image');

        // Prime cache.
        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();

        $ticket->forceFill(['state' => Ticket::STATE_CANCELLED])->save();

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    // ── Bug regression ────────────────────────────────────────────────────────

    /**
     * Regression: a post with local media must show a valid proxy URL in the
     * feed HTML and that proxy must return 200 image/jpeg.
     */
    public function test_community_post_with_local_media_shows_working_proxy_url(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $path = 'ticket-evidence/bug-regr/local.jpg';
        Storage::disk('public')->put($path, $this->minimalJpeg());
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeMediaRecord($ticket, $reporter, Storage::disk('public')->url($path), 'image');

        $proxyUrl = route('reporter.community.media.thumbnail', $media->id);

        // Feed must render the proxy URL.
        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee($proxyUrl, false);

        // Proxy must return 200 image/jpeg (no 404).
        $this->actingAs($reporter)
            ->get($proxyUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    /**
     * Regression: a post with Supabase media using the real bucket name
     * (TableTicket) must not be rejected by the allowlist check.
     * This was the root cause: bucket mismatch between config default ('tickets')
     * and real bucket ('TableTicket') caused silent 404 on every Supabase image.
     */
    public function test_community_post_with_supabase_media_real_bucket_shows_working_proxy_url(): void
    {
        Storage::fake('public');
        $host = 'test.supabase.co';
        $bucket = 'TableTicket';
        $this->configureSupabase($host, $bucket);

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter, title: 'Proyector malogrado');
        $fileUrl = "https://{$host}/storage/v1/object/public/{$bucket}/tickets/media/img.jpg";
        $media = $this->makeMediaRecord($ticket, $reporter, $fileUrl, 'image');

        Http::fake([
            $fileUrl => Http::response($this->minimalJpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $proxyUrl = route('reporter.community.media.thumbnail', $media->id);

        // Feed must use proxy URL, not raw Supabase URL.
        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee($proxyUrl, false)
            ->assertDontSee($host, false);

        // Proxy must return 200 image/jpeg (not 404).
        $this->actingAs($reporter)
            ->get($proxyUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    /**
     * Regression (negative): when supabase_public_bucket is set to the wrong
     * bucket name, the URL is correctly rejected. This proves the fix is load-bearing.
     */
    public function test_supabase_url_with_wrong_bucket_config_is_rejected(): void
    {
        Storage::fake('public');
        Http::fake();
        $host = 'test.supabase.co';

        // Intentionally set wrong bucket — simulates old broken config.
        $this->configureSupabase($host, 'wrong-bucket');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $fileUrl = "https://{$host}/storage/v1/object/public/TableTicket/tickets/media/img.jpg";
        $media = $this->makeMediaRecord($ticket, $reporter, $fileUrl, 'image');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeReporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeTicket(
        User $reporter,
        bool $communityVisible = true,
        string $state = Ticket::STATE_OPEN,
        string $title = '',
    ): Ticket {
        return Ticket::create([
            'title' => $title !== '' ? $title : 'Ticket regr '.Str::random(6),
            'description' => 'Descripción regresión.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => $communityVisible,
        ]);
    }

    private function makeMediaRecord(Ticket $ticket, User $uploader, string $fileUrl, string $fileType): TicketMedia
    {
        return TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => $fileUrl,
            'file_type' => $fileType,
            'uploaded_by' => $uploader->id,
        ]);
    }

    private function configureSupabase(string $host, string $bucket): void
    {
        config([
            'community.media.remote_allowed_hosts' => [$host],
            'community.media.supabase_public_bucket' => $bucket,
        ]);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Aula Regr '.Str::random(4),
            'building' => 'Edificio R',
            'floor' => '1',
            'room_code' => 'RG-'.Str::upper(Str::random(5)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para regression tests',
        ]);
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function minimalJpeg(): string
    {
        $im = imagecreatetruecolor(4, 4);
        $white = imagecolorallocate($im, 255, 255, 255);
        if ($white !== false) {
            imagefill($im, 0, 0, $white);
        }
        ob_start();
        imagejpeg($im, null, 80);
        imagedestroy($im);

        return (string) ob_get_clean();
    }
}
