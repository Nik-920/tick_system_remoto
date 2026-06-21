<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TicketMedia;
use App\Services\Community\CommunityMediaAccessService;
use App\Services\Community\CommunityMediaThumbnailService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Community Media Privacy Proxy — reporter-only thumbnail endpoint.
 *
 * Visibility gates enforced on every request (even for cached thumbnails):
 *   1. Authenticated reporter (middleware).
 *   2. Ticket community_visible=true.
 *   3. Ticket state in {open, in_progress, resolved}.
 *   4. Media file_type starts with "image".
 *
 * Thumbnail generation:
 *   - Local files (http://localhost/storage/...) are processed directly.
 *   - Supabase files are fetched via CommunityRemoteMediaFetcher (allowlist-only).
 *   - Result is a cached JPEG stored on the configured thumbnail disk.
 *
 * Returns 404 for every gate failure; no distinguishing messages are exposed.
 */
class CommunityMediaController extends Controller
{
    public function __invoke(
        TicketMedia $media,
        CommunityMediaAccessService $accessService,
        CommunityMediaThumbnailService $thumbnailService,
    ): StreamedResponse {
        $media->loadMissing('ticket');

        if (! $accessService->canViewInCommunity($media)) {
            abort(404);
        }

        if (! $accessService->isPreviewable($media)) {
            abort(404);
        }

        $thumbnail = $thumbnailService->thumbnailFor($media);
        if ($thumbnail === null) {
            abort(404);
        }

        $bytes = Storage::disk($thumbnail->disk)->get($thumbnail->path);
        if (! is_string($bytes) || $bytes === '') {
            abort(404);
        }

        $maxAge = (int) config('community.media.cache_max_age', 300);
        $contentType = $thumbnail->contentType;
        $body = $bytes;

        return response()->stream(
            static function () use ($body): void {
                echo $body;
            },
            200,
            [
                'Content-Type' => $contentType,
                'Cache-Control' => 'private, max-age='.$maxAge,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Disposition' => 'inline',
            ],
        );
    }
}
