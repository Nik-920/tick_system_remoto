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
use App\Queries\Users\UserListQuery;
use App\Services\Auth\SupabaseRoleSyncService;
use App\Services\Storage\UserAvatarStorageService;
use App\Services\Users\UserRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private readonly UserRoleService $userRoles) {}

    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validated();

        $users = UserListQuery::build($filters)
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
        $this->userRoles->assignSingleRole($user, $role);

        if ($request->hasFile('avatar_file')) {
            $avatarUrl = $avatarStorageService->replaceAvatar($user, $request->file('avatar_file'));
            $user->forceFill(['avatar_url' => $avatarUrl])->save();
        }

        $syncResult = $roleSyncService->syncUserRole($user, $role);

        return response()->json([
            'message' => $this->userRoles->statusMessage('Usuario creado correctamente.', $syncResult),
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
        if ($actor instanceof User && $this->userRoles->isSelfDemotion($actor, $user, $role)) {
            return response()->json([
                'message' => 'No puedes quitarte a ti mismo el rol super_admin.',
            ], 422);
        }

        if ($this->userRoles->wouldRemoveLastSuperAdminByDemotion($user, $role)) {
            return response()->json([
                'message' => 'No se puede remover el ultimo super_admin del sistema.',
            ], 422);
        }

        $this->userRoles->assignSingleRole($user, $role);
        $syncResult = $roleSyncService->syncUserRole($user, $role);

        return response()->json([
            'message' => $this->userRoles->statusMessage('Rol de usuario actualizado correctamente.', $syncResult),
            'data' => (new UserResource($user->load('roles')))->resolve($request),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($this->userRoles->wouldRemoveLastSuperAdminByDeletion($user)) {
            return response()->json([
                'message' => 'No se puede eliminar el ultimo super_admin del sistema.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }
}
