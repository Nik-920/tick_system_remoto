<?php

namespace App\Listeners;

use App\Events\TicketAssigned;
use App\Listeners\Concerns\BuildsTicketAssignedNotification;
use App\Listeners\Concerns\DeliversTicketInAppNotification;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Persiste la notificación in-app al destinatario de una asignación.
 *
 * Queue 'default' con 3 intentos: escritura in-app idempotente (Fase 5.2).
 */
class CreateInAppNotificationOnTicketAssigned implements ShouldQueue
{
    use BuildsTicketAssignedNotification;
    use DeliversTicketInAppNotification;
    use InteractsWithQueue;

    public string $queue = 'default';

    public int $tries = 3;

    public bool $afterCommit = true;

    public function __construct(
        private NotificationService $notificationService,
        private ?TicketNotificationPreferenceService $preferences = null,
    ) {}

    protected function notificationService(): NotificationService
    {
        return $this->notificationService;
    }

    public function handle(TicketAssigned $event): void
    {
        $prefType = $event->action === 'unassigned'
            ? TicketNotificationPreference::TYPE_TICKET_UNASSIGNED_ASSIGNEE
            : TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE;

        $prefs = $this->preferences;

        $this->deliverInAppNotification(
            fn (): ?array => $this->buildTicketAssignedNotification($event),
            'Error creando notificación in-app de asignación.',
            (string) $event->ticket->id,
            $prefs !== null
                ? fn (User $user) => $prefs->isEnabled($user, $prefType, TicketNotificationPreference::CHANNEL_IN_APP)
                : null,
        );
    }
}
