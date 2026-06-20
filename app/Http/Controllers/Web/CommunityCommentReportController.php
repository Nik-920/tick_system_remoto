<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityCommentReportRequest;
use App\Models\CommunityComment;
use App\Models\CommunityReport;
use App\Models\Ticket;
use App\Services\Community\CommunityNotificationService;
use Illuminate\Http\RedirectResponse;

class CommunityCommentReportController extends Controller
{
    public function __construct(private readonly CommunityNotificationService $communityNotifications) {}

    /**
     * POST /reporter/community/comments/{comment}/reports
     * Reporter reports a visible community comment for moderation review.
     * Deduplicates pending reports: one pending per user per comment.
     * Does NOT auto-hide the comment.
     */
    public function store(StoreCommunityCommentReportRequest $request, CommunityComment $comment): RedirectResponse
    {
        // Comment must be visible
        abort_unless($comment->isVisible(), 404);

        // Parent ticket must be community-visible and in a public state
        /** @var Ticket $ticket */
        $ticket = $comment->ticket;
        abort_unless(
            $ticket->community_visible && in_array($ticket->state, [
                Ticket::STATE_OPEN,
                Ticket::STATE_IN_PROGRESS,
                Ticket::STATE_RESOLVED,
            ], true),
            404
        );

        $userId = (string) $request->user()?->id;

        $existingPending = CommunityReport::query()
            ->where('comment_id', $comment->id)
            ->where('reported_by', $userId)
            ->where('status', CommunityReport::STATUS_PENDING)
            ->exists();

        if ($existingPending) {
            return redirect()->back()
                ->withFragment('ticket-'.$ticket->id)
                ->with('warning', 'Ya tienes un reporte pendiente para este comentario.');
        }

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment->id,
            'reported_by' => $userId,
            'reason' => $request->validated('reason'),
            'note' => $request->validated('note'),
            'status' => CommunityReport::STATUS_PENDING,
        ]);

        $this->communityNotifications->notifyAdminsOfReport($report);

        return redirect()->back()
            ->withFragment('ticket-'.$ticket->id)
            ->with('status', 'Tu reporte fue enviado. El equipo de moderación lo revisará.');
    }
}
