<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Notifications\EmailNotificationProvider;
use PHPUnit\Framework\Assert;

class FakeEmailNotificationProvider implements EmailNotificationProvider
{
    /** @var array<int, array<string, mixed>> */
    public array $sent = [];

    private int $sequence = 0;

    public function send(string $toEmail, string $subject, string $html, array $tags = []): string
    {
        $this->sequence++;

        $this->sent[] = [
            'to' => $toEmail,
            'subject' => $subject,
            'html' => $html,
            'tags' => $tags,
        ];

        return 'fake-email-id-'.$this->sequence;
    }

    public function assertSentTo(string $toEmail): void
    {
        $recipients = array_column($this->sent, 'to');
        Assert::assertContains($toEmail, $recipients, "Expected email sent to {$toEmail}");
    }

    public function assertNotSentTo(string $toEmail): void
    {
        $recipients = array_column($this->sent, 'to');
        Assert::assertNotContains($toEmail, $recipients, "Expected no email sent to {$toEmail}");
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->sent, 'Expected no emails to be sent');
    }
}
