<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Lógica compartida de gestión de roles de usuario.
 *
 * Centraliza las invariantes de seguridad de super_admin (antes duplicadas
 * byte a byte entre Web\UserController y Api\UserController), de forma que
 * ambas superficies usen la MISMA fuente de verdad y no puedan divergir.
 */
class UserRoleService
{
    /**
     * Asigna un único rol al usuario (reemplaza cualquier rol previo).
     */
    public function assignSingleRole(User $user, string $role): void
    {
        Role::findOrCreate($role, 'web');
        $user->syncRoles([$role]);
        $user->load('roles');
    }

    /**
     * Un super_admin no puede quitarse a sí mismo el rol super_admin.
     */
    public function isSelfDemotion(User $actor, User $managedUser, string $newRole): bool
    {
        if ($actor->id !== $managedUser->id) {
            return false;
        }

        return $newRole !== 'super_admin';
    }

    /**
     * Cambiar el rol dejaría al sistema sin ningún super_admin.
     */
    public function wouldRemoveLastSuperAdminByDemotion(User $managedUser, string $newRole): bool
    {
        if (! $managedUser->hasRole('super_admin')) {
            return false;
        }

        if ($newRole === 'super_admin') {
            return false;
        }

        return $this->countSuperAdmins() <= 1;
    }

    /**
     * Eliminar al usuario dejaría al sistema sin ningún super_admin.
     */
    public function wouldRemoveLastSuperAdminByDeletion(User $managedUser): bool
    {
        if (! $managedUser->hasRole('super_admin')) {
            return false;
        }

        return $this->countSuperAdmins() <= 1;
    }

    public function countSuperAdmins(): int
    {
        return Role::findOrCreate('super_admin', 'web')->users()->count();
    }

    /**
     * Combina el mensaje base con el resultado de la sincronización Supabase.
     *
     * @param  array{status:string,message:string,role:string}  $syncResult
     */
    public function statusMessage(string $baseMessage, array $syncResult): string
    {
        if ($syncResult['status'] === 'synced') {
            return $baseMessage;
        }

        return $baseMessage.' '.$syncResult['message'];
    }
}
