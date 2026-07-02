<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketEvidenceAdded;
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
 * Envía un email al reporter y al técnico asignado cuando se agrega
 * evidencia a un ticket. Reglas de destinatario idénticas a
 * CreateInAppNotificationOnTicketEvidenceAdded (sin auto-notificación, sin
 * duplicar reporter === assignee, admin no notificado).
 *
 * Queue 'notifications' con 3 intentos: idempotente vía dedup por ticket/día.
 */
class SendEmailOnTicketEvidenceAdded implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    public bool $afterCommit = true;

    public function __construct(private readonly EmailNotificationService $emailService) {}

    public function handle(TicketEvidenceAdded $event): void
    {
        $event->ticket->loadMissing(['reporter', 'assignee']);

        $this->notifyReporter($event);
        $this->notifyAssignee($event);
    }

    private function notifyReporter(TicketEvidenceAdded $event): void
    {
        try {
            $ticket = $event->ticket;
            $reporter = $ticket->reporter;

            if (! $reporter instanceof User || $reporter->id === $event->actor->id) {
                return;
            }

            $payload = $this->buildPayload(
                $ticket,
                TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER,
                route('reporter.tickets.show', $ticket),
                'Se agregó evidencia a tu ticket.',
            );

            $this->emailService->sendTicketEmail($reporter, $ticket, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_REPORTER, $payload);
        } catch (Throwable $e) {
            Log::error('Error enviando email de evidencia al reporter.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyAssignee(TicketEvidenceAdded $event): void
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

            $payload = $this->buildPayload(
                $ticket,
                TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE,
                route('tickets.show', $ticket),
                'Se agregó evidencia a un ticket asignado a ti.',
            );

            $this->emailService->sendTicketEmail($assignee, $ticket, TicketNotificationPreference::TYPE_TICKET_EVIDENCE_ASSIGNEE, $payload);
        } catch (Throwable $e) {
            Log::error('Error enviando email de evidencia al asignado.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildPayload(Ticket $ticket, string $type, string $ctaUrl, string $summaryLine): EmailNotificationPayload
    {
        return new EmailNotificationPayload(
            type: $type,
            subject: 'Nueva evidencia: '.$ticket->title,
            title: '📎 Nueva evidencia',
            bodyLines: [$summaryLine, "Título: {$ticket->title}"],
            ctaLabel: 'Ver ticket',
            ctaUrl: $ctaUrl,
            ticketId: (string) $ticket->id,
            dedupKey: null,
        );
    }
}
