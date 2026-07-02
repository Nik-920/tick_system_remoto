<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetPasswordMail extends Mailable
{
    public function __construct(
        public readonly string $resetUrl,
        public readonly int $expireMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Restablece tu contraseña');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.reset-password',
            with: [
                'resetUrl' => $this->resetUrl,
                'expireMinutes' => $this->expireMinutes,
            ],
        );
    }
}
