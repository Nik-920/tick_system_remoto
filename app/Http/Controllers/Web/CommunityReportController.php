<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityReportRequest;
use App\Models\CommunityReport;
use App\Models\Ticket;
use App\Services\Community\CommunityNotificationService;
use Illuminate\Http\RedirectResponse;

class CommunityReportController extends Controller
{
    public function __construct(private readonly CommunityNotificationService $communityNotifications) {}

    /**
     * POST /reporter/community/tickets/{ticket}/reports
     * Reporter reports a community-visible ticket for moderation review.
     * Deduplicates pending reports: one pending report per user per ticket.
     */
    public function store(StoreCommunityReportRequest $request, Ticket $ticket): RedirectResponse
    {
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
            ->where('ticket_id', $ticket->id)
            ->where('reported_by', $userId)
            ->where('status', CommunityReport::STATUS_PENDING)
            ->exists();

        if ($existingPending) {
            return redirect()->back()
                ->withFragment('ticket-'.$ticket->id)
                ->with('warning', 'Ya tienes un reporte pendiente para esta publicación.');
        }

        $report = CommunityReport::create([
            'ticket_id' => $ticket->id,
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
