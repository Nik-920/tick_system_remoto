<?php

namespace App\Services\Observability;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TicketQrLogger
{
    private const HASHED_KEYS = ['email', 'qr_token', 'token'];

    private const REDACTED_KEYS = ['comment', 'description', 'title'];

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $eventName, array $context = []): void
    {
        $this->log('info', $eventName, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $eventName, array $context = []): void
    {
        $this->log('warning', $eventName, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $eventName, array $context = []): void
    {
        $this->log('error', $eventName, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $level, string $eventName, array $context = []): void
    {
        $request = $this->currentRequest();
        $correlationId = $this->resolveCorrelationId($request, $context);

        $payload = [
            'event_name' => $eventName,
            'domain' => $this->resolveDomain($eventName, $context),
            'level' => $level,
            'occurred_at' => now()->toIso8601String(),
            'correlation_id' => $correlationId,
            'request_id' => $this->resolveRequestId($request),
            'actor_id' => $this->resolveActorId($context),
            'ticket_id' => $this->stringOrNull($context['ticket_id'] ?? null),
            'location_id' => $this->stringOrNull($context['location_id'] ?? null),
            'qr_job_id' => $this->stringOrNull($context['qr_job_id'] ?? null),
            'operation_type' => $this->stringOrNull($context['operation_type'] ?? null),
            'context' => $this->sanitizeContext($context),
        ];

        Log::channel('ticket_qr')->log($level, $eventName, $this->dropNullValues($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dropNullValues(array $payload): array
    {
        return array_filter($payload, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveDomain(string $eventName, array $context): string
    {
        $domain = $context['domain'] ?? null;
        if (is_string($domain) && $domain !== '') {
            return $domain;
        }

        if (str_starts_with($eventName, 'ticket.')) {
            return 'ticket';
        }

        if (str_starts_with($eventName, 'qr.')) {
            return 'qr';
        }

        return 'general';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveActorId(array $context): ?string
    {
        foreach (['actor_id', 'user_id', 'reporter_id', 'changed_by', 'assigned_to'] as $candidateKey) {
            if (array_key_exists($candidateKey, $context) && $context[$candidateKey] !== null && $context[$candidateKey] !== '') {
                return (string) $context[$candidateKey];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveCorrelationId(?Request $request, array $context): string
    {
        $fromContext = $context['correlation_id'] ?? null;
        if (is_string($fromContext) && $fromContext !== '') {
            return $fromContext;
        }

        if ($request !== null) {
            $fromHeader = (string) $request->headers->get('X-Correlation-Id', '');
            if ($fromHeader !== '') {
                $request->attributes->set('correlation_id', $fromHeader);

                return $fromHeader;
            }

            $existing = (string) $request->attributes->get('correlation_id', '');
            if ($existing !== '') {
                return $existing;
            }

            $generated = (string) Str::uuid();
            $request->attributes->set('correlation_id', $generated);

            return $generated;
        }

        return (string) Str::uuid();
    }

    private function resolveRequestId(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $requestId = (string) $request->headers->get('X-Request-Id', '');

        return $requestId === '' ? null : $requestId;
    }

    private function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request instanceof Request ? $request : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $stringKey = (string) $key;
            $sanitized[$stringKey] = $this->sanitizeValue($stringKey, $value);
        }

        return $sanitized;
    }

    private function sanitizeValue(string $key, mixed $value): mixed
    {
        $normalizedKey = Str::lower($key);

        if (in_array($normalizedKey, self::HASHED_KEYS, true)) {
            return $this->hashValue($value);
        }

        if (in_array($normalizedKey, self::REDACTED_KEYS, true)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $nestedKey => $nestedValue) {
                $result[(string) $nestedKey] = $this->sanitizeValue((string) $nestedKey, $nestedValue);
            }

            return $result;
        }

        if (is_string($value)) {
            return Str::limit($value, 500, '...');
        }

        return $value;
    }

    private function hashValue(mixed $value): string
    {
        if (is_array($value)) {
            $encoded = json_encode($value);

            return hash('sha256', $encoded === false ? '' : $encoded);
        }

        if (is_object($value)) {
            return hash('sha256', get_class($value));
        }

        return hash('sha256', (string) $value);
    }
}
