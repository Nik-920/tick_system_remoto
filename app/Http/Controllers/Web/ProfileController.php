<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateUserAvatarRequest;
use App\Models\User;
use App\Services\Storage\UserAvatarStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return view('profile.edit', [
            'profileUser' => $user,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $user->fill($request->validated());
        $user->save();

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Perfil actualizado correctamente.');
    }

    public function updateAvatar(
        UpdateUserAvatarRequest $request,
        UserAvatarStorageService $avatarStorageService
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $avatarUrl = $avatarStorageService->replaceAvatar($user, $request->file('avatar_file'));
        $user->forceFill(['avatar_url' => $avatarUrl])->save();

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Avatar actualizado correctamente.');
    }

    public function destroyAvatar(UserAvatarStorageService $avatarStorageService): RedirectResponse
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $avatarStorageService->deleteAvatar($user);

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Avatar eliminado correctamente.');
    }
}
