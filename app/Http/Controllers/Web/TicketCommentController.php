<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Events\TicketCommentCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\StoreTicketCommentRequest;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final class TicketCommentController extends Controller
{
    /**
     * POST /tickets/{ticket}/comments
     *
     * Stores a private core comment. Authorization via TicketPolicy@comment.
     * Dispatches TicketCommentCreated after the transaction commits so listeners
     * receive a fully persisted comment.
     */
    public function store(StoreTicketCommentRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('comment', $ticket);

        /** @var User $actor */
        $actor = $request->user();

        $comment = DB::transaction(static function () use ($request, $ticket, $actor): TicketComment {
            return TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $actor->id,
                'body' => $request->validated('body'),
            ]);
        });

        TicketCommentCreated::dispatch($ticket, $actor, $comment);

        return redirect()->back()->with('status', 'Comentario agregado.');
    }
}
