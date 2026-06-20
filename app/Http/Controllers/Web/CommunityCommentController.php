<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityCommentRequest;
use App\Http\Requests\Community\UpdateCommunityCommentRequest;
use App\Models\CommunityComment;
use App\Models\CommunityCommentEditLog;
use App\Models\Ticket;
use App\Services\Community\CommunityNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

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
     * PATCH /reporter/community/comments/{comment}
     * Reporter edits their own visible comment on a still-visible ticket.
     */
    public function update(UpdateCommunityCommentRequest $request, CommunityComment $comment): RedirectResponse
    {
        $userId = (string) $request->user()?->id;

        abort_unless((string) $comment->user_id === $userId, 403);

        abort_unless($comment->isVisible(), 404);

        $ticket = $comment->ticket;
        abort_unless(
            $ticket->community_visible && in_array($ticket->state, [
                Ticket::STATE_OPEN,
                Ticket::STATE_IN_PROGRESS,
                Ticket::STATE_RESOLVED,
            ], true),
            404
        );

        $previousBody = $comment->body;
        $newBody = $request->validated('body');

        // No-op: identical content (ignoring surrounding whitespace) does not
        // touch edited_at nor create an audit log.
        if (trim((string) $newBody) === trim((string) $previousBody)) {
            return redirect()->back()
                ->withFragment('ticket-'.$comment->ticket_id)
                ->with('status', 'No hay cambios en tu comentario.');
        }

        DB::transaction(function () use ($comment, $newBody, $previousBody, $request): void {
            $comment->forceFill([
                'body' => $newBody,
                'edited_at' => now(),
            ])->save();

            CommunityCommentEditLog::create([
                'comment_id' => $comment->id,
                'ticket_id' => $comment->ticket_id,
                'edited_by' => $request->user()?->id,
                'previous_body' => $previousBody,
                'new_body' => $newBody,
                'metadata' => ['source' => 'community_comment_controller'],
            ]);
        });

        return redirect()->back()
            ->withFragment('ticket-'.$comment->ticket_id)
            ->with('status', 'Tu comentario fue actualizado.');
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
