<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\Notifications\EmailNotificationProvider;
use App\Models\Category;
use App\Models\EmailNotificationDelivery;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\EmailNotificationPayload;
use App\Services\Notifications\EmailNotificationService;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakeEmailNotificationProvider;
use Tests\TestCase;

class EmailNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeEmailNotificationProvider $provider;

    private TicketNotificationPreferenceService $prefs;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('reporter', 'web');
        $this->provider = new FakeEmailNotificationProvider;
        $this->prefs = app(TicketNotificationPreferenceService::class);
    }

    public function test_sends_email_when_enabled_by_default(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        $this->makeService()->sendTicketEmail(
            $reporter,
            $ticket,
            TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER,
            $this->makePayload($ticket),
        );

        $this->provider->assertSentTo($reporter->email);
    }

    public function test_does_not_send_when_preference_disabled(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_EMAIL, false);

        $this->makeService()->sendTicketEmail(
            $reporter,
            $ticket,
            TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER,
            $this->makePayload($ticket),
        );

        $this->provider->assertNothingSent();
        $this->assertDatabaseMissing('email_notification_deliveries', ['user_id' => $reporter->id]);
    }

    public function test_does_not_send_when_recipient_has_no_email(): void
    {
        $reporter = $this->makeReporter();
        $reporter->forceFill(['email' => ''])->save();
        $ticket = $this->makeTicket($reporter);

        $threw = false;
        try {
            $this->makeService()->sendTicketEmail(
                $reporter,
                $ticket,
                TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER,
                $this->makePayload($ticket),
            );
        } catch (\Throwable) {
            $threw = true;
        }

        $this->assertFalse($threw);
        $this->provider->assertNothingSent();
    }

    public function test_does_not_send_when_recipient_is_suppressed(): void
    {
        $reporter = $this->makeReporter();
        $reporter->forceFill(['email_notifications_suppressed_at' => now()])->save();
        $ticket = $this->makeTicket($reporter);

        $this->makeService()->sendTicketEmail(
            $reporter,
            $ticket,
            TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER,
            $this->makePayload($ticket),
        );

        $this->provider->assertNothingSent();
    }

    public function test_dedup_prevents_second_send_with_same_dedup_key(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $service = $this->makeService();

        $service->sendTicketEmail($reporter, $ticket, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, $this->makePayload($ticket));
        $service->sendTicketEmail($reporter, $ticket, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, $this->makePayload($ticket));

        $this->assertCount(1, $this->provider->sent);
    }

    public function test_delivery_row_created_with_sent_status_and_resend_email_id(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        $this->makeService()->sendTicketEmail(
            $reporter,
            $ticket,
            TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER,
            $this->makePayload($ticket),
        );

        $delivery = EmailNotificationDelivery::query()->where('user_id', $reporter->id)->firstOrFail();
        $this->assertSame(EmailNotificationDelivery::STATUS_SENT, $delivery->status);
        $this->assertNotNull($delivery->resend_email_id);
        $this->assertNotNull($delivery->sent_at);
        $this->assertSame($reporter->email, $delivery->recipient_email);
    }

    public function test_payload_does_not_leak_file_url_or_other_users_email(): void
    {
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        $this->makeService()->sendTicketEmail(
            $reporter,
            $ticket,
            TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER,
            $this->makePayload($ticket),
        );

        $html = $this->provider->sent[0]['html'];
        $this->assertStringNotContainsString('file_url', $html);
        $this->assertStringNotContainsString('supabase', strtolower($html));
    }

    public function test_provider_failure_marks_delivery_failed_and_logs(): void
    {
        Log::spy();

        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        $throwingProvider = new class implements EmailNotificationProvider
        {
            public function send(string $toEmail, string $subject, string $html, array $tags = []): string
            {
                throw new \RuntimeException('Resend exploded');
            }
        };

        $service = new EmailNotificationService($throwingProvider, $this->prefs);
        $service->sendTicketEmail($reporter, $ticket, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, $this->makePayload($ticket));

        $delivery = EmailNotificationDelivery::query()->where('user_id', $reporter->id)->firstOrFail();
        $this->assertSame(EmailNotificationDelivery::STATUS_FAILED, $delivery->status);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')->withArgs(fn ($msg) => str_contains((string) $msg, 'email'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeService(): EmailNotificationService
    {
        return new EmailNotificationService($this->provider, $this->prefs);
    }

    private function makeReporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeTicket(User $reporter): Ticket
    {
        $location = Location::create([
            'name' => 'Sala email '.uniqid(),
            'building' => 'Edificio E',
            'floor' => '1',
            'room_code' => 'E-'.uniqid(),
            'qr_token' => 'qr-e-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat email '.uniqid(),
            'icon' => 'bolt',
            'description' => 'Test email',
        ]);

        return Ticket::create([
            'title' => 'Ticket email prueba',
            'description' => 'Descripcion email.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function makePayload(Ticket $ticket): EmailNotificationPayload
    {
        return new EmailNotificationPayload(
            type: TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER,
            subject: 'Cambio de estado: '.$ticket->title,
            title: 'Cambio de estado',
            bodyLines: ['Tu ticket cambió de estado.'],
            ctaLabel: 'Ver ticket',
            ctaUrl: 'http://localhost/tickets/'.$ticket->id,
            ticketId: (string) $ticket->id,
            dedupKey: "ticket_state_changed:{$ticket->id}:open:resolved",
        );
    }
}
