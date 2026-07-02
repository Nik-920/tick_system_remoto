<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Contracts\Notifications\EmailNotificationProvider;
use App\Mail\TicketNotificationMail;
use App\Models\EmailNotificationDelivery;
use App\Models\Ticket;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailNotificationService
{
    public function __construct(
        private readonly EmailNotificationProvider $provider,
        private readonly TicketNotificationPreferenceService $preferences,
    ) {}

    public function sendTicketEmail(User $recipient, Ticket $ticket, string $type, EmailNotificationPayload $payload): void
    {
        if (! $this->shouldSend($recipient, $type)) {
            return;
        }

        if ($this->isDuplicate($recipient->id, $type, $payload)) {
            return;
        }

        $delivery = EmailNotificationDelivery::create([
            'user_id' => $recipient->id,
            'ticket_id' => $payload->ticketId,
            'notification_type' => $type,
            'recipient_email' => $recipient->email,
            'subject' => $payload->subject,
            'status' => EmailNotificationDelivery::STATUS_QUEUED,
            'dedup_key' => $payload->dedupKey,
        ]);

        try {
            $html = (new TicketNotificationMail($payload))->render();

            $emailId = $this->provider->send(
                toEmail: $recipient->email,
                subject: $payload->subject,
                html: $html,
                tags: [
                    'notification_type' => $type,
                    'delivery_id' => $delivery->id,
                ],
            );

            $delivery->forceFill([
                'status' => EmailNotificationDelivery::STATUS_SENT,
                'resend_email_id' => $emailId,
                'sent_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $delivery->forceFill(['status' => EmailNotificationDelivery::STATUS_FAILED])->save();

            Log::error('Error enviando email de notificación de ticket.', [
                'ticket_id' => $payload->ticketId,
                'notification_type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function shouldSend(User $recipient, string $type): bool
    {
        if (trim((string) $recipient->email) === '') {
            return false;
        }

        if ($recipient->isEmailNotificationsSuppressed()) {
            return false;
        }

        return $this->preferences->isEnabled($recipient, $type, TicketNotificationPreference::CHANNEL_EMAIL);
    }

    private function isDuplicate(string $userId, string $type, EmailNotificationPayload $payload): bool
    {
        if ($payload->dedupKey !== null) {
            return EmailNotificationDelivery::query()
                ->where('user_id', $userId)
                ->where('dedup_key', $payload->dedupKey)
                ->whereDate('created_at', now()->toDateString())
                ->exists();
        }

        if ($payload->ticketId !== null) {
            return EmailNotificationDelivery::query()
                ->where('user_id', $userId)
                ->where('notification_type', $type)
                ->where('ticket_id', $payload->ticketId)
                ->whereDate('created_at', now()->toDateString())
                ->exists();
        }

        return false;
    }
}
