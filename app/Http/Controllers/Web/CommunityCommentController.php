<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityCommentRequest;
use App\Models\CommunityComment;
use App\Models\Ticket;
use App\Services\Community\CommunityNotificationService;
use Illuminate\Http\RedirectResponse;

class CommunityCommentController extends Controller
{
    public function __construct(private readonly CommunityNotificationService $communityNotifications) {}

    /**
     * POST /reporter/community/tickets/{ticket}/comments
     * Reporter adds a comment to a community-visible ticket.
     */
    public function store(StoreCommunityCommentRequest $request, Ticket $ticket): RedirectResponse
    {
        abort_unless(
            $ticket->community_visible && in_array($ticket->state, [
                Ticket::STATE_OPEN,
                Ticket::STATE_IN_PROGRESS,
                Ticket::STATE_RESOLVED,
            ], true),
            404
        );

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()?->id,
            'body' => $request->validated('body'),
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->communityNotifications->notifyTicketReporterOfComment($comment);

        return redirect()->back()
            ->withFragment('ticket-'.$ticket->id)
            ->with('status', 'Tu comentario fue publicado.');
    }

    /**
     * DELETE /reporter/community/comments/{comment}
     * Reporter soft-deletes their own comment (sets status=deleted).
     */
    public function destroy(CommunityComment $comment): RedirectResponse
    {
        $userId = (string) request()->user()?->id;

        abort_unless((string) $comment->user_id === $userId, 403);

        $comment->update([
            'status' => CommunityComment::STATUS_DELETED,
            'deleted_at' => now(),
        ]);

        return redirect()->back()
            ->withFragment('ticket-'.$comment->ticket_id)
            ->with('status', 'Tu comentario fue eliminado.');
    }
}
