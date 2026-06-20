<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\UpdateCommunityNotificationPreferencesRequest;
use App\Models\User;
use App\Services\Community\CommunityNotificationPreferenceService;
use Illuminate\Http\RedirectResponse;

class CommunityNotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly CommunityNotificationPreferenceService $preferenceService,
    ) {}

    public function update(UpdateCommunityNotificationPreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $submitted = $request->validated()['preferences'] ?? [];
        $applicableTypes = $this->preferenceService->applicableTypesFor($user);

        foreach ($applicableTypes as $type) {
            $enabled = (bool) ($submitted[$type] ?? false);
            $this->preferenceService->set($user, $type, $enabled);
        }

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Preferencias de notificaciones actualizadas.');
    }
}
