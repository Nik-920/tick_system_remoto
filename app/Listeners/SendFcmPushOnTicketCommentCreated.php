<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketCommentCreated;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía push FCM al reporter Y al técnico asignado (maintenance) cuando alguien
 * crea un comentario privado en un ticket.
 *
 * Reglas idénticas al in-app listener:
 *  - No self-notification para el actor.
 *  - No duplicado si reporter === assignee.
 *  - Payload no expone el cuerpo completo del comentario.
 *
 * Queue 'notifications' con 1 intento: push FCM no reintenta sin dedup key.
 */
class SendFcmPushOnTicketCommentCreated implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 1;

    public bool $afterCommit = true;

    public function __construct(
        private PushNotificationProvider $fcm,
        private ?TicketNotificationPreferenceService $preferences = null,
    ) {}

    public function handle(TicketCommentCreated $event): void
    {
        $ticket = $event->ticket;
        $ticket->loadMissing(['reporter', 'assignee']);

        $this->notifyReporter($event);
        $this->notifyAssignee($event);
    }

    private function notifyReporter(TicketCommentCreated $event): void
    {
        try {
            $ticket = $event->ticket;
            $reporter = $ticket->reporter;

            if (! $reporter instanceof User || $reporter->id === $event->actor->id) {
                return;
            }

            if ($this->preferences !== null && ! $this->preferences->isEnabled($reporter, TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER, TicketNotificationPreference::CHANNEL_FCM)) {
                return;
            }

            $this->fcm->sendToUser(
                user: $reporter,
                title: 'Nuevo comentario: '.$ticket->title,
                body: 'Tu ticket recibió un nuevo comentario.',
                data: [
                    'ticket_id' => (string) $ticket->id,
                    'type' => TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER,
                    'action' => 'comment_created',
                    'recipient_role' => 'reporter',
                ],
            );
        } catch (Throwable $e) {
            Log::error('Error enviando push FCM de comentario al reporter.', [
                'ticket_id' => (string) $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyAssignee(TicketCommentCreated $event): void
    {
        try {
            $ticket = $event->ticket;
            $assignee = $ticket->assignee;

            if (! $assignee instanceof User || ! $assignee->hasRole('maintenance')) {
                return;
            }

            $reporter = $ticket->reporter;
            $actorIsAssignee = $assignee->id === $event->actor->id;
            $reporterIsAssignee = $reporter instanceof User && $reporter->id === $assignee->id;

            if ($actorIsAssignee || $reporterIsAssignee) {
                return;
            }

            if ($this->preferences !== null && ! $this->preferences->isEnabled($assignee, TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE, TicketNotificationPreference::CHANNEL_FCM)) {
                return;
            }

            $this->fcm->sendToUser(
                user: $assignee,
                title: 'Nuevo comentario: '.$ticket->title,
                body: 'Un ticket asignado a ti recibió un nuevo comentario.',
                data: [
                    'ticket_id' => (string) $ticket->id,
                    'type' => TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE,
                    'action' => 'comment_created',
                    'recipient_role' => 'maintenance',
                ],
            );
        } catch (Throwable $e) {
            Log::error('Error enviando push FCM de comentario al técnico asignado.', [
                'ticket_id' => (string) $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
