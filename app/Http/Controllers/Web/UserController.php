<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserAvatarRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\User;
use App\Services\Auth\SupabaseRoleSyncService;
use App\Services\Storage\UserAvatarStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(ListUsersRequest $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validated();
        $query = User::query()->with('roles');

        $this->applyFilters($query, $filters);

        $users = $query
            ->latest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'filters' => $filters,
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function store(
        StoreUserRequest $request,
        SupabaseRoleSyncService $roleSyncService,
        UserAvatarStorageService $avatarStorageService
    ): RedirectResponse {
        $this->authorize('create', User::class);

        $data = $request->validated();

        $user = User::query()->create([
            'name' => (string) $data['name'],
            'last_name' => (string) $data['last_name'],
            'email' => (string) $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => (string) $data['password'],
        ]);

        $role = (string) $data['role'];
        $this->assignSingleRole($user, $role);

        if ($request->hasFile('avatar_file')) {
            $avatarUrl = $avatarStorageService->replaceAvatar($user, $request->file('avatar_file'));
            $user->forceFill(['avatar_url' => $avatarUrl])->save();
        }

        $syncResult = $roleSyncService->syncUserRole($user, $role);

        return redirect()
            ->route('users.edit', $user)
            ->with('status', $this->buildStatusMessage('Usuario creado correctamente.', $syncResult));
    }

    public function edit(User $user): View
    {
        $this->authorize('view', $user);

        $user->load('roles');

        return view('users.edit', [
            'managedUser' => $user,
            'availableRoles' => $this->availableRoles(),
            'currentRole' => $this->resolvePrimaryRole($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->fill($request->validated());
        $user->save();

        return redirect()
            ->route('users.edit', $user)
            ->with('status', 'Perfil de usuario actualizado correctamente.');
    }

    public function updateAvatar(
        UpdateUserAvatarRequest $request,
        User $user,
        UserAvatarStorageService $avatarStorageService
    ): RedirectResponse {
        $this->authorize('update', $user);

        $avatarUrl = $avatarStorageService->replaceAvatar($user, $request->file('avatar_file'));
        $user->forceFill(['avatar_url' => $avatarUrl])->save();

        return redirect()
            ->route('users.edit', $user)
            ->with('status', 'Avatar actualizado correctamente.');
    }

    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user,
        SupabaseRoleSyncService $roleSyncService
    ): RedirectResponse {
        $this->authorize('assignRole', $user);

        $role = (string) $request->validated('role');

        $actor = $request->user();
        if ($actor instanceof User && $this->isSelfDemotion($actor, $user, $role)) {
            return back()->withErrors([
                'role' => 'No puedes quitarte a ti mismo el rol super_admin.',
            ]);
        }

        if ($this->isLastSuperAdminDemotion($user, $role)) {
            return back()->withErrors([
                'role' => 'No se puede remover el ultimo super_admin del sistema.',
            ]);
        }

        $this->assignSingleRole($user, $role);
        $syncResult = $roleSyncService->syncUserRole($user, $role);

        return redirect()
            ->route('users.edit', $user)
            ->with('status', $this->buildStatusMessage('Rol de usuario actualizado correctamente.', $syncResult));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($this->isLastSuperAdmin($user)) {
            return back()->withErrors([
                'delete' => 'No se puede eliminar el ultimo super_admin del sistema.',
            ]);
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('status', 'Usuario eliminado correctamente.');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $role = trim((string) $filters['role']);
            $query->whereHas('roles', function (Builder $innerQuery) use ($role): void {
                $innerQuery->where('name', $role);
            });
        }
    }

    /**
     * @return list<string>
     */
    private function availableRoles(): array
    {
        return ['reporter', 'maintenance', 'admin', 'super_admin'];
    }

    private function assignSingleRole(User $user, string $role): void
    {
        Role::findOrCreate($role, 'web');
        $user->syncRoles([$role]);
        $user->load('roles');
    }

    private function resolvePrimaryRole(User $user): string
    {
        $role = $user->getRoleNames()->first();

        return is_string($role) && $role !== '' ? $role : 'reporter';
    }

    /**
     * @param  array{status:string,message:string,role:string}  $syncResult
     */
    private function buildStatusMessage(string $baseMessage, array $syncResult): string
    {
        if ($syncResult['status'] === 'synced') {
            return $baseMessage;
        }

        return $baseMessage.' '.$syncResult['message'];
    }

    private function isSelfDemotion(User $actor, User $managedUser, string $newRole): bool
    {
        if ($actor->id !== $managedUser->id) {
            return false;
        }

        return $newRole !== 'super_admin';
    }

    private function isLastSuperAdminDemotion(User $managedUser, string $newRole): bool
    {
        if (! $managedUser->hasRole('super_admin')) {
            return false;
        }

        if ($newRole === 'super_admin') {
            return false;
        }

        return $this->countSuperAdmins() <= 1;
    }

    private function isLastSuperAdmin(User $managedUser): bool
    {
        if (! $managedUser->hasRole('super_admin')) {
            return false;
        }

        return $this->countSuperAdmins() <= 1;
    }

    private function countSuperAdmins(): int
    {
        $role = Role::findOrCreate('super_admin', 'web');

        return $role->users()->count();
    }
}
