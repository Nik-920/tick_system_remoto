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
        if ($this->hasAnyRole($user, ['maintenance', 'admin', 'super_admin'])) {
            return true;
        }

        return $ticket->reporter_id === $user->id || $ticket->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->id !== '';
    }

    public function updateState(User $user, Ticket $ticket): bool
    {
        if ($ticket->state === 'resolved') {
            return $this->hasRole($user, 'super_admin');
        }

        if ($ticket->state === 'rejected') {
            return $this->hasAnyRole($user, ['admin', 'super_admin']);
        }

        return $this->hasAnyRole($user, ['maintenance', 'admin', 'super_admin']);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $this->hasAnyRole($user, ['admin', 'super_admin']);
    }

    /**
     * @param list<string> $roles
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
