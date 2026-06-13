<?php

declare(strict_types=1);

namespace App\Queries\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Construye la query base del listado de usuarios (Web y API).
 *
 * Sigue el mismo patrón que TicketIndexQuery: elimina la duplicación de
 * `applyFilters` entre Web\UserController y Api\UserController. No pagina,
 * ni ordena, ni autoriza.
 */
final class UserListQuery
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    public static function build(array $filters): Builder
    {
        $query = User::query()->with('roles');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $inner) use ($search): void {
                $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $role = trim((string) $filters['role']);
            $query->whereHas('roles', function (Builder $inner) use ($role): void {
                $inner->where('name', $role);
            });
        }

        return $query;
    }
}
