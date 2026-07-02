<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): ResetPasswordMail
    {
        $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new ResetPasswordMail($resetUrl, $expireMinutes))
            ->to($notifiable->getEmailForPasswordReset());
    }
}
