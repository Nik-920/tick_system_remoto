<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\TicketNotificationPreference;
use App\Models\User;

class TicketNotificationPreferenceService
{
    /**
     * Returns true when the user wants to receive the given notification type on the given channel.
     * Missing preference defaults to enabled for backward compatibility.
     */
    public function isEnabled(User $user, string $type, string $channel): bool
    {
        $pref = TicketNotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('channel', $channel)
            ->first();

        return $pref === null || $pref->enabled;
    }

    public function set(User $user, string $type, string $channel, bool $enabled): void
    {
        TicketNotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'type' => $type, 'channel' => $channel],
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
            $types[] = TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN;
        }

        if ($user->hasRole('reporter')) {
            $types[] = TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER;
            $types[] = TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER;
            $types[] = TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER;
        }

        if ($user->hasRole('maintenance')) {
            $types[] = TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE;
            $types[] = TicketNotificationPreference::TYPE_TICKET_UNASSIGNED_ASSIGNEE;
            $types[] = TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE;
            $types[] = TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE;
            $types[] = TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE;
        }

        return array_values(array_unique($types));
    }

    /**
     * Preferences filtered to only the types applicable to the user, with per-channel state.
     *
     * @return array<string, array{label: string, channels: array<string, bool>}>
     */
    public function applicablePreferencesFor(User $user): array
    {
        $types = $this->applicableTypesFor($user);

        if ($types === []) {
            return [];
        }

        $labels = $this->labels();

        $stored = TicketNotificationPreference::query()
            ->where('user_id', $user->id)
            ->whereIn('type', $types)
            ->get()
            ->groupBy('type');

        $result = [];
        foreach ($types as $type) {
            $typeChannels = $this->channelsFor();
            $channelPrefs = [];

            foreach ($typeChannels as $channel) {
                $row = $stored->get($type)?->firstWhere('channel', $channel);
                $channelPrefs[$channel] = $row === null ? true : (bool) $row->enabled;
            }

            $result[$type] = [
                'label' => $labels[$type] ?? $type,
                'channels' => $channelPrefs,
            ];
        }

        return $result;
    }

    /** @return array<string, string> */
    public function labels(): array
    {
        return [
            TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN => 'Tickets creados — avisarme cuando se cree un nuevo ticket',
            TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE => 'Tickets asignados a mí — avisarme cuando me asignen un ticket',
            TicketNotificationPreference::TYPE_TICKET_UNASSIGNED_ASSIGNEE => 'Tickets desasignados — avisarme cuando me retiren un ticket asignado',
            TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER => 'Cambios de estado de mis tickets — avisarme cuando cambien de estado',
            TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE => 'Cambios de estado asignado — avisarme cuando cambie el estado de un ticket que tengo asignado',
            TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER => 'Evidencias en mis tickets — avisarme cuando alguien adjunte evidencia en mi ticket',
            TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE => 'Evidencias en tickets asignados — avisarme cuando alguien adjunte evidencia en un ticket asignado a mí',
            TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER => 'Comentarios en mis tickets — avisarme cuando alguien comente en mi ticket',
            TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE => 'Comentarios en tickets asignados — avisarme cuando alguien comente en un ticket asignado a mí',
        ];
    }

    /** @return list<string> */
    public function channels(): array
    {
        return [
            TicketNotificationPreference::CHANNEL_IN_APP,
            TicketNotificationPreference::CHANNEL_FCM,
            TicketNotificationPreference::CHANNEL_EMAIL,
        ];
    }

    /**
     * Channels applicable for a given notification type.
     *
     * @return list<string>
     */
    private function channelsFor(): array
    {
        return $this->channels();
    }
}
