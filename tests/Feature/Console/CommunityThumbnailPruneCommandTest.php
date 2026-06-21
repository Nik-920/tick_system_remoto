<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use App\Services\Community\CommunityMediaThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

/**
 * community:thumbnails:prune — prunes stale/orphaned community thumbnail cache files.
 *
 * Strategy for "older than" in fake storage:
 *   Storage::fake() files always have lastModified ≈ time() (now).
 *   --older-than=0  → cutoff = now()  → all files qualify (lte comparison).
 *   --older-than=99999 → cutoff = 99999 days ago → no file qualifies (too new).
 */
class CommunityThumbnailPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    // ── 1. Dry-run ────────────────────────────────────────────────────────────

    public function test_dry_run_reports_files_but_deletes_nothing(): void
    {
        Storage::fake('public');

        $mediaId = (string) Str::uuid();
        $this->putThumbnail($mediaId, 'thumb_old_640x360.jpg');

        $this->artisan('community:thumbnails:prune', [
            '--dry-run' => true,
            '--older-than' => 0,
            '--prune-hidden' => true,
        ])
            ->assertExitCode(Command::SUCCESS);

        // File still exists — dry-run never deletes
        $this->assertTrue(
            Storage::disk('public')->exists("community-thumbnails/{$mediaId}/thumb_old_640x360.jpg")
        );
    }

    public function test_dry_run_output_contains_would_delete_label(): void
    {
        Storage::fake('public');

        $mediaId = (string) Str::uuid();
        $this->putThumbnail($mediaId, 'thumb_old_640x360.jpg');

        $this->artisan('community:thumbnails:prune', [
            '--dry-run' => true,
            '--older-than' => 0,
        ])
            ->expectsOutputToContain('dry-run')
            ->expectsOutputToContain('Would delete')
            ->assertExitCode(Command::SUCCESS);
    }

    // ── 2. Orphan — media_id not in DB ────────────────────────────────────────

    public function test_orphan_thumbnail_older_than_threshold_is_deleted(): void
    {
        Storage::fake('public');

        $mediaId = (string) Str::uuid(); // no TicketMedia record
        $this->putThumbnail($mediaId, 'thumb_orphan_640x360.jpg');

        $this->artisan('community:thumbnails:prune', [
            '--older-than' => 0,
            '--limit' => 1000,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertFalse(
            Storage::disk('public')->exists("community-thumbnails/{$mediaId}/thumb_orphan_640x360.jpg")
        );
    }

    public function test_orphan_thumbnail_newer_than_threshold_is_skipped(): void
    {
        Storage::fake('public');

        $mediaId = (string) Str::uuid();
        $this->putThumbnail($mediaId, 'thumb_fresh_640x360.jpg');

        $this->artisan('community:thumbnails:prune', [
            '--older-than' => 99999,
            '--limit' => 1000,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertTrue(
            Storage::disk('public')->exists("community-thumbnails/{$mediaId}/thumb_fresh_640x360.jpg")
        );
    }

    // ── 3. Hidden/non-public tickets with --prune-hidden ─────────────────────

    public function test_hidden_ticket_thumbnail_deleted_with_prune_hidden(): void
    {
        Storage::fake('public');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter, ['community_visible' => false]);
        $media = $this->makeMedia($ticket);

        $this->putThumbnail((string) $media->id, 'thumb_hidden_640x360.jpg');

        $this->artisan('community:thumbnails:prune', [
            '--older-than' => 99999, // age doesn't matter for hidden
            '--prune-hidden' => true,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertFalse(
            Storage::disk('public')->exists("community-thumbnails/{$media->id}/thumb_hidden_640x360.jpg")
        );
    }

    public function test_hidden_ticket_current_thumbnail_skipped_without_prune_hidden(): void
    {
        Storage::fake('public');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter, ['community_visible' => false]);
        $media = $this->makeMedia($ticket);

        // Put the CURRENT expected path for this media so it's not treated as stale
        $service = app(CommunityMediaThumbnailService::class);
        $currentPath = $service->cachePathFor($media);
        Storage::disk('public')->put($currentPath, 'fake-jpeg');

        $this->artisan('community:thumbnails:prune', [
            '--older-than' => 0,
        ])->assertExitCode(Command::SUCCESS);

        // Without --prune-hidden the hidden-ticket branch is skipped; the file
        // matches the current expected hash so the stale branch also skips it.
        $this->assertTrue(Storage::disk('public')->exists($currentPath));
    }

    public function test_cancelled_ticket_thumbnail_deleted_with_prune_hidden(): void
    {
        Storage::fake('public');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter, [
            'state' => Ticket::STATE_CANCELLED,
            'community_visible' => true,
        ]);
        $media = $this->makeMedia($ticket);

        $this->putThumbnail((string) $media->id, 'thumb_cancelled_640x360.jpg');

        $this->artisan('community:thumbnails:prune', [
            '--older-than' => 99999,
            '--prune-hidden' => true,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertFalse(
            Storage::disk('public')->exists("community-thumbnails/{$media->id}/thumb_cancelled_640x360.jpg")
        );
    }

    public function test_rejected_ticket_thumbnail_deleted_with_prune_hidden(): void
    {
        Storage::fake('public');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter, [
            'state' => Ticket::STATE_REJECTED,
            'community_visible' => true,
        ]);
        $media = $this->makeMedia($ticket);

        $this->putThumbnail((string) $media->id, 'thumb_rejected_640x360.jpg');

        $this->artisan('community:thumbnails:prune', [
            '--older-than' => 99999,
            '--prune-hidden' => true,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertFalse(
            Storage::disk('public')->exists("community-thumbnails/{$media->id}/thumb_rejected_640x360.jpg")
        );
    }

    // ── 4. Visible ticket — current thumbnail is preserved ───────────────────

    public function test_current_thumbnail_for_visible_ticket_is_not_deleted(): void
    {
        Storage::fake('public');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeMedia($ticket);

        // Put the CURRENT expected path
        $service = app(CommunityMediaThumbnailService::class);
        $currentPath = $service->cachePathFor($media);
        Storage::disk('public')->put($currentPath, 'fake-jpeg');

        $this->artisan('community:thumbnails:prune', [
            '--older-than' => 0,
            '--prune-hidden' => true,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertTrue(Storage::disk('public')->exists($currentPath));
    }

    // ── 5. Stale hash — obsolete file deleted, current preserved ─────────────

    public function test_stale_hash_thumbnail_deleted_when_current_path_differs(): void
    {
        Storage::fake('public');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $media = $this->makeMedia($ticket);

        $service = app(CommunityMediaThumbnailService::class);
        $currentPath = $service->cachePathFor($media);

        // Put a stale file (wrong hash) and the current file
        $stalePath = "community-thumbnails/{$media->id}/thumb_stale_old_640x360.jpg";
        Storage::disk('public')->put($stalePath, 'stale-data');
        Storage::disk('public')->put($currentPath, 'current-data');

        $this->artisan('community:thumbnails:prune', [
            '--older-than' => 0,
        ])->assertExitCode(Command::SUCCESS);

        $this->assertFalse(Storage::disk('public')->exists($stalePath));
        $this->assertTrue(Storage::disk('public')->exists($currentPath));
    }

    // ── 6. --limit stops after N deletions ───────────────────────────────────

    public function test_limit_stops_deletions_after_n_files(): void
    {
        Storage::fake('public');

        // Create 5 orphan directories (each with 1 file)
        for ($i = 0; $i < 5; $i++) {
            $this->putThumbnail((string) Str::uuid(), 'thumb_abc_640x360.jpg');
        }

        $this->artisan('community:thumbnails:prune', [
            '--older-than' => 0,
            '--limit' => 2,
        ])->assertExitCode(Command::SUCCESS);

        // Only 2 of the 5 orphan files should be deleted
        $remaining = count(Storage::disk('public')->allFiles('community-thumbnails'));
        $this->assertSame(3, $remaining);
    }

    // ── 7. Unsafe path fails with FAILURE exit code ───────────────────────────

    public function test_unsafe_configured_path_exits_failure_and_deletes_nothing(): void
    {
        Storage::fake('public');

        $mediaId = (string) Str::uuid();
        $this->putThumbnail($mediaId, 'thumb_safe_640x360.jpg');

        config(['community.media.thumbnail_path' => '../dangerous']);

        $this->artisan('community:thumbnails:prune', ['--older-than' => 0])
            ->expectsOutputToContain('Unsafe')
            ->assertExitCode(Command::FAILURE);

        // Nothing deleted
        $this->assertTrue(
            Storage::disk('public')->exists("community-thumbnails/{$mediaId}/thumb_safe_640x360.jpg")
        );
    }

    public function test_empty_path_config_exits_failure(): void
    {
        Storage::fake('public');
        config(['community.media.thumbnail_path' => '']);

        $this->artisan('community:thumbnails:prune', ['--older-than' => 0])
            ->expectsOutputToContain('Unsafe')
            ->assertExitCode(Command::FAILURE);
    }

    // ── 8. Output / stats ─────────────────────────────────────────────────────

    public function test_output_contains_scanned_and_deleted_counts(): void
    {
        Storage::fake('public');

        $mediaId = (string) Str::uuid();
        $this->putThumbnail($mediaId, 'thumb_orphan_640x360.jpg');

        $this->artisan('community:thumbnails:prune', ['--older-than' => 0])
            ->expectsOutputToContain('Scanned')
            ->expectsOutputToContain('Deleted')
            ->expectsOutputToContain('Skipped')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_exits_success_on_empty_directory(): void
    {
        Storage::fake('public');

        $this->artisan('community:thumbnails:prune', ['--older-than' => 0])
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_exits_success_for_dry_run_with_files(): void
    {
        Storage::fake('public');

        $this->putThumbnail((string) Str::uuid(), 'thumb_any_640x360.jpg');

        $this->artisan('community:thumbnails:prune', [
            '--dry-run' => true,
            '--older-than' => 0,
        ])->assertExitCode(Command::SUCCESS);
    }

    // ── 9. No PII in output ───────────────────────────────────────────────────

    public function test_output_does_not_contain_reporter_email(): void
    {
        Storage::fake('public');

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter, ['community_visible' => false]);
        $media = $this->makeMedia($ticket);
        $this->putThumbnail((string) $media->id, 'thumb_hidden_640x360.jpg');

        Artisan::call('community:thumbnails:prune', [
            '--older-than' => 0,
            '--prune-hidden' => true,
        ]);

        $this->assertStringNotContainsString($reporter->email, Artisan::output());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function putThumbnail(string $mediaId, string $filename): void
    {
        $base = (string) config('community.media.thumbnail_path', 'community-thumbnails');
        Storage::disk('public')->put("{$base}/{$mediaId}/{$filename}", 'fake-jpeg-data');
    }

    private function makeReporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeTicket(User $reporter, array $attrs = []): Ticket
    {
        return Ticket::create(array_merge([
            'title' => 'Thumb prune test '.Str::random(6),
            'description' => 'Test description.',
            'reporter_id' => $reporter->id,
            'location_id' => $this->makeLocation()->id,
            'category_id' => $this->makeCategory()->id,
            'state' => Ticket::STATE_OPEN,
            'priority' => 'medium',
            'community_visible' => true,
        ], $attrs));
    }

    private function makeMedia(Ticket $ticket): TicketMedia
    {
        return TicketMedia::create([
            'ticket_id' => $ticket->id,
            'file_url' => 'https://test.supabase.co/storage/v1/object/public/tickets/evidence/test.jpg',
            'file_type' => 'image/jpeg',
            'uploaded_by' => $ticket->reporter_id,
        ]);
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Aula Prune '.Str::random(4),
            'building' => 'Edificio P',
            'floor' => '1',
            'room_code' => 'PR-'.Str::upper(Str::random(5)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat-'.Str::lower(Str::random(6)),
            'icon' => 'wrench',
            'description' => 'Category for prune tests',
        ]);
    }
}
