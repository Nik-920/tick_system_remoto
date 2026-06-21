<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\CommunityNotificationPreference;
use App\Models\User;

class CommunityNotificationPreferenceService
{
    /**
     * Returns true when the user wants to receive the given notification type.
     * If no preference record exists the default is enabled (true).
     */
    public function enabled(User|string $user, string $type): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        $preference = CommunityNotificationPreference::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->first();

        return $preference === null || $preference->enabled;
    }

    public function set(User $user, string $type, bool $enabled): void
    {
        CommunityNotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'type' => $type],
            ['enabled' => $enabled],
        );
    }

    /**
     * Types relevant to the given user based on their role(s).
     *
     * @return list<string>
     */
    public function applicableTypesFor(User $user): array
    {
        $types = [];

        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            $types[] = CommunityNotificationPreference::TYPE_REPORT_CREATED;
        }

        if ($user->hasRole('reporter')) {
            $types[] = CommunityNotificationPreference::TYPE_REPORT_REVIEWED;
            $types[] = CommunityNotificationPreference::TYPE_COMMENT_CREATED;
            $types[] = CommunityNotificationPreference::TYPE_DIGEST_WEEKLY;
        }

        return $types;
    }

    /**
     * Returns preferences filtered to only the types applicable to the user.
     *
     * @return array<string, array{enabled: bool, label: string}>
     */
    public function applicablePreferencesFor(User $user): array
    {
        $applicableTypes = $this->applicableTypesFor($user);

        if ($applicableTypes === []) {
            return [];
        }

        $labels = $this->labels();

        $stored = CommunityNotificationPreference::query()
            ->where('user_id', $user->id)
            ->whereIn('type', $applicableTypes)
            ->get()
            ->keyBy('type');

        $result = [];
        foreach ($applicableTypes as $type) {
            $result[$type] = [
                'enabled' => $stored->has($type) ? (bool) $stored[$type]->enabled : true,
                'label' => $labels[$type] ?? $type,
            ];
        }

        return $result;
    }

    /** @return array<string, string> */
    public function labels(): array
    {
        return [
            CommunityNotificationPreference::TYPE_REPORT_CREATED => 'Avisarme cuando haya nuevos reportes de Comunidad pendientes de revisar',
            CommunityNotificationPreference::TYPE_REPORT_REVIEWED => 'Avisarme cuando un reporte que envié sea revisado',
            CommunityNotificationPreference::TYPE_COMMENT_CREATED => 'Avisarme cuando alguien comente en mis reportes públicos',
            CommunityNotificationPreference::TYPE_DIGEST_WEEKLY => 'Avisarme con un resumen semanal de Comunidad',
        ];
    }
}
