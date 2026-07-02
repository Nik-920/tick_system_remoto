<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketStateChanged;
use App\Models\Ticket;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\EmailNotificationPayload;
use App\Services\Notifications\EmailNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía un email al reporter y al técnico asignado cuando cambia el estado
 * de un ticket. Reglas de destinatario idénticas a
 * CreateInAppNotificationOnTicketStateChanged: sin auto-notificación y sin
 * duplicar cuando reporter === assignee.
 *
 * Queue 'notifications' con 3 intentos: idempotente vía dedup_key.
 */
class SendEmailOnTicketStateChanged implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    public bool $afterCommit = true;

    /** @var array<string, string> */
    private const STATE_LABELS = [
        'open' => '🔔 Reabierto',
        'in_progress' => '🔧 En progreso',
        'resolved' => '✅ Resuelto',
        'rejected' => '❌ Rechazado',
    ];

    public function __construct(private readonly EmailNotificationService $emailService) {}

    public function handle(TicketStateChanged $event): void
    {
        $this->notifyReporter($event);
        $this->notifyAssignee($event);
    }

    private function notifyReporter(TicketStateChanged $event): void
    {
        try {
            $ticket = $event->ticket;
            $reporter = $ticket->reporter;

            if (! $reporter instanceof User || $reporter->id === $event->actor->id) {
                return;
            }

            $payload = $this->buildPayload(
                $ticket,
                $event,
                TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER,
                route('reporter.tickets.show', $ticket),
                'Tu ticket cambió de estado.',
            );

            $this->emailService->sendTicketEmail($reporter, $ticket, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, $payload);
        } catch (Throwable $e) {
            Log::error('Error enviando email de cambio de estado al reporter.', [
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

            if (! $assignee instanceof User || ! $assignee->hasRole('maintenance')) {
                return;
            }

            $reporter = $ticket->reporter;
            $actorIsAssignee = $event->actor->id === $assignee->id;
            $reporterIsAssignee = $reporter instanceof User && $reporter->id === $assignee->id;

            if ($actorIsAssignee || $reporterIsAssignee) {
                return;
            }

            $payload = $this->buildPayload(
                $ticket,
                $event,
                TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE,
                route('tickets.show', $ticket),
                'Un ticket asignado a ti cambió de estado.',
            );

            $this->emailService->sendTicketEmail($assignee, $ticket, TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, $payload);
        } catch (Throwable $e) {
            Log::error('Error enviando email de cambio de estado al asignado.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildPayload(Ticket $ticket, TicketStateChanged $event, string $type, string $ctaUrl, string $summaryLine): EmailNotificationPayload
    {
        $label = self::STATE_LABELS[$event->toState] ?? '📋 Actualizado';

        return new EmailNotificationPayload(
            type: $type,
            subject: "{$label}: {$ticket->title}",
            title: $label,
            bodyLines: [$summaryLine, "Título: {$ticket->title}"],
            ctaLabel: 'Ver ticket',
            ctaUrl: $ctaUrl,
            ticketId: (string) $ticket->id,
            dedupKey: "ticket_state_changed:{$ticket->id}:{$event->fromState}:{$event->toState}",
        );
    }
}
