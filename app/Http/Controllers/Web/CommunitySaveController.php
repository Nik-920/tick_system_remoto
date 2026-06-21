<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CommunitySave;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunitySaveController extends Controller
{
    /**
     * POST /reporter/community/tickets/{ticket}/save
     * Save (or idempotently ignore) a community-visible ticket.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
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

        CommunitySave::firstOrCreate([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
        ]);

        if ($request->expectsJson()) {
            return response()->json($this->savePayload($ticket, true));
        }

        return redirect()->back()->withFragment('ticket-'.$ticket->id);
    }

    /**
     * DELETE /reporter/community/tickets/{ticket}/save
     * Remove the current user's save.
     */
    public function destroy(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        $userId = (string) $request->user()?->id;

        CommunitySave::where('ticket_id', $ticket->id)
            ->where('user_id', $userId)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json($this->savePayload($ticket, false));
        }

        return redirect()->back()->withFragment('ticket-'.$ticket->id);
    }

    /** @return array<string, mixed> */
    private function savePayload(Ticket $ticket, bool $active): array
    {
        return [
            'ok' => true,
            'ticket_id' => $ticket->id,
            'kind' => 'save',
            'active' => $active,
            'count' => $ticket->communitySaves()->count(),
        ];
    }
}
