<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Queries\Community\CommunityModerationQueueQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityModerationQueueController extends Controller
{
    public function __invoke(Request $request, CommunityModerationQueueQuery $query): View
    {
        $vm = $query->forAdmin($request->user(), $request->query());

        return view('community.admin.moderation.index', compact('vm'));
    }
}
