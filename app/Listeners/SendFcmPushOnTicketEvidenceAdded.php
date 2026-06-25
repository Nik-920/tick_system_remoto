<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketEvidenceAdded;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía push FCM al reporter Y al técnico asignado (maintenance) cuando alguien
 * agrega evidencia a un ticket.
 *
 * Reglas idénticas a CreateInAppNotificationOnTicketEvidenceAdded:
 *  - No self-notification para el actor.
 *  - No duplicado si reporter === assignee.
 *  - Payload no expone file_url ni rutas privadas.
 *
 * Queue 'notifications' con 1 intento: push FCM no reintenta sin dedup key.
 */
class SendFcmPushOnTicketEvidenceAdded implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 1;

    public bool $afterCommit = true;

    public function __construct(
        private PushNotificationProvider $fcm,
        private ?TicketNotificationPreferenceService $preferences = null,
    ) {}

    public function handle(TicketEvidenceAdded $event): void
    {
        $ticket = $event->ticket;
        $ticket->loadMissing(['reporter', 'assignee']);

        $this->notifyReporter($event);
        $this->notifyAssignee($event);
    }

    private function notifyReporter(TicketEvidenceAdded $event): void
    {
        try {
            $ticket = $event->ticket;
            $reporter = $ticket->reporter;

            if (! $reporter instanceof User) {
                return;
            }

            if ($reporter->id === $event->actor->id) {
                return;
            }

            if ($this->preferences !== null && ! $this->preferences->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, TicketNotificationPreference::CHANNEL_FCM)) {
                return;
            }

            $this->fcm->sendToUser(
                user: $reporter,
                title: 'Nueva evidencia: '.$ticket->title,
                body: 'Se agregó evidencia a tu ticket.',
                data: [
                    'ticket_id' => (string) $ticket->id,
                    'type' => 'ticket_evidence_added',
                    'action' => 'evidence_added',
                    'recipient_role' => 'reporter',
                    'count' => (string) $event->count,
                ],
            );
        } catch (Throwable $e) {
            Log::error('Error enviando push FCM de evidencia al reporter.', [
                'ticket_id' => (string) $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyAssignee(TicketEvidenceAdded $event): void
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

            if ($this->preferences !== null && ! $this->preferences->isEnabled($assignee, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM)) {
                return;
            }

            $this->fcm->sendToUser(
                user: $assignee,
                title: 'Nueva evidencia: '.$ticket->title,
                body: 'Se agregó evidencia a un ticket asignado a ti.',
                data: [
                    'ticket_id' => (string) $ticket->id,
                    'type' => 'ticket_evidence_added',
                    'action' => 'evidence_added',
                    'recipient_role' => 'maintenance',
                    'count' => (string) $event->count,
                ],
            );
        } catch (Throwable $e) {
            Log::error('Error enviando push FCM de evidencia al técnico asignado.', [
                'ticket_id' => (string) $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
