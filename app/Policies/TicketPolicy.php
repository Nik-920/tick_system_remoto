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
