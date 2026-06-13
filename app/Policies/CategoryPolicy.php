<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Lectura de catálogo: abierta a cualquier usuario autenticado.
     *
     * Divergencia Web/API INTENCIONAL: la API expone el listado de categorías
     * a reporter/maintenance (lo necesitan para crear tickets desde clientes),
     * mientras que el módulo Web de gestión restringe el index a admin/super_admin
     * usando la ability `create`. La gestión (create/update/delete) sí es admin+.
     */
    public function viewAny(User $user): bool
    {
        return $user->id !== '';
    }

    public function view(User $user, Category $category): bool
    {
        return $user->id !== '';
    }

    public function create(User $user): bool
    {
        return $this->hasAnyRole($user, ['admin', 'super_admin']);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->hasAnyRole($user, ['admin', 'super_admin']);
    }

    public function delete(User $user, Category $category): bool
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
}
