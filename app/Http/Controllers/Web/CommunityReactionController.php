<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityReactionRequest;
use App\Models\CommunityReaction;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityReactionController extends Controller
{
    /**
     * POST /reporter/community/tickets/{ticket}/reactions
     * Add (or idempotently ignore) a reaction to a community-visible ticket.
     */
    public function store(StoreCommunityReactionRequest $request, Ticket $ticket): RedirectResponse|JsonResponse
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
        $type = (string) $request->validated('type');

        CommunityReaction::firstOrCreate([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'type' => $type,
        ]);

        if ($request->expectsJson()) {
            return response()->json($this->reactionPayload($ticket, $type, true));
        }

        return redirect()->back()->withFragment('ticket-'.$ticket->id);
    }

    /**
     * DELETE /reporter/community/tickets/{ticket}/reactions/{type}
     * Remove the current user's reaction of a given type.
     */
    public function destroy(Request $request, Ticket $ticket, string $type): RedirectResponse|JsonResponse
    {
        abort_unless(in_array($type, CommunityReaction::allowedTypes(), true), 404);

        $userId = (string) $request->user()?->id;

        CommunityReaction::where('ticket_id', $ticket->id)
            ->where('user_id', $userId)
            ->where('type', $type)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json($this->reactionPayload($ticket, $type, false));
        }

        return redirect()->back()->withFragment('ticket-'.$ticket->id);
    }

    /** @return array<string, mixed> */
    private function reactionPayload(Ticket $ticket, string $type, bool $active): array
    {
        $counts = [];
        foreach (CommunityReaction::allowedTypes() as $t) {
            $counts[$t] = $ticket->communityReactions()->where('type', $t)->count();
        }

        return [
            'ok' => true,
            'ticket_id' => $ticket->id,
            'kind' => 'reaction',
            'type' => $type,
            'active' => $active,
            'count' => $counts[$type],
            'counts' => $counts,
        ];
    }
}
