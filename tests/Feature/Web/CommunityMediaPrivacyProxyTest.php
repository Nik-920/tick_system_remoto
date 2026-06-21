<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Community Media Privacy Proxy — access, response, no-leak, and regression tests.
 *
 * Security contract:
 *   - Only authenticated reporters can call the proxy endpoint.
 *   - The ticket must be community_visible=true and in an allowed state.
 *   - Admin-hidden, cancelled, and rejected tickets must not serve media.
 *   - The community feed HTML must not expose raw file_url or storage paths.
 */
class CommunityMediaPrivacyProxyTest extends TestCase
{
    use RefreshDatabase;

    // ── Access ───────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_community_media_proxy(): void
    {
        $media = $this->makeVisibleMedia();

        $this->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertRedirect(route('login'));
    }

    public function test_reporter_can_access_media_for_visible_community_ticket(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $media = $this->makeVisibleMedia($reporter, path: 'ticket-evidence/test/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();
    }

    public function test_reporter_cannot_access_media_for_community_hidden_ticket(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeTicket($reporter, communityVisible: false);
        $media = $this->makeMediaForTicket($ticket, $reporter, 'ticket-evidence/hidden/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_reporter_cannot_access_media_after_admin_hides_ticket_from_community(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeTicket($reporter, communityVisible: true);
        $media = $this->makeMediaForTicket($ticket, $reporter, 'ticket-evidence/unhide/img.jpg');

        // Simulate admin hiding the ticket
        $ticket->forceFill(['community_visible' => false])->save();

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_reporter_cannot_access_media_for_cancelled_ticket(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeTicket($reporter, state: Ticket::STATE_CANCELLED);
        $media = $this->makeMediaForTicket($ticket, $reporter, 'ticket-evidence/cancelled/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_reporter_cannot_access_media_for_rejected_ticket(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeTicket($reporter, state: Ticket::STATE_REJECTED);
        $media = $this->makeMediaForTicket($ticket, $reporter, 'ticket-evidence/rejected/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_reporter_cannot_access_media_for_community_invisible_ticket(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeTicket($reporter, communityVisible: false, state: Ticket::STATE_OPEN);
        $media = $this->makeMediaForTicket($ticket, $reporter, 'ticket-evidence/invisible/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_maintenance_cannot_access_reporter_community_media_route(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $media = $this->makeVisibleMedia();

        $this->actingAs($maintenance)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertForbidden();
    }

    public function test_admin_cannot_access_reporter_community_media_route(): void
    {
        $admin = $this->createUserWithRole('admin');
        $media = $this->makeVisibleMedia();

        $this->actingAs($admin)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertForbidden();
    }

    // ── Response ─────────────────────────────────────────────────────────────

    public function test_allowed_image_returns_200(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $media = $this->makeVisibleMedia($reporter, path: 'ticket-evidence/resp/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertOk();
    }

    public function test_response_has_image_content_type(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $media = $this->makeVisibleMedia($reporter, path: 'ticket-evidence/ctype/img.jpg', fileType: 'image/png');

        // Thumbnails are always served as JPEG regardless of the original file type.
        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_response_has_nosniff_header(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $media = $this->makeVisibleMedia($reporter, path: 'ticket-evidence/nosniff/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_response_has_private_cache_header(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $media = $this->makeVisibleMedia($reporter, path: 'ticket-evidence/cache/img.jpg');

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertHeader('Cache-Control', 'max-age=300, private');
    }

    public function test_missing_file_returns_404(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        // Media record exists in DB but the actual file is absent from disk
        $ticket = $this->makeTicket($reporter);
        $media = TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'http://localhost/storage/ticket-evidence/ghost/nofile.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    public function test_non_previewable_file_returns_404(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $path = 'ticket-evidence/doc/file.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 fake bytes');
        $ticket = $this->makeTicket($reporter);
        $media = TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => Storage::disk('public')->url($path),
            'file_type' => 'document',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $media->id))
            ->assertNotFound();
    }

    // ── Feed no-leak ─────────────────────────────────────────────────────────

    public function test_community_feed_uses_proxy_url_for_thumbnails(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeTicket($reporter);
        $media = TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://demo.incidex.test/evidencias/silla.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee(route('reporter.community.media.thumbnail', $media->id), false);
    }

    public function test_community_feed_does_not_contain_raw_file_url(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeTicket($reporter);
        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://demo.incidex.test/evidencias/raw-leak.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertDontSee('https://demo.incidex.test/evidencias/raw-leak.jpg', false);
    }

    public function test_community_feed_does_not_contain_storage_internal_path(): void
    {
        Storage::fake('public');
        $reporter = $this->createUserWithRole('reporter');
        $path = 'ticket-evidence/internal/secret.jpg';
        Storage::disk('public')->put($path, 'imgdata');
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

    public function test_community_feed_shows_media_placeholder_when_no_images(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeTicket($reporter);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('comm-post__thumb-placeholder', false);
    }

    public function test_has_media_filter_still_works_with_proxied_thumbnails(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeTicket($reporter, title: 'Ticket con imagen proxied XYZ');
        TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://demo.incidex.test/e/test.jpg',
            'file_type' => 'image',
            'uploaded_by' => $reporter->id,
        ]);
        $this->makeTicket($reporter, title: 'Ticket sin imagen ABC999');

        $this->actingAs($reporter)
            ->get(route('reporter.community').'?has_media=1')
            ->assertOk()
            ->assertSee('Ticket con imagen proxied XYZ', false)
            ->assertDontSee('Ticket sin imagen ABC999', false);
    }

    // ── Regression ───────────────────────────────────────────────────────────

    public function test_community_reactions_not_affected(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $ticket = $this->makeTicket($reporter);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Me interesa', false)
            ->assertSee('También me pasa', false)
            ->assertSee('Lo vi', false);
    }

    public function test_community_saves_not_affected(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeTicket($reporter);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Guardar', false);
    }

    public function test_community_comments_not_affected(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $this->makeTicket($reporter);

        $this->actingAs($reporter)
            ->get(route('reporter.community'))
            ->assertOk()
            ->assertSee('Comentarios', false);
    }

    public function test_proxy_route_is_named_correctly(): void
    {
        $this->assertTrue(
            collect(app('router')->getRoutes())->contains(
                fn ($route) => $route->getName() === 'reporter.community.media.thumbnail'
            )
        );
    }

    public function test_non_existent_media_id_returns_404(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $fakeUuid = Str::uuid()->toString();

        $this->actingAs($reporter)
            ->get(route('reporter.community.media.thumbnail', $fakeUuid))
            ->assertNotFound();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Creates a TicketMedia backed by a real file on the fake public disk.
     * If $path is provided, the file is put there and the file_url is local.
     * Uses a minimal valid JPEG so the thumbnail service can process the file.
     */
    private function makeVisibleMedia(
        ?User $reporter = null,
        ?string $path = null,
        string $fileType = 'image',
    ): TicketMedia {
        $reporter ??= $this->createUserWithRole('reporter');
        $ticket = $this->makeTicket($reporter);

        if ($path !== null) {
            Storage::disk('public')->put($path, $this->minimalJpeg());
            $fileUrl = Storage::disk('public')->url($path);
        } else {
            $fileUrl = 'https://demo.incidex.test/evidencias/placeholder.jpg';
        }

        return TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => $fileUrl,
            'file_type' => $fileType,
            'uploaded_by' => $reporter->id,
        ]);
    }

    private function makeMediaForTicket(Ticket $ticket, User $uploader, string $path): TicketMedia
    {
        Storage::disk('public')->put($path, $this->minimalJpeg());

        return TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => Storage::disk('public')->url($path),
            'file_type' => 'image',
            'uploaded_by' => $uploader->id,
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

    private function makeTicket(
        User $reporter,
        bool $communityVisible = true,
        string $state = Ticket::STATE_OPEN,
        string $title = '',
    ): Ticket {
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        return Ticket::create([
            'title' => $title !== '' ? $title : 'Ticket comunidad '.Str::random(6),
            'description' => 'Descripción del reporte público.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => $state,
            'priority' => 'medium',
            'community_visible' => $communityVisible,
        ]);
    }

    private function createUserWithRole(string $role): User
    {
        $this->ensureRolesExist();

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Aula Media Test',
            'building' => 'Edificio B',
            'floor' => '2',
            'room_code' => 'MT-'.Str::upper(Str::random(5)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Categoría para tests de proxy de media',
        ]);
    }
}
