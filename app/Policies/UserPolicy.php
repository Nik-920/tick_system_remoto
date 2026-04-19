<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function view(User $user, User $managedUser): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(User $user, User $managedUser): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(User $user, User $managedUser): bool
    {
        if (! $this->isSuperAdmin($user)) {
            return false;
        }

        return $user->id !== $managedUser->id;
    }

    public function assignRole(User $user, User $managedUser): bool
    {
        return $this->isSuperAdmin($user);
    }

    private function isSuperAdmin(User $user): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole('super_admin');
    }
}
