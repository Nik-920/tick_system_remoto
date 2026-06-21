<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Ticket;
use App\Models\TicketMedia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Prunes stale and orphaned thumbnail cache files from community-thumbnails/.
 *
 * Rules (applied in priority order per media directory):
 *  1. Orphan   — media_id not found in DB → delete if older than threshold.
 *  2. Hidden   — ticket no longer publicly visible → delete if --prune-hidden.
 *  3. Stale    — not the current expected hash for this media → delete if older than threshold.
 *  4. Current  — the expected cache path for this media → always skip.
 *
 * Safety:
 *  - Only touches files inside the configured thumbnail_path prefix.
 *  - Throws RuntimeException when path config is unsafe (caller shows FAILURE).
 *  - Respects --limit: stops deletions (but not scanning) once limit is reached.
 *  - --dry-run never deletes.
 */
final class CommunityThumbnailPruner
{
    /** @var list<string> */
    private const COMMUNITY_VISIBLE_STATES = [
        Ticket::STATE_OPEN,
        Ticket::STATE_IN_PROGRESS,
        Ticket::STATE_RESOLVED,
    ];

    public function __construct(
        private readonly CommunityMediaThumbnailService $thumbnailService,
    ) {}

    /**
     * @param  array{dry_run: bool, older_than: int, limit: int, prune_hidden: bool}  $options
     * @return array{scanned: int, deleted: int, skipped: int, orphans: int, hidden: int, stale: int, dry_run: bool}
     *
     * @throws RuntimeException when the configured thumbnail path is unsafe
     */
    public function prune(array $options): array
    {
        $disk = (string) config('community.media.thumbnail_disk', 'public');
        $base = (string) config('community.media.thumbnail_path', 'community-thumbnails');

        $this->assertSafePath($base);

        $stats = [
            'scanned' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'orphans' => 0,
            'hidden' => 0,
            'stale' => 0,
            'dry_run' => $options['dry_run'],
        ];

        $cutoff = Carbon::now()->subDays($options['older_than']);

        $directories = Storage::disk($disk)->directories($base);

        foreach ($directories as $dir) {
            if ($stats['deleted'] >= $options['limit']) {
                break;
            }

            $mediaId = basename($dir);
            $files = Storage::disk($disk)->files($dir);
            $stats['scanned'] += count($files);

            $media = TicketMedia::find($mediaId);

            if ($media === null) {
                foreach ($files as $file) {
                    if ($stats['deleted'] >= $options['limit']) {
                        break;
                    }

                    if ($this->isOlderThan($disk, $file, $cutoff)) {
                        if (! $options['dry_run']) {
                            Storage::disk($disk)->delete($file);
                        }
                        $stats['orphans']++;
                        $stats['deleted']++;
                    } else {
                        $stats['skipped']++;
                    }
                }

                continue;
            }

            $ticket = $media->ticket;

            if ($options['prune_hidden'] && ! $this->isPubliclyVisible($ticket)) {
                foreach ($files as $file) {
                    if ($stats['deleted'] >= $options['limit']) {
                        break;
                    }

                    if (! $options['dry_run']) {
                        Storage::disk($disk)->delete($file);
                    }
                    $stats['hidden']++;
                    $stats['deleted']++;
                }

                continue;
            }

            $currentPath = $this->thumbnailService->cachePathFor($media);

            foreach ($files as $file) {
                if ($stats['deleted'] >= $options['limit']) {
                    break;
                }

                if ($file === $currentPath) {
                    $stats['skipped']++;

                    continue;
                }

                if ($this->isOlderThan($disk, $file, $cutoff)) {
                    if (! $options['dry_run']) {
                        Storage::disk($disk)->delete($file);
                    }
                    $stats['stale']++;
                    $stats['deleted']++;
                } else {
                    $stats['skipped']++;
                }
            }
        }

        return $stats;
    }

    private function assertSafePath(string $base): void
    {
        if ($base === '' || str_contains($base, '..')) {
            throw new RuntimeException('Unsafe thumbnail path configuration.');
        }

        if ($base !== 'community-thumbnails' && ! str_starts_with($base, 'community-thumbnails/')) {
            throw new RuntimeException('Unsafe thumbnail path configuration.');
        }
    }

    private function isPubliclyVisible(?Ticket $ticket): bool
    {
        if ($ticket === null) {
            return false;
        }

        return (bool) $ticket->community_visible
            && in_array((string) $ticket->state, self::COMMUNITY_VISIBLE_STATES, true);
    }

    private function isOlderThan(string $disk, string $file, Carbon $cutoff): bool
    {
        try {
            $lastModified = Storage::disk($disk)->lastModified($file);

            return Carbon::createFromTimestamp($lastModified)->lte($cutoff);
        } catch (\Throwable) {
            return false;
        }
    }
}
