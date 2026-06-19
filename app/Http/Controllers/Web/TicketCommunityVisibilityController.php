<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\UpdateCommunityVisibilityRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $ticket->forceFill([
            'community_visible' => false,
            'community_hidden_at' => now(),
            'community_hidden_by' => $request->user()?->id,
            'community_visibility_reason' => $request->validated('reason'),
        ])->save();

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Ticket ocultado de Comunidad correctamente.');
    }

    /**
     * PATCH /tickets/{ticket}/community/restore
     * Admin/SuperAdmin restaura la visibilidad de un ticket en Comunidad.
     * Limpia los metadatos de ocultamiento (v1: sin historial de moderación).
     */
    public function restore(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('moderateCommunityVisibility', $ticket);

        unset($request);

        $ticket->forceFill([
            'community_visible' => true,
            'community_hidden_at' => null,
            'community_hidden_by' => null,
            'community_visibility_reason' => null,
        ])->save();

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('status', 'Ticket restaurado en Comunidad correctamente.');
    }
}
