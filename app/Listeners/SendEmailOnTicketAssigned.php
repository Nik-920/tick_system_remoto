<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketAssigned;
use App\Listeners\Concerns\BuildsTicketAssignedNotification;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\EmailNotificationPayload;
use App\Services\Notifications\EmailNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía un email al destinatario (nuevo asignado o asignado previo) cuando
 * cambia la asignación de un ticket. Reutiliza BuildsTicketAssignedNotification
 * para no duplicar la resolución de destinatario por acción.
 *
 * Queue 'notifications' con 3 intentos: idempotente vía dedup_key.
 */
class SendEmailOnTicketAssigned implements ShouldQueue
{
    use BuildsTicketAssignedNotification;
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    public bool $afterCommit = true;

    public function __construct(private readonly EmailNotificationService $emailService) {}

    public function handle(TicketAssigned $event): void
    {
        try {
            $built = $this->buildTicketAssignedNotification($event);

            if ($built === null) {
                return;
            }

            $preferenceType = $event->action === 'unassigned'
                ? TicketNotificationPreference::TYPE_TICKET_UNASSIGNED_ASSIGNEE
                : TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE;

            /** @var User $recipient */
            $recipient = $built['user'];

            $payload = new EmailNotificationPayload(
                type: $preferenceType,
                subject: $built['title'],
                title: $built['icon'].' '.$built['title'],
                bodyLines: [$built['body']],
                ctaLabel: 'Ver ticket',
                ctaUrl: $built['url'],
                ticketId: $built['ticketId'],
                dedupKey: $built['dedupKey'],
            );

            $this->emailService->sendTicketEmail($recipient, $event->ticket, $preferenceType, $payload);
        } catch (Throwable $e) {
            Log::error('Error enviando email en cambio de asignación.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
