<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CommunitySave;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunitySaveController extends Controller
{
    /**
     * POST /reporter/community/tickets/{ticket}/save
     * Save (or idempotently ignore) a community-visible ticket.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
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

        return redirect()->back()->withFragment('ticket-'.$ticket->id);
    }

    /**
     * DELETE /reporter/community/tickets/{ticket}/save
     * Remove the current user's save.
     */
    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        $userId = (string) $request->user()?->id;

        CommunitySave::where('ticket_id', $ticket->id)
            ->where('user_id', $userId)
            ->delete();

        return redirect()->back()->withFragment('ticket-'.$ticket->id);
    }
}
