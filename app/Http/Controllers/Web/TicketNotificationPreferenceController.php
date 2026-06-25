<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTicketNotificationPreferencesRequest;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Http\RedirectResponse;

class TicketNotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly TicketNotificationPreferenceService $preferenceService,
    ) {}

    public function update(UpdateTicketNotificationPreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $submitted = $request->validated()['preferences'] ?? [];
        $applicableTypes = $this->preferenceService->applicableTypesFor($user);

        $allChannels = [
            TicketNotificationPreference::CHANNEL_IN_APP,
            TicketNotificationPreference::CHANNEL_FCM,
        ];

        foreach ($applicableTypes as $type) {
            foreach ($allChannels as $channel) {
                $enabled = isset($submitted[$type][$channel]) && $submitted[$type][$channel] === '1';
                $this->preferenceService->set($user, $type, $channel, $enabled);
            }
        }

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Preferencias de notificaciones de tickets actualizadas.');
    }
}
