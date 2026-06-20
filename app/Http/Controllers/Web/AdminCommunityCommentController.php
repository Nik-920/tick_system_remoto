<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\HideCommunityCommentRequest;
use App\Models\CommunityComment;
use Illuminate\Http\RedirectResponse;

class AdminCommunityCommentController extends Controller
{
    /**
     * PATCH /admin/community/comments/{comment}/hide
     * Admin/SuperAdmin hides a community comment from the reporter feed.
     */
    public function hide(HideCommunityCommentRequest $request, CommunityComment $comment): RedirectResponse
    {
        abort_unless($comment->isVisible(), 422);

        $comment->update([
            'status' => CommunityComment::STATUS_HIDDEN,
            'hidden_by' => $request->user()?->id,
            'hidden_at' => now(),
            'hidden_reason' => $request->validated('reason'),
        ]);

        return redirect()->back()
            ->with('status', 'Comentario ocultado correctamente.');
    }

    /**
     * PATCH /admin/community/comments/{comment}/restore
     * Admin/SuperAdmin restores a hidden community comment.
     */
    public function restore(CommunityComment $comment): RedirectResponse
    {
        abort_unless($comment->isHidden(), 422);

        $comment->update([
            'status' => CommunityComment::STATUS_VISIBLE,
            'hidden_by' => null,
            'hidden_at' => null,
            'hidden_reason' => null,
        ]);

        return redirect()->back()
            ->with('status', 'Comentario restaurado correctamente.');
    }
}
