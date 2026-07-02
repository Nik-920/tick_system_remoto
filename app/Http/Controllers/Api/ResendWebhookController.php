<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailNotificationDelivery;
use App\Models\ResendWebhookEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Resend\Exceptions\WebhookSignatureVerificationException;
use Resend\WebhookSignature;

/**
 * Recibe los webhooks de Resend (sent/delivered/delivery_delayed/bounced/
 * complained/opened/clicked) y actualiza email_notification_deliveries.
 *
 * Fail-closed: sin RESEND_WEBHOOK_SECRET, sin headers Svix o con firma
 * inválida, el request se rechaza sin procesar el payload.
 */
class ResendWebhookController extends Controller
{
    /** @var array<string, string> */
    private const EVENT_STATUSES = [
        'email.sent' => EmailNotificationDelivery::STATUS_SENT,
        'email.delivered' => EmailNotificationDelivery::STATUS_DELIVERED,
        'email.delivery_delayed' => EmailNotificationDelivery::STATUS_DELIVERY_DELAYED,
        'email.bounced' => EmailNotificationDelivery::STATUS_BOUNCED,
        'email.complained' => EmailNotificationDelivery::STATUS_COMPLAINED,
        'email.opened' => EmailNotificationDelivery::STATUS_OPENED,
        'email.clicked' => EmailNotificationDelivery::STATUS_CLICKED,
    ];

    /** @var array<string, string> */
    private const EVENT_TIMESTAMP_COLUMNS = [
        'email.delivered' => 'delivered_at',
        'email.bounced' => 'bounced_at',
        'email.complained' => 'complained_at',
        'email.opened' => 'opened_at',
        'email.clicked' => 'clicked_at',
    ];

    /** @var list<string> */
    private const SUPPRESSING_EVENTS = ['email.bounced', 'email.complained'];

    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('resend.webhook.secret');

        if (! is_string($secret) || $secret === '') {
            Log::critical('Resend webhook rechazado: RESEND_WEBHOOK_SECRET no configurado.');

            return response()->json(['message' => 'Webhook secret not configured'], 500);
        }

        $headers = $this->svixHeaders($request);
        if ($headers === null) {
            return response()->json(['message' => 'Missing Svix headers'], 400);
        }

        if (! $this->hasValidSignature($request->getContent(), $headers, $secret)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $eventType = (string) ($payload['type'] ?? '');

        if ($this->alreadyProcessed($headers['svix-id'], $eventType)) {
            return response()->json(['status' => 'ok']);
        }

        $this->applyEvent($eventType, $payload);

        return response()->json(['status' => 'ok']);
    }

    /**
     * @return array{'svix-id': string, 'svix-timestamp': string, 'svix-signature': string}|null
     */
    private function svixHeaders(Request $request): ?array
    {
        $id = $request->header('svix-id');
        $timestamp = $request->header('svix-timestamp');
        $signature = $request->header('svix-signature');

        if (! is_string($id) || ! is_string($timestamp) || ! is_string($signature)
            || $id === '' || $timestamp === '' || $signature === '') {
            return null;
        }

        return ['svix-id' => $id, 'svix-timestamp' => $timestamp, 'svix-signature' => $signature];
    }

    /**
     * @param  array{'svix-id': string, 'svix-timestamp': string, 'svix-signature': string}  $headers
     */
    private function hasValidSignature(string $rawBody, array $headers, string $secret): bool
    {
        try {
            return WebhookSignature::verify($rawBody, $headers, $secret, (int) config('resend.webhook.tolerance', 300));
        } catch (WebhookSignatureVerificationException $e) {
            Log::warning('Resend webhook con firma inválida.', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function alreadyProcessed(string $svixId, string $eventType): bool
    {
        try {
            ResendWebhookEvent::create([
                'svix_id' => $svixId,
                'event_type' => $eventType,
                'received_at' => now(),
            ]);

            return false;
        } catch (QueryException) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyEvent(string $eventType, array $payload): void
    {
        $emailId = (string) ($payload['data']['email_id'] ?? '');

        if ($emailId === '' || ! isset(self::EVENT_STATUSES[$eventType])) {
            return;
        }

        $delivery = EmailNotificationDelivery::query()->where('resend_email_id', $emailId)->first();
        if ($delivery === null) {
            return;
        }

        $updates = [
            'status' => self::EVENT_STATUSES[$eventType],
            'last_event_type' => $eventType,
            'last_event_at' => now(),
        ];

        $timestampColumn = self::EVENT_TIMESTAMP_COLUMNS[$eventType] ?? null;
        if ($timestampColumn !== null) {
            $updates[$timestampColumn] = now();
        }

        $delivery->forceFill($updates)->save();

        if (in_array($eventType, self::SUPPRESSING_EVENTS, true)) {
            $this->suppressRecipient($delivery);
        }
    }

    private function suppressRecipient(EmailNotificationDelivery $delivery): void
    {
        if ($delivery->user_id === null) {
            return;
        }

        User::query()->whereKey($delivery->user_id)->update([
            'email_notifications_suppressed_at' => now(),
        ]);
    }
}
