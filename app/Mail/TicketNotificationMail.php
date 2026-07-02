<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\Notifications\EmailNotificationPayload;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mailable de notificaciones de ticket — solo se usa para renderizar el HTML
 * (ver EmailNotificationService::sendTicketEmail), el envío real ocurre por
 * EmailNotificationProvider/Resend, no por Mail::send().
 */
class TicketNotificationMail extends Mailable
{
    public function __construct(public readonly EmailNotificationPayload $payload) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->payload->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.notification',
            with: [
                'title' => $this->payload->title,
                'bodyLines' => $this->payload->bodyLines,
                'ctaLabel' => $this->payload->ctaLabel,
                'ctaUrl' => $this->payload->ctaUrl,
            ],
        );
    }
}
