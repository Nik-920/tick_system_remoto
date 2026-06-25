<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketCommentCreated;
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
 * cuando alguien crea un comentario privado en un ticket.
 *
 * Reglas:
 *  - Reporter notificado si actor !== reporter y preferencia habilitada.
 *  - Assignee notificado si actor !== assignee, assignee tiene rol maintenance,
 *    assignee !== reporter (evita duplicado) y preferencia habilitada.
 *  - Admin/super_admin no notificados (comentarios operativos, no de supervisión).
 *  - No self-notification.
 *
 * Queue 'default' con 3 intentos: escritura in-app idempotente.
 */
class CreateInAppNotificationOnTicketCommentCreated implements ShouldQueue
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

    public function handle(TicketCommentCreated $event): void
    {
        $ticket = $event->ticket;
        $ticketId = (string) $ticket->id;

        $ticket->loadMissing(['reporter', 'assignee']);

        $this->notifyReporter($event, $ticketId);
        $this->notifyAssignee($event, $ticketId);
    }

    private function notifyReporter(TicketCommentCreated $event, string $ticketId): void
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

                return [
                    'user' => $reporter,
                    'type' => 'ticket_comment_reporter',
                    'title' => 'Nuevo comentario: '.$ticket->title,
                    'body' => 'Tu ticket recibió un nuevo comentario.',
                    'url' => route('tickets.show', $ticket),
                    'icon' => '💬',
                    'ticketId' => (string) $ticket->id,
                    'dedupKey' => null,
                ];
            },
            'Error creando notificación in-app de comentario para reporter.',
            $ticketId,
            $this->preferences !== null
                ? fn (User $u) => $this->preferences->isEnabled($u, TicketNotificationPreference::TYPE_TICKET_COMMENT_REPORTER, TicketNotificationPreference::CHANNEL_IN_APP)
                : null,
        );
    }

    private function notifyAssignee(TicketCommentCreated $event, string $ticketId): void
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

            if ($this->preferences !== null && ! $this->preferences->isEnabled($assignee, TicketNotificationPreference::TYPE_TICKET_COMMENT_ASSIGNEE, TicketNotificationPreference::CHANNEL_IN_APP)) {
                return;
            }

            $this->notificationService()->notifyUser($assignee, new NotificationPayload(
                type: 'ticket_comment_assignee',
                title: 'Nuevo comentario: '.$ticket->title,
                body: 'Un ticket asignado a ti recibió un nuevo comentario.',
                url: route('tickets.show', $ticket),
                icon: '💬',
                ticketId: (string) $ticket->id,
                dedupKey: null,
            ));
        } catch (Throwable $e) {
            Log::error('Error creando notificación in-app de comentario para asignado.', [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
