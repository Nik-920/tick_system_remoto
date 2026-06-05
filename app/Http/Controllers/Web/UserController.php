<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserAvatarRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\User;
use App\Queries\Users\UserListQuery;
use App\Services\Auth\SupabaseRoleSyncService;
use App\Services\Storage\UserAvatarStorageService;
use App\Services\Users\UserRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserRoleService $userRoles) {}

    public function index(ListUsersRequest $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validated();

        $users = UserListQuery::build($filters)
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
        $this->userRoles->assignSingleRole($user, $role);

        if ($request->hasFile('avatar_file')) {
            $avatarUrl = $avatarStorageService->replaceAvatar($user, $request->file('avatar_file'));
            $user->forceFill(['avatar_url' => $avatarUrl])->save();
        }

        $syncResult = $roleSyncService->syncUserRole($user, $role);

        return redirect()
            ->route('users.edit', $user)
            ->with('status', $this->userRoles->statusMessage('Usuario creado correctamente.', $syncResult));
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
        if ($actor instanceof User && $this->userRoles->isSelfDemotion($actor, $user, $role)) {
            return back()->withErrors([
                'role' => 'No puedes quitarte a ti mismo el rol super_admin.',
            ]);
        }

        if ($this->userRoles->wouldRemoveLastSuperAdminByDemotion($user, $role)) {
            return back()->withErrors([
                'role' => 'No se puede remover el ultimo super_admin del sistema.',
            ]);
        }

        $this->userRoles->assignSingleRole($user, $role);
        $syncResult = $roleSyncService->syncUserRole($user, $role);

        return redirect()
            ->route('users.edit', $user)
            ->with('status', $this->userRoles->statusMessage('Rol de usuario actualizado correctamente.', $syncResult));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($this->userRoles->wouldRemoveLastSuperAdminByDeletion($user)) {
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
     * @return list<string>
     */
    private function availableRoles(): array
    {
        return ['reporter', 'maintenance', 'admin', 'super_admin'];
    }

    private function resolvePrimaryRole(User $user): string
    {
        $role = $user->getRoleNames()->first();

        return is_string($role) && $role !== '' ? $role : 'reporter';
    }
}
