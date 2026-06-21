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
     *
     * When parent_id is present the comment is stored as a one-level reply.
     * Replies to a non-matching, hidden/deleted, or already-nested parent are
     * rejected with 404 so moderation state is never revealed.
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

        $parentId = $this->resolveParentId($request->validated('parent_id'), $ticket);

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'parent_id' => $parentId,
            'user_id' => $request->user()?->id,
            'body' => $request->validated('body'),
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        $this->communityNotifications->notifyTicketReporterOfComment($comment);

        return redirect()->back()
            ->withFragment('ticket-'.$ticket->id)
            ->with('status', $parentId === null
                ? 'Tu comentario fue publicado.'
                : 'Tu respuesta fue publicada.');
    }

    /**
     * Validate an optional reply target. Returns the parent id when the reply
     * is allowed, null for a root comment, and aborts 404 otherwise so hidden,
     * deleted, cross-ticket or already-nested parents are indistinguishable.
     */
    private function resolveParentId(?string $parentId, Ticket $ticket): ?string
    {
        if ($parentId === null) {
            return null;
        }

        /** @var CommunityComment|null $parent */
        $parent = CommunityComment::query()->find($parentId);

        abort_unless(
            $parent !== null
                && (string) $parent->ticket_id === (string) $ticket->id
                && $parent->canReceiveReply(),
            404
        );

        return $parent->id;
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
