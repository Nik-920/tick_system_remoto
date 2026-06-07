<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->id !== '';
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($this->hasAnyRole($user, ['admin', 'super_admin'])) {
            return true;
        }

        if ($this->hasRole($user, 'maintenance')) {
            // maintenance can only see tickets assigned to them
            // or tickets that are open and available to claim.
            return $ticket->assigned_to === $user->id
                || ($ticket->state === Ticket::STATE_OPEN && $ticket->assigned_to === null);
        }

        return $ticket->reporter_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->id !== '';
    }

    /**
     * A reporter may EDIT their own request, but ONLY while it is still open and
     * untouched by maintenance. The moment it is assigned, locked, or moves to
     * in_progress/resolved/rejected it belongs to the operational flow and must
     * stay immutable for the reporter (to protect audit, state_history and
     * assignments).
     *
     * No HTTP route invokes 'update' on a Ticket today and there is no edit
     * feature for admin/super_admin yet, so this ability is scoped to the
     * reporter rule. A future phase can widen it without breaking any caller.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $this->reporterCanModifyOwnRequest($user, $ticket);
    }

    /**
     * A reporter may CANCEL (voluntarily withdraw) their own request under the
     * SAME window as edit. Cancellation is NOT rejection: "rejected" means
     * maintenance/admin reviewed it and decided it does not proceed; "cancelled"
     * means the reporter retired their own request before anyone started
     * attending it. The domain has no `cancelled` state yet, so this ability
     * only gates the UI affordance for now (no mutation, no hard delete).
     */
    public function cancelAsReporter(User $user, Ticket $ticket): bool
    {
        return $this->reporterCanModifyOwnRequest($user, $ticket);
    }

    /**
     * Shared window for reporter edit/cancel: own + open + unassigned + unlocked.
     */
    private function reporterCanModifyOwnRequest(User $user, Ticket $ticket): bool
    {
        return $this->hasRole($user, 'reporter')
            && $ticket->reporter_id === $user->id
            && $ticket->state === Ticket::STATE_OPEN
            && $ticket->assigned_to === null
            && ! $ticket->assignment_locked;
    }

    public function updateState(User $user, Ticket $ticket): bool
    {
        // Resolved tickets: only super_admin can reopen them.
        if ($ticket->state === 'resolved') {
            return $this->hasRole($user, 'super_admin');
        }

        // Rejected tickets: only admin/super_admin can reopen them.
        if ($ticket->state === 'rejected') {
            return $this->hasAnyRole($user, ['admin', 'super_admin']);
        }

        // Admin/super_admin can transition any open or in_progress ticket.
        if ($this->hasAnyRole($user, ['admin', 'super_admin'])) {
            return true;
        }

        // maintenance can only change state of tickets assigned to them.
        // A ticket must be claimed first via claim() before state can change.
        if ($this->hasRole($user, 'maintenance')) {
            return $ticket->assigned_to === $user->id;
        }

        return false;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        unset($ticket);

        return $this->canDeleteTicket($user);
    }

    /**
     * Ability to confirm or dismiss a duplicate detected by the AI.
     * Allowed roles: maintenance, admin, super_admin.
     */
    public function reviewDuplicate(User $user, Ticket $ticket): bool
    {
        return $this->hasAnyRole($user, ['maintenance', 'admin', 'super_admin']);
    }

    public function claim(User $user, Ticket $ticket): bool
    {
        return $this->hasRole($user, 'maintenance')
            && $ticket->state === Ticket::STATE_OPEN
            && $ticket->assigned_to === null
            && ! $ticket->assignment_locked;
    }

    public function release(User $user, Ticket $ticket): bool
    {
        return $this->hasRole($user, 'maintenance')
            && $ticket->state === Ticket::STATE_OPEN
            && $ticket->assigned_to === $user->id
            && ! $ticket->assignment_locked
            && $ticket->assignment_source === Ticket::ASSIGNMENT_SOURCE_SELF;
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        unset($ticket);

        return $this->canAssignTicket($user);
    }

    public function unassign(User $user, Ticket $ticket): bool
    {
        unset($ticket);

        return $this->canUnassignTicket($user);
    }

    private function canDeleteTicket(User $user): bool
    {
        return $this->hasAnyRole($user, ['admin', 'super_admin']);
    }

    private function canAssignTicket(User $user): bool
    {
        return $this->hasAnyRole($user, ['admin', 'super_admin']);
    }

    private function canUnassignTicket(User $user): bool
    {
        return $this->hasAnyRole($user, ['admin', 'super_admin']);
    }

    /**
     * @param  list<string>  $roles
     */
    private function hasAnyRole(User $user, array $roles): bool
    {
        if (! method_exists($user, 'hasAnyRole')) {
            return false;
        }

        return $user->hasAnyRole($roles);
    }

    private function hasRole(User $user, string $role): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole($role);
    }
}
