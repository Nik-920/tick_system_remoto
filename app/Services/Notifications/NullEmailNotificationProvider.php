<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Contracts\Notifications\EmailNotificationProvider;

/**
 * Implementación no-op ligada en runningUnitTests() (ver AppServiceProvider),
 * para que los flujos que disparan eventos de ticket reales en tests no
 * intenten llamadas de red a Resend. Los tests dedicados de email inyectan
 * FakeEmailNotificationProvider directamente en el listener/servicio.
 */
class NullEmailNotificationProvider implements EmailNotificationProvider
{
    public function send(string $toEmail, string $subject, string $html, array $tags = []): string
    {
        return 'null-provider-'.uniqid();
    }
}
