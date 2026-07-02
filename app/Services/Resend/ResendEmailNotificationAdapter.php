<?php

declare(strict_types=1);

namespace App\Services\Resend;

use App\Contracts\Notifications\EmailNotificationProvider;
use Resend\Client;

class ResendEmailNotificationAdapter implements EmailNotificationProvider
{
    public function __construct(private readonly Client $client) {}

    public function send(string $toEmail, string $subject, string $html, array $tags = []): string
    {
        $parameters = [
            'from' => sprintf('%s <%s>', config('mail.from.name'), config('mail.from.address')),
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $html,
        ];

        if ($tags !== []) {
            $parameters['tags'] = array_map(
                static fn (string $name, string $value): array => ['name' => $name, 'value' => $value],
                array_keys($tags),
                array_values($tags),
            );
        }

        $email = $this->client->emails->send($parameters);

        return (string) $email->id;
    }
}
