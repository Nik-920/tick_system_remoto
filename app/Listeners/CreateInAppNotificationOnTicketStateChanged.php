<?php

namespace App\Listeners;

use App\Events\TicketStateChanged;
use App\Listeners\Concerns\BuildsTicketStateChangedNotification;
use App\Listeners\Concerns\DeliversTicketInAppNotification;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\NotificationPayload;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persiste la notificación in-app al reporter Y al técnico asignado (maintenance)
 * cuando cambia el estado del ticket.
 *
 * Queue 'default' con 3 intentos: escritura in-app idempotente (Fase 5.2).
 */
class CreateInAppNotificationOnTicketStateChanged implements ShouldQueue
{
    use BuildsTicketStateChangedNotification;
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

    public function handle(TicketStateChanged $event): void
    {
        $prefs = $this->preferences;

        $this->deliverInAppNotification(
            fn (): ?array => $this->buildTicketStateChangedNotification($event),
            'Error creando notificación in-app en cambio de estado.',
            (string) $event->ticket->id,
            $prefs !== null
                ? fn (User $u) => $prefs->isEnabled($u, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP)
                : null,
        );

        $this->deliverInAppNotificationToAssignee($event);
    }

    private function deliverInAppNotificationToAssignee(TicketStateChanged $event): void
    {
        try {
            $assignee = $event->ticket->assignee;

            if (! $assignee instanceof User) {
                return;
            }

            if ($this->preferences !== null && ! $this->preferences->isEnabled($assignee, TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, TicketNotificationPreference::CHANNEL_IN_APP)) {
                return;
            }

            $stateLabels = [
                'open' => '🔔 Reabierto',
                'in_progress' => '🔧 En progreso',
                'resolved' => '✅ Resuelto',
                'rejected' => '❌ Rechazado',
            ];

            $ticket = $event->ticket;
            $label = $stateLabels[$event->toState] ?? '📋 Actualizado';
            $url = route('tickets.show', $ticket);

            $this->notificationService()->notifyAssignee(
                $assignee,
                new NotificationPayload(
                    type: 'ticket_state_changed',
                    title: "{$label}: {$ticket->title}",
                    body: "Un ticket asignado a ti fue actualizado a: {$event->toState}",
                    url: $url,
                    icon: $label,
                    ticketId: (string) $ticket->id,
                    dedupKey: "ticket_state_changed:{$ticket->id}:{$event->fromState}:{$event->toState}",
                ),
                $event->actor,
            );
        } catch (Throwable $e) {
            Log::error('Error creando notificación in-app de cambio de estado para asignado.', [
                'ticket_id' => (string) $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
