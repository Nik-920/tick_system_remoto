<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Community\CommunityCategoryIconFetcher;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Category Icon Privacy Proxy — reporter-only endpoint.
 *
 * Serves category icon images through a controlled Laravel endpoint instead of
 * exposing raw Supabase Storage URLs in the community feed HTML.
 *
 * Gates enforced on every request:
 *   1. Authenticated user (outer auth middleware).
 *   2. Role:reporter (route middleware).
 *   3. Category icon must be an https URL on the allowlisted Supabase host,
 *      pointing to the categories bucket.
 *
 * Returns 404 for every gate failure; no distinguishing messages are exposed.
 */
class CommunityCategoryIconController extends Controller
{
    public function __invoke(
        Category $category,
        CommunityCategoryIconFetcher $fetcher,
    ): StreamedResponse {
        $icon = trim((string) ($category->icon ?? ''));

        if ($icon === '') {
            abort(404);
        }

        if (! str_starts_with($icon, 'http://') && ! str_starts_with($icon, 'https://')) {
            abort(404);
        }

        $payload = $fetcher->fetch($icon);

        if ($payload === null) {
            abort(404);
        }

        $maxAge = (int) config('community.category_icons.cache_max_age', 3600);
        $contentType = $payload->contentType;
        $body = $payload->bytes;

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
