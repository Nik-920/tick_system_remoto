<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserAvatarRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\SupabaseRoleSyncService;
use App\Services\Storage\UserAvatarStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validated();
        $query = User::query()->with('roles');

        $this->applyFilters($query, $filters);

        $users = $query
            ->latest('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        $user->load('roles');

        return new UserResource($user);
    }

    public function store(
        StoreUserRequest $request,
        SupabaseRoleSyncService $roleSyncService,
        UserAvatarStorageService $avatarStorageService
    ): JsonResponse {
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

        return response()->json([
            'message' => $this->buildStatusMessage('Usuario creado correctamente.', $syncResult),
            'data' => (new UserResource($user->load('roles')))->resolve($request),
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user->fill($request->validated());
        $user->save();

        return response()->json([
            'message' => 'Perfil de usuario actualizado correctamente.',
            'data' => (new UserResource($user->load('roles')))->resolve($request),
        ]);
    }

    public function updateAvatar(
        UpdateUserAvatarRequest $request,
        User $user,
        UserAvatarStorageService $avatarStorageService
    ): JsonResponse {
        $this->authorize('update', $user);

        $avatarUrl = $avatarStorageService->replaceAvatar($user, $request->file('avatar_file'));
        $user->forceFill(['avatar_url' => $avatarUrl])->save();

        return response()->json([
            'message' => 'Avatar actualizado correctamente.',
            'data' => (new UserResource($user->load('roles')))->resolve($request),
        ]);
    }

    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user,
        SupabaseRoleSyncService $roleSyncService
    ): JsonResponse {
        $this->authorize('assignRole', $user);

        $role = (string) $request->validated('role');

        $actor = $request->user();
        if ($actor instanceof User && $this->isSelfDemotion($actor, $user, $role)) {
            return response()->json([
                'message' => 'No puedes quitarte a ti mismo el rol super_admin.',
            ], 422);
        }

        if ($this->isLastSuperAdminDemotion($user, $role)) {
            return response()->json([
                'message' => 'No se puede remover el ultimo super_admin del sistema.',
            ], 422);
        }

        $this->assignSingleRole($user, $role);
        $syncResult = $roleSyncService->syncUserRole($user, $role);

        return response()->json([
            'message' => $this->buildStatusMessage('Rol de usuario actualizado correctamente.', $syncResult),
            'data' => (new UserResource($user->load('roles')))->resolve($request),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($this->isLastSuperAdmin($user)) {
            return response()->json([
                'message' => 'No se puede eliminar el ultimo super_admin del sistema.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
        ]);
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

    private function assignSingleRole(User $user, string $role): void
    {
        Role::findOrCreate($role, 'web');
        $user->syncRoles([$role]);
        $user->load('roles');
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
