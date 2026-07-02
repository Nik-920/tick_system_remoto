<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\EmailNotificationPayload;
use App\Services\Notifications\EmailNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envía un email a admins/super_admins cuando se crea un ticket.
 *
 * Queue 'notifications' con 3 intentos: la entrega de email es idempotente
 * gracias al dedup_key de EmailNotificationDelivery.
 */
class SendEmailOnTicketCreated implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public int $tries = 3;

    public bool $afterCommit = true;

    public function __construct(private readonly EmailNotificationService $emailService) {}

    public function handle(TicketCreated $event): void
    {
        try {
            $ticket = $event->ticket;
            $location = $ticket->location?->name ?? 'Ubicación desconocida';
            $category = $ticket->category?->name ?? 'Sin categoría';
            $url = route('tickets.show', $ticket);

            $payload = new EmailNotificationPayload(
                type: TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN,
                subject: 'Nuevo ticket reportado: '.$ticket->title,
                title: '🎫 Nuevo ticket reportado',
                bodyLines: [
                    "Se reportó un nuevo ticket en {$location} ({$category}).",
                    "Título: {$ticket->title}",
                ],
                ctaLabel: 'Ver ticket',
                ctaUrl: $url,
                ticketId: (string) $ticket->id,
                dedupKey: "ticket_created:{$ticket->id}",
            );

            User::role(['admin', 'super_admin'])->get()->unique('id')->each(
                fn (User $admin) => $this->emailService->sendTicketEmail(
                    $admin,
                    $ticket,
                    TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN,
                    $payload,
                )
            );
        } catch (Throwable $e) {
            Log::error('Error enviando email en ticket creado.', [
                'ticket_id' => $event->ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
