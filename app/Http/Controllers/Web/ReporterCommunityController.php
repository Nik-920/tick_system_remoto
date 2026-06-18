<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Queries\Community\CommunityFeedQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Comunidad del campus — reporter-only social feed.
 *
 * Phase 1: read-only feed from real ticket data.
 * Social actions (reactions, saves, comments) are placeholders for future phases.
 */
class ReporterCommunityController extends Controller
{
    public function __invoke(Request $request, CommunityFeedQuery $query): View
    {
        $feed = $query->forReporter($request->user(), $request->query());

        return view('reporter.community', compact('feed'));
    }
}
