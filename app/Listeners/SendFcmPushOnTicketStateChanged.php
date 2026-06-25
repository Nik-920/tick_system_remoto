<?php

namespace App\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketStateChanged;
use App\Listeners\Concerns\BuildsTicketStateChangedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía el push FCM cuando cambia el estado del ticket.
 *
 * Destinatarios: reporter (path existente) + maintenance assignee.
 * Queue 'notifications' con 1 intento: push FCM no reintenta sin dedup key.
 */
class SendFcmPushOnTicketStateChanged implements ShouldQueue
{
    use BuildsTicketStateChangedNotification;
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 1;

    public bool $afterCommit = true;

    public function __construct(
        private PushNotificationProvider $fcm
    ) {}

    public function handle(TicketStateChanged $event): void
    {
        $this->notifyReporter($event);
        $this->notifyAssignee($event);
    }

    private function notifyReporter(TicketStateChanged $event): void
    {
        try {
            $n = $this->buildTicketStateChangedNotification($event);
            if ($n === null) {
                return;
            }

            $this->fcm->sendToUser(
                user: $n['user'],
                title: $n['title'],
                body: $n['body'],
                data: $n['data'],
            );
        } catch (Throwable $e) {
            Log::error('Error enviando push FCM en cambio de estado.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyAssignee(TicketStateChanged $event): void
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

            if ($event->actor->id === $assignee->id) {
                return;
            }

            $reporter = $ticket->reporter;
            if ($reporter instanceof User && $reporter->id === $assignee->id) {
                return;
            }

            $stateLabels = [
                'open' => '🔔 Reabierto',
                'in_progress' => '🔧 En progreso',
                'resolved' => '✅ Resuelto',
                'rejected' => '❌ Rechazado',
            ];

            $label = $stateLabels[$event->toState] ?? '📋 Actualizado';
            $url = route('tickets.show', $ticket);

            $this->fcm->sendToUser(
                user: $assignee,
                title: "{$label}: {$ticket->title}",
                body: "Un ticket asignado a ti fue actualizado a: {$event->toState}",
                data: [
                    'ticket_id' => $ticket->id,
                    'url' => $url,
                    'type' => 'ticket_state_changed',
                    'from_state' => $event->fromState,
                    'to_state' => $event->toState,
                    'recipient_role' => 'maintenance',
                ],
            );
        } catch (Throwable $e) {
            Log::error('Error enviando push FCM al técnico asignado en cambio de estado.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
