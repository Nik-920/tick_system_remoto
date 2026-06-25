<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketEvidenceAdded;
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
 * cuando alguien agrega evidencia a un ticket.
 *
 * Reglas:
 *  - Reporter notificado si actor !== reporter y preferencia habilitada.
 *  - Assignee notificado si actor !== assignee, assignee tiene rol maintenance,
 *    y assignee !== reporter (evita duplicado si son la misma persona).
 *  - Admin no notificado: evidencia no requiere revisión administrativa.
 *
 * Queue 'default' con 3 intentos: escritura in-app idempotente.
 */
class CreateInAppNotificationOnTicketEvidenceAdded implements ShouldQueue
{
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

    public function handle(TicketEvidenceAdded $event): void
    {
        $ticket = $event->ticket;
        $actor = $event->actor;
        $ticketId = (string) $ticket->id;

        $ticket->loadMissing(['reporter', 'assignee']);

        $this->notifyReporter($event, $ticketId);
        $this->notifyAssignee($event, $ticketId);
    }

    private function notifyReporter(TicketEvidenceAdded $event, string $ticketId): void
    {
        $this->deliverInAppNotification(
            function () use ($event): ?array {
                $ticket = $event->ticket;
                $reporter = $ticket->reporter;

                if (! $reporter instanceof User) {
                    return null;
                }

                if ($reporter->id === $event->actor->id) {
                    return null;
                }

                $url = route('tickets.show', $ticket);

                return [
                    'user' => $reporter,
                    'type' => 'ticket_evidence_reporter',
                    'title' => 'Nueva evidencia: '.$ticket->title,
                    'body' => 'Se agregó evidencia a tu ticket.',
                    'url' => $url,
                    'icon' => '📎',
                    'ticketId' => (string) $ticket->id,
                    'dedupKey' => null,
                ];
            },
            'Error creando notificación in-app de evidencia para reporter.',
            $ticketId,
            $this->preferences !== null
                ? fn (User $u) => $this->preferences->isEnabled($u, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP)
                : null,
        );
    }

    private function notifyAssignee(TicketEvidenceAdded $event, string $ticketId): void
    {
        try {
            $ticket = $event->ticket;
            $assignee = $ticket->assignee;

            if (! $assignee instanceof User) {
                return;
            }

            if (! $assignee->hasRole('maintenance')) {
                return;
            }

            if ($assignee->id === $event->actor->id) {
                return;
            }

            $reporter = $ticket->reporter;
            if ($reporter instanceof User && $reporter->id === $assignee->id) {
                return;
            }

            if ($this->preferences !== null && ! $this->preferences->isEnabled($assignee, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, TicketNotificationPreference::CHANNEL_IN_APP)) {
                return;
            }

            $url = route('tickets.show', $ticket);

            $this->notificationService()->notifyUser($assignee, new NotificationPayload(
                type: 'ticket_evidence_assignee',
                title: 'Nueva evidencia: '.$ticket->title,
                body: 'Se agregó evidencia a un ticket asignado a ti.',
                url: $url,
                icon: '📎',
                ticketId: (string) $ticket->id,
                dedupKey: null,
            ));
        } catch (Throwable $e) {
            Log::error('Error creando notificación in-app de evidencia para asignado.', [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
