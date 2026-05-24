<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EnsureIdempotency
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('idempotency.enabled', true)) {
            return $next($request);
        }

        if (! $this->shouldHandle($request)) {
            return $next($request);
        }

        $key = $this->resolveKey($request);
        if ($key === null || trim($key) === '') {
            if ((bool) config('idempotency.allow_missing', true)) {
                return $next($request);
            }

            return $this->missingKeyResponse($request);
        }

        $key = trim($key);
        $maxLength = (int) config('idempotency.max_key_length', 128);
        if (strlen($key) > $maxLength) {
            return $this->invalidKeyResponse($request, 'Idempotency-Key too long.');
        }

        $userId = $request->user()?->getAuthIdentifier();
        $requestHash = $this->buildRequestHash($request, $userId !== null ? (string) $userId : null);
        $routeName = $request->route()?->getName();
        $path = '/'.ltrim($request->path(), '/');
        $method = $request->method();

        $recordOrResponse = $this->acquireRecord(
            $key,
            $userId !== null ? (string) $userId : null,
            $routeName,
            $method,
            $path,
            $requestHash,
            $request,
        );

        if ($recordOrResponse instanceof Response) {
            return $recordOrResponse;
        }

        $record = $recordOrResponse;

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->markFailed($record);
            throw $exception;
        }

        $this->storeResponse($record, $response);
        $response->headers->set('Idempotency-Key', $key);
        $response->headers->set('Idempotency-Status', 'stored');

        return $response;
    }

    private function shouldHandle(Request $request): bool
    {
        $allowed = config('idempotency.allowed_methods', ['POST', 'PATCH', 'PUT', 'DELETE']);

        return in_array($request->method(), $allowed, true);
    }

    private function resolveKey(Request $request): ?string
    {
        $header = (string) config('idempotency.header', 'Idempotency-Key');
        $fallback = (string) config('idempotency.header_fallback', 'X-Idempotency-Key');

        $key = $request->headers->get($header);
        if (is_string($key) && $key !== '') {
            return $key;
        }

        $key = $request->headers->get($fallback);
        if (is_string($key) && $key !== '') {
            return $key;
        }

        $inputKey = $request->input('idempotency_key');
        if (is_string($inputKey) && $inputKey !== '') {
            return $inputKey;
        }

        return null;
    }

    private function acquireRecord(
        string $key,
        ?string $userId,
        ?string $routeName,
        string $method,
        string $path,
        string $requestHash,
        Request $request,
    ): IdempotencyKey|Response {
        $ttlSeconds = (int) config('idempotency.ttl_seconds', 86400);
        $expiresAt = now()->addSeconds($ttlSeconds);

        return DB::transaction(function () use ($key, $userId, $routeName, $method, $path, $requestHash, $expiresAt, $request) {
            $record = IdempotencyKey::query()->lockForUpdate()->find($key);
            if ($record !== null && $record->isExpired()) {
                $record->delete();
                $record = null;
            }

            if ($record !== null) {
                if ($record->request_hash !== $requestHash) {
                    return $this->conflictResponse($request, 'Idempotency-Key reuse with different payload.');
                }

                if ($record->status === IdempotencyKey::STATUS_COMPLETED) {
                    return $this->replayResponse($record, $request);
                }

                if ($record->status === IdempotencyKey::STATUS_PROCESSING) {
                    return $this->conflictResponse($request, 'Request already in progress.');
                }

                if ($record->status === IdempotencyKey::STATUS_FAILED) {
                    return $this->conflictResponse($request, 'Previous request failed. Retry with a new Idempotency-Key.');
                }

                return $this->conflictResponse($request, 'Idempotency-Key is in an invalid state.');
            }

            try {
                return IdempotencyKey::create([
                    'key' => $key,
                    'user_id' => $userId,
                    'route' => $routeName,
                    'method' => $method,
                    'path' => $path,
                    'request_hash' => $requestHash,
                    'status' => IdempotencyKey::STATUS_PROCESSING,
                    'locked_at' => now(),
                    'expires_at' => $expiresAt,
                ]);
            } catch (QueryException $exception) {
                $existing = IdempotencyKey::query()->lockForUpdate()->find($key);
                if ($existing === null) {
                    throw $exception;
                }

                if ($existing->request_hash !== $requestHash) {
                    return $this->conflictResponse($request, 'Idempotency-Key reuse with different payload.');
                }

                if ($existing->status === IdempotencyKey::STATUS_COMPLETED) {
                    return $this->replayResponse($existing, $request);
                }

                if ($existing->status === IdempotencyKey::STATUS_PROCESSING) {
                    return $this->conflictResponse($request, 'Request already in progress.');
                }

                if ($existing->status === IdempotencyKey::STATUS_FAILED) {
                    return $this->conflictResponse($request, 'Previous request failed. Retry with a new Idempotency-Key.');
                }

                return $this->conflictResponse($request, 'Idempotency-Key is in an invalid state.');
            }
        });
    }

    private function replayResponse(IdempotencyKey $record, Request $request): Response
    {
        if ($record->response_status === null) {
            return $this->conflictResponse($request, 'Idempotency-Key has no stored response.');
        }

        $headers = $record->response_headers ?? [];
        $response = response($record->response_body ?? '', $record->response_status, $headers);
        $response->headers->set('Idempotency-Key', $record->key);
        $response->headers->set('Idempotency-Status', 'replayed');

        return $response;
    }

    private function storeResponse(IdempotencyKey $record, Response $response): void
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            $this->markFailed($record);

            return;
        }

        $record->forceFill([
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'response_status' => $response->getStatusCode(),
            'response_headers' => $this->serializeHeaders($response),
            'response_body' => $response->getContent(),
            'completed_at' => now(),
        ])->save();
    }

    private function markFailed(IdempotencyKey $record): void
    {
        $record->forceFill([
            'status' => IdempotencyKey::STATUS_FAILED,
            'failed_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function serializeHeaders(Response $response): array
    {
        $headers = [];
        foreach ($response->headers->allPreserveCase() as $name => $values) {
            if (strcasecmp($name, 'set-cookie') === 0) {
                continue;
            }

            $headers[$name] = array_values($values);
        }

        return $headers;
    }

    private function conflictResponse(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code' => 'IDEMPOTENCY_CONFLICT',
            ], 409);
        }

        return response($message, 409);
    }

    private function missingKeyResponse(Request $request): Response
    {
        $message = 'Idempotency-Key is required for this request.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code' => 'IDEMPOTENCY_KEY_REQUIRED',
            ], 428);
        }

        return response($message, 428);
    }

    private function invalidKeyResponse(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code' => 'IDEMPOTENCY_KEY_INVALID',
            ], 400);
        }

        return response($message, 400);
    }

    private function buildRequestHash(Request $request, ?string $userId): string
    {
        $payload = [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'query' => $this->normalizeInput($request->query()),
            'body' => $this->normalizeInput($request->except('idempotency_key')),
            'user_id' => $userId,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = serialize($payload);
        }

        return hash('sha256', $encoded);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input): array
    {
        $normalized = [];
        foreach ($input as $key => $value) {
            $normalized[$key] = $this->normalizeValue($value);
        }

        if ($this->isAssoc($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                'name' => $value->getClientOriginalName(),
                'size' => $value->getSize(),
                'mime' => $value->getClientMimeType(),
            ];
        }

        if (is_array($value)) {
            return $this->normalizeInput($value);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
