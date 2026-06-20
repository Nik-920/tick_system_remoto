<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\ReviewCommunityReportRequest;
use App\Models\CommunityReport;
use App\Services\Community\CommunityNotificationService;
use Illuminate\Http\RedirectResponse;

class AdminCommunityReportController extends Controller
{
    public function __construct(private readonly CommunityNotificationService $communityNotifications) {}

    /**
     * PATCH /admin/community/reports/{report}
     * Admin/SuperAdmin marks a community report as resolved or dismissed.
     * Does NOT automatically hide the ticket — admin uses existing hide action for that.
     */
    public function review(ReviewCommunityReportRequest $request, CommunityReport $report): RedirectResponse
    {
        abort_unless($report->status === CommunityReport::STATUS_PENDING, 422);

        $report->update([
            'status' => $request->validated('status'),
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'resolution_note' => $request->validated('resolution_note'),
        ]);

        $this->communityNotifications->notifyReporterOfReportReview($report);

        return redirect()->back()
            ->with('status', 'Reporte actualizado correctamente.');
    }
}
