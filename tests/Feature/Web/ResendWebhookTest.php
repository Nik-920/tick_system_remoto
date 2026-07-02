<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\EmailNotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Webhook de Resend (/api/webhooks/resend): verificación de firma Svix
 * fail-closed, idempotencia por svix-id y actualización de
 * email_notification_deliveries.
 */
class ResendWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_dGVzdC1zaWduaW5nLXNlY3JldC0zMi1ieXRlcyEh';

    protected function setUp(): void
    {
        parent::setUp();
        config(['resend.webhook.secret' => self::SECRET, 'resend.webhook.tolerance' => 300]);
    }

    public function test_missing_svix_headers_is_rejected(): void
    {
        $this->postJson(route('api.webhooks.resend'), ['type' => 'email.delivered'])
            ->assertStatus(400);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = ['type' => 'email.delivered', 'data' => ['email_id' => 'unknown']];

        $this->postJson(route('api.webhooks.resend'), $payload, [
            'svix-id' => 'msg_1',
            'svix-timestamp' => (string) time(),
            'svix-signature' => 'v1,'.base64_encode('not-a-valid-signature'),
        ])->assertStatus(400);
    }

    public function test_missing_secret_is_rejected(): void
    {
        config(['resend.webhook.secret' => null]);
        $payload = ['type' => 'email.delivered', 'data' => ['email_id' => 'unknown']];

        $this->postJson(route('api.webhooks.resend'), $payload, [
            'svix-id' => 'msg_2',
            'svix-timestamp' => (string) time(),
            'svix-signature' => 'v1,'.base64_encode('irrelevant'),
        ])->assertStatus(500);
    }

    public function test_email_delivered_updates_delivery(): void
    {
        $delivery = $this->makeDelivery();
        $payload = ['type' => 'email.delivered', 'data' => ['email_id' => $delivery->resend_email_id]];

        $this->postSigned($payload, 'msg_3')->assertOk();

        $delivery->refresh();
        $this->assertSame(EmailNotificationDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertSame('email.delivered', $delivery->last_event_type);
    }

    public function test_email_bounced_updates_delivery_and_suppresses_user(): void
    {
        $user = User::factory()->create();
        $delivery = $this->makeDelivery($user);
        $payload = ['type' => 'email.bounced', 'data' => ['email_id' => $delivery->resend_email_id]];

        $this->postSigned($payload, 'msg_4')->assertOk();

        $delivery->refresh();
        $this->assertSame(EmailNotificationDelivery::STATUS_BOUNCED, $delivery->status);
        $this->assertNotNull($delivery->bounced_at);
        $this->assertNotNull($user->fresh()->email_notifications_suppressed_at);
    }

    public function test_email_complained_updates_delivery_and_suppresses_user(): void
    {
        $user = User::factory()->create();
        $delivery = $this->makeDelivery($user);
        $payload = ['type' => 'email.complained', 'data' => ['email_id' => $delivery->resend_email_id]];

        $this->postSigned($payload, 'msg_5')->assertOk();

        $delivery->refresh();
        $this->assertSame(EmailNotificationDelivery::STATUS_COMPLAINED, $delivery->status);
        $this->assertNotNull($user->fresh()->email_notifications_suppressed_at);
    }

    public function test_duplicate_event_is_idempotent(): void
    {
        $delivery = $this->makeDelivery();
        $payload = ['type' => 'email.delivered', 'data' => ['email_id' => $delivery->resend_email_id]];

        $this->postSigned($payload, 'msg_6')->assertOk();
        $this->postSigned($payload, 'msg_6')->assertOk();

        $this->assertDatabaseCount('resend_webhook_events', 1);
    }

    public function test_unknown_event_type_returns_ok(): void
    {
        $payload = ['type' => 'email.something_new', 'data' => ['email_id' => 'whatever']];

        $this->postSigned($payload, 'msg_7')->assertOk();
    }

    public function test_event_with_no_matching_delivery_returns_ok(): void
    {
        $payload = ['type' => 'email.delivered', 'data' => ['email_id' => 'does-not-exist']];

        $this->postSigned($payload, 'msg_8')->assertOk();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeDelivery(?User $user = null): EmailNotificationDelivery
    {
        $user ??= User::factory()->create();

        return EmailNotificationDelivery::create([
            'user_id' => $user->id,
            'notification_type' => 'ticket.state.reporter',
            'recipient_email' => $user->email,
            'resend_email_id' => 'resend-email-'.uniqid(),
            'subject' => 'Test subject',
            'status' => EmailNotificationDelivery::STATUS_SENT,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSigned(array $payload, string $svixId): TestResponse
    {
        $rawBody = json_encode($payload);
        $timestamp = time();

        return $this->postJson(route('api.webhooks.resend'), $payload, [
            'svix-id' => $svixId,
            'svix-timestamp' => (string) $timestamp,
            'svix-signature' => $this->sign(self::SECRET, $svixId, $timestamp, $rawBody),
        ]);
    }

    private function sign(string $secret, string $messageId, int $timestamp, string $payload): string
    {
        $prefix = 'whsec_';
        $decodedSecret = str_starts_with($secret, $prefix) ? substr($secret, strlen($prefix)) : $secret;
        $decodedSecret = base64_decode($decodedSecret);

        $toSign = "{$messageId}.{$timestamp}.{$payload}";
        $hash = hash_hmac('sha256', $toSign, $decodedSecret);
        $signature = base64_encode(pack('H*', $hash));

        return "v1,{$signature}";
    }
}
