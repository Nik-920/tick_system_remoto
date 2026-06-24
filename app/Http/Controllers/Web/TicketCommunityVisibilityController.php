<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\UpdateCommunityVisibilityRequest;
use App\Models\CommunityModerationLog;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketCommunityVisibilityController extends Controller
{
    /**
     * PATCH /tickets/{ticket}/community/hide
     * Admin/SuperAdmin oculta un ticket del feed de Comunidad.
     * No altera el estado operacional del ticket.
     */
    public function hide(UpdateCommunityVisibilityRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('moderateCommunityVisibility', $ticket);

        $previousVisible = (bool) $ticket->community_visible;
        $previousReason = $ticket->community_visibility_reason;
        $reason = $request->validated('reason');
        $performedById = $request->user()?->id;

        DB::transaction(function () use ($ticket, $reason, $performedById, $previousVisible, $previousReason): void {
            $ticket->forceFill([
                'community_visible' => false,
                'community_hidden_at' => now(),
                'community_hidden_by' => $performedById,
                'community_visibility_reason' => $reason,
            ])->save();

            CommunityModerationLog::create([
                'ticket_id' => $ticket->id,
                'action' => CommunityModerationLog::ACTION_HIDDEN,
                'reason' => $reason,
                'performed_by' => $performedById,
                'previous_visible' => $previousVisible,
                'new_visible' => false,
                'previous_reason' => $previousReason,
                'metadata' => ['source' => 'ticket_community_visibility_controller'],
            ]);
        });

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Ticket ocultado de Comunidad correctamente.');
    }

    /**
     * PATCH /tickets/{ticket}/community/restore
     * Admin/SuperAdmin restaura la visibilidad de un ticket en Comunidad.
     * Limpia los metadatos de ocultamiento actuales; el historial persiste en community_moderation_logs.
     */
    public function restore(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('moderateCommunityVisibility', $ticket);

        $previousVisible = (bool) $ticket->community_visible;
        $previousReason = $ticket->community_visibility_reason;
        $performedById = $request->user()?->id;

        DB::transaction(function () use ($ticket, $performedById, $previousVisible, $previousReason): void {
            $ticket->forceFill([
                'community_visible' => true,
                'community_hidden_at' => null,
                'community_hidden_by' => null,
                'community_visibility_reason' => null,
            ])->save();

            CommunityModerationLog::create([
                'ticket_id' => $ticket->id,
                'action' => CommunityModerationLog::ACTION_RESTORED,
                'reason' => null,
                'performed_by' => $performedById,
                'previous_visible' => $previousVisible,
                'new_visible' => true,
                'previous_reason' => $previousReason,
                'metadata' => ['source' => 'ticket_community_visibility_controller'],
            ]);
        });

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Ticket restaurado en Comunidad correctamente.');
    }
}
