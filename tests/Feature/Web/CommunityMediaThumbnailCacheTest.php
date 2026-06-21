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
 * Community Media Thumbnail Cache — local thumbnails, Supabase remote, SSRF
 * blocks, visibility gates with cached thumbnails, and feed no-leak.
 *
 * Security contract:
 * - Visibility gates are re-checked on every request, even when a cached
 *   thumbnail already exists on disk.
 * - Remote fetch only happens for allowlisted https Supabase hosts.
 * - Private IPs, http://, userinfo, and wrong hosts are never fetched.
 * - Feed HTML must never contain raw file_url or cached disk paths.
 */
class CommunityMediaThumbnailCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
    }

    // ── Local thumbnails ──────────────────────────────────────────────────────

    public function test_local_image_media_returns_200(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $media = $this->makeLocalMedia($reporter, 'ticket-evidence/local/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();
    }

    public function test_local_image_creates_thumbnail_in_cache_path(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $media = $this->makeLocalMedia($reporter, 'ticket-evidence/cache-check/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();

        $thumbnailFiles = Storage::disk('public')->allFiles('community-thumbnails');
        $this->assertNotEmpty($thumbnailFiles, 'A thumbnail should be written to community-thumbnails/');
    }

    public function test_second_request_serves_cached_thumbnail_without_extra_remote_call(): void
    {
        Storage::fake('public');
        $this->configureSupabase();
        $reporter = $this->makeReporter();
        $media = $this->makeSupabaseMedia($reporter);

        Http::fake([
            $media->file_url => Http::response($this->minimalJpeg(), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        // First request — remote fetch happens, thumbnail is written to cache.
        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();

        Http::assertSentCount(1);

        // Second request — thumbnail already cached; no new HTTP call.
        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();

        Http::assertSentCount(1);
    }

    public function test_response_content_type_is_image_jpeg(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $media = $this->makeLocalMedia($reporter, 'ticket-evidence/ctype/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_response_has_nosniff_and_private_cache_headers(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $media = $this->makeLocalMedia($reporter, 'ticket-evidence/headers/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'max-age=300, private');
    }

    // ── Supabase remote thumbnails ────────────────────────────────────────────

    public function test_supabase_allowed_url_generates_thumbnail(): void
    {
        Storage::fake('public');
        $this->configureSupabase();
        $reporter = $this->makeReporter();
        $media = $this->makeSupabaseMedia($reporter);

        Http::fake([
            $media->file_url => Http::response($this->minimalJpeg(), 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_supabase_response_with_non_image_content_type_returns_404(): void
    {
        Storage::fake('public');
        $this->configureSupabase();
        $reporter = $this->makeReporter();
        $media = $this->makeSupabaseMedia($reporter);

        Http::fake([
            $media->file_url => Http::response('<html>not an image</html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_supabase_response_exceeding_max_bytes_returns_404(): void
    {
        Storage::fake('public');
        $this->configureSupabase();
        config(['community.media.max_remote_bytes' => 10]);
        $reporter = $this->makeReporter();
        $media = $this->makeSupabaseMedia($reporter);

        Http::fake([
            $media->file_url => Http::response($this->minimalJpeg(), 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => '999999',
            ]),
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_supabase_404_returns_proxy_404(): void
    {
        Storage::fake('public');
        $this->configureSupabase();
        $reporter = $this->makeReporter();
        $media = $this->makeSupabaseMedia($reporter);

        Http::fake([
            $media->file_url => Http::response('Not Found', 404),
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_disallowed_host_is_rejected_and_not_fetched(): void
    {
        Storage::fake('public');
        Http::fake();
        $this->configureSupabase('allowed.supabase.co');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://evil.example.com/storage/v1/object/public/tickets/evidence/img.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_http_scheme_url_is_rejected(): void
    {
        Storage::fake('public');
        Http::fake();
        $this->configureSupabase('test.supabase.co');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'http://test.supabase.co/storage/v1/object/public/tickets/evidence/img.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_localhost_url_is_rejected_for_remote_fetch(): void
    {
        Storage::fake('public');
        Http::fake();

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'http://localhost/storage/ticket-evidence/ghost/img.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        // File is absent from local disk → falls through to remote fetch attempt.
        // Remote fetch must refuse localhost (not https + private host).
        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_url_with_userinfo_is_rejected(): void
    {
        Storage::fake('public');
        Http::fake();
        $this->configureSupabase('test.supabase.co');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://user:pass@test.supabase.co/storage/v1/object/public/tickets/evidence/img.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_redirect_to_any_host_is_not_followed(): void
    {
        Storage::fake('public');
        $this->configureSupabase();
        $reporter = $this->makeReporter();
        $media = $this->makeSupabaseMedia($reporter);

        Http::fake([
            $media->file_url => Http::response('', 301, [
                'Location' => 'https://evil.example.com/img.jpg',
            ]),
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    // ── Visibility gates with cache ───────────────────────────────────────────

    public function test_cached_thumbnail_not_served_after_ticket_hidden(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeLocalMediaForTicket($reporter, $ticket, 'ticket-evidence/gate1/img.jpg');

        // Prime the cache.
        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();

        // Admin hides the ticket from community.
        $ticket->forceFill(['community_visible' => false])->save();

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_cached_thumbnail_not_served_after_ticket_cancelled(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeLocalMediaForTicket($reporter, $ticket, 'ticket-evidence/gate2/img.jpg');

        // Prime the cache.
        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();

        $ticket->forceFill(['state' => Ticket::STATE_CANCELLED])->save();

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_cached_thumbnail_not_served_after_ticket_rejected(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeLocalMediaForTicket($reporter, $ticket, 'ticket-evidence/gate3/img.jpg');

        // Prime the cache.
        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();

        $ticket->forceFill(['state' => Ticket::STATE_REJECTED])->save();

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_guest_cannot_access_cached_thumbnail_through_proxy(): void
    {
        $media = $this->makeVisibleMediaRecord();

        $this->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertRedirect(route('login'));
    }

    public function test_maintenance_cannot_access_reporter_community_route(): void
    {
        $maintenance = $this->makeUserWithRole('maintenance');
        $media = $this->makeVisibleMediaRecord();

        $this->actingAs($maintenance)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertForbidden();
    }

    // ── Feed no-leak regression ───────────────────────────────────────────────

    public function test_feed_uses_proxy_url_not_cached_disk_url(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://demo.incidex.test/e/test.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee(route('reporter.community.media.thumbnail', $media->id), false)
            ->assertDontSee('community-thumbnails', false);
    }

    public function test_feed_does_not_contain_raw_supabase_url(): void
    {
        $this->configureSupabase('myproject.supabase.co');
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://myproject.supabase.co/storage/v1/object/public/tickets/evidence/img.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('myproject.supabase.co', false);
    }

    public function test_feed_does_not_contain_local_storage_path(): void
    {
        Storage::fake('public');
        $reporter = $this->makeReporter();
        $path = 'ticket-evidence/noleak/secret.jpg';
        Storage::disk('public')->put($path, $this->minimalJpeg());
        $ticket = $this->makeTicket($reporter);
        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => Storage::disk('public')->url($path),
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee($path, false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeReporter(): User
    {
        return $this->makeUserWithRole('reporter');
    }

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeLocalMedia(User $reporter, string $path): TicketMedia
    {
        $ticket = $this->makeTicket($reporter);

        return $this->makeLocalMediaForTicket($reporter, $ticket, $path);
    }

    private function makeLocalMediaForTicket(User $reporter, Ticket $ticket, string $path): TicketMedia
    {
        Storage::disk('public')->put($path, $this->minimalJpeg());

        return TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => Storage::disk('public')->url($path),
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);
    }

    private function makeSupabaseMedia(User $reporter, string $host = 'test.supabase.co'): TicketMedia
    {
        $ticket = $this->makeTicket($reporter);

        return TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://'.$host.'/storage/v1/object/public/tickets/ticket-evidence/test/img.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);
    }

    private function makeVisibleMediaRecord(): TicketMedia
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        return TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://demo.incidex.test/e/placeholder.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);
    }

    private function makeTicket(
        User $reporter,
        bool $communityVisible = true,
        string $state = Ticket::STATE_OPEN,
    ): Ticket {
        return Ticket::create([
            'title' => 'Ticket thumbnail test '.Str::random(6),
            'description' => 'Test description.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => $communityVisible,
        ]);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Aula Thumb '.Str::random(4),
            'building' => 'Edificio T',
            'floor' => '1',
            'room_code' => 'TH-'.Str::upper(Str::random(5)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de thumbnail cache',
        ]);
    }

    private function configureSupabase(string $host = 'test.supabase.co'): void
    {
        config([
            'community.media.remote_allowed_hosts' => [$host],
            'community.media.supabase_public_bucket' => 'tickets',
        ]);
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
