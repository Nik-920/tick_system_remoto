<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TicketMedia;
use App\Services\Community\CommunityMediaAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Community Media Privacy Proxy — reporter-only thumbnail/preview endpoint.
 *
 * Serves media files from the public Storage disk only when all of the
 * following gates pass at request time:
 *   - The viewer is authenticated (auth middleware on route).
 *   - The viewer has the reporter role (role:reporter middleware on route).
 *   - The media belongs to a ticket that is currently visible in the feed.
 *   - The ticket's state is open / in_progress / resolved.
 *   - The media is an image (previewable).
 *   - The file exists on the local public disk.
 *
 * Returns 404 for every failed gate; no distinguishing error messages are
 * exposed to avoid information leakage.
 *
 * Production note: files uploaded via TicketMediaStorageService land on
 * Supabase; those files are NOT yet available on the local public disk.
 * In that scenario resolvePath() returns null and the response is 404.
 * This is a known limitation — see "Riesgos pendientes" in the phase notes.
 */
class CommunityMediaController extends Controller
{
    public function __invoke(
        Request $request,
        TicketMedia $media,
        CommunityMediaAccessService $accessService,
    ): StreamedResponse {
        $media->loadMissing('ticket');

        if (! $accessService->canViewInCommunity($media)) {
            abort(404);
        }

        if (! $accessService->isPreviewable($media)) {
            abort(404);
        }

        $path = $accessService->resolvePath($media);
        if ($path === null) {
            abort(404);
        }

        $response = Storage::disk('public')->response($path);
        $response->headers->set('Content-Type', $accessService->contentType($media));
        $response->headers->set('Cache-Control', 'private, max-age=300');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Disposition', 'inline');

        return $response;
    }
}
