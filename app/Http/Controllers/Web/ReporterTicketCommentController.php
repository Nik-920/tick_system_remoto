<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CommunityComment;
use App\Models\Ticket;
use App\Support\Community\CommentAuthorPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * JSON endpoints for the reporter "Mis tickets" comments modal.
 *
 * Security contract:
 *  - Ownership check is applied first and unconditionally (reporter_id = auth user).
 *    A foreign ticket returns 404 — indistinguishable from non-existent (no-leak).
 *  - Only STATUS_VISIBLE comments and replies are returned. Hidden/deleted rows
 *    never leave the server, and no field that could reveal moderation state
 *    (hidden_reason, previous_body, new_body) is included in the response.
 *  - Author identity mirrors the community feed (see CommentAuthorPresenter):
 *    "Tú" for the viewer's own comments, otherwise the commenter's display
 *    name + role badge. Email and internal IDs are never exposed.
 *  - Comment creation mirrors the community rules (community_visible + allowed
 *    state) so the two surfaces stay consistent.
 */
final class ReporterTicketCommentController extends Controller
{
    /** States in which a community-visible ticket accepts new comments. */
    private const ALLOWED_STATES = [
        Ticket::STATE_OPEN,
        Ticket::STATE_IN_PROGRESS,
        Ticket::STATE_RESOLVED,
    ];

    /**
     * GET /reporter/tickets/{ticket}/comments
     * Returns visible root comments + visible replies, sanitized for the modal.
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $userId = (string) $request->user()?->id;

        if ((string) $ticket->reporter_id !== $userId) {
            abort(404);
        }

        $comments = CommunityComment::query()
            ->where('ticket_id', $ticket->id)
            ->whereNull('parent_id')
            ->where('status', CommunityComment::STATUS_VISIBLE)
            ->with([
                'replies' => fn ($q) => $q
                    ->where('status', CommunityComment::STATUS_VISIBLE)
                    ->oldest('created_at'),
            ])
            ->oldest('created_at')
            ->get(['id', 'parent_id', 'user_id', 'body', 'edited_at', 'created_at']);

        $userDataMap = CommentAuthorPresenter::loadUserData($this->collectCommentUserIds($comments));

        $shaped = $comments
            ->map(fn (CommunityComment $c) => $this->shapeComment($c, $userId, $userDataMap))
            ->all();

        return response()->json([
            'ok' => true,
            'ticket_id' => (string) $ticket->id,
            'ticket_ref' => $this->ref($ticket),
            'count' => count($shaped),
            'can_comment' => $this->canComment($ticket),
            'comments' => $shaped,
        ]);
    }

    /**
     * POST /reporter/tickets/{ticket}/comments
     * Creates a root comment on an owned ticket, subject to community rules.
     * Returns 201 JSON on success, 422 on business-rule or validation failure.
     */
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $userId = (string) $request->user()?->id;

        if ((string) $ticket->reporter_id !== $userId) {
            abort(404);
        }

        if (! $this->canComment($ticket)) {
            return response()->json([
                'ok' => false,
                'message' => 'Los comentarios no están disponibles para este ticket.',
            ], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:500'],
        ], [
            'body.required' => 'El comentario no puede estar vacío.',
            'body.min' => 'El comentario debe tener al menos 2 caracteres.',
            'body.max' => 'El comentario no puede superar los 500 caracteres.',
        ]);

        $comment = CommunityComment::create([
            'ticket_id' => $ticket->id,
            'parent_id' => null,
            'user_id' => $userId,
            'body' => $validated['body'],
            'status' => CommunityComment::STATUS_VISIBLE,
        ]);

        return response()->json([
            'ok' => true,
            'comment' => $this->shapeComment($comment, $userId),
        ], 201);
    }

    /**
     * Sanitize one comment for the JSON response.
     * No PII, no moderation fields, no edit-log content.
     *
     * @param  array<string, array{name: string, last_name: string, role: string}>  $userDataMap
     * @return array<string, mixed>
     */
    private function shapeComment(CommunityComment $comment, string $viewerId, array $userDataMap = []): array
    {
        $uid = $comment->user_id !== null ? (string) $comment->user_id : null;
        $isOwner = $uid !== null && $uid === $viewerId;
        $userData = $uid !== null ? ($userDataMap[$uid] ?? null) : null;

        $roleTone = CommentAuthorPresenter::roleTone($userData);
        $author = CommentAuthorPresenter::resolve($userData, $isOwner);

        $replies = [];
        if ($comment->relationLoaded('replies')) {
            $replies = $comment->replies
                ->map(fn (CommunityComment $r) => $this->shapeComment($r, $viewerId, $userDataMap))
                ->all();
        }

        return [
            'id' => (string) $comment->id,
            'author_label' => $author['label'],
            'author_initials' => $author['initials'],
            'role_tone' => $roleTone,
            'role_label' => CommentAuthorPresenter::roleLabel($roleTone),
            'body' => (string) $comment->body,
            'created_at_label' => $comment->created_at?->diffForHumans() ?? '—',
            'edited' => $comment->wasEdited(),
            'owned_by_viewer' => $isOwner,
            'replies' => $replies,
        ];
    }

    /**
     * Gather every distinct commenter id across root comments + their loaded
     * replies, for a single batched CommentAuthorPresenter::loadUserData() call.
     *
     * @param  Collection<int, CommunityComment>  $comments
     * @return list<string>
     */
    private function collectCommentUserIds(Collection $comments): array
    {
        $ids = [];
        foreach ($comments as $comment) {
            if ($comment->user_id !== null) {
                $ids[] = (string) $comment->user_id;
            }
            if ($comment->relationLoaded('replies')) {
                foreach ($comment->replies as $reply) {
                    if ($reply->user_id !== null) {
                        $ids[] = (string) $reply->user_id;
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function canComment(Ticket $ticket): bool
    {
        return (bool) $ticket->community_visible
            && in_array((string) $ticket->state, self::ALLOWED_STATES, true);
    }

    private function ref(Ticket $ticket): string
    {
        return '#'.strtoupper(substr((string) $ticket->id, 0, 8));
    }
}
