<?php

declare(strict_types=1);

namespace App\Services\Community;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Downloads an image from a URL that has already been approved by RemoteImageUrlGuard.
 *
 * Security enforced:
 * - URL is re-validated against the policy before every request.
 * - Redirects are never followed (withoutRedirecting).
 * - Content-Type must be image/*.
 * - Body size must not exceed policy maxBytes (checked via Content-Length header and actual body).
 * - Empty bodies are rejected.
 */
final class RemoteImageFetcher
{
    public function __construct(
        private readonly RemoteImageUrlGuard $guard,
    ) {}

    public function allows(string $url, RemoteImagePolicy $policy): bool
    {
        return $this->guard->allows($url, $policy);
    }

    public function fetch(string $url, RemoteImagePolicy $policy): ?RemoteMediaPayload
    {
        if (! $this->guard->allows($url, $policy)) {
            return null;
        }

        return $this->doFetch($url, $policy);
    }

    private function doFetch(string $url, RemoteImagePolicy $policy): ?RemoteMediaPayload
    {
        try {
            return $this->fetchAndValidate($url, $policy);
        } catch (\Throwable $e) {
            Log::warning('RemoteImageFetcher: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function fetchAndValidate(string $url, RemoteImagePolicy $policy): ?RemoteMediaPayload
    {
        $response = Http::timeout($policy->timeoutSeconds)->withoutRedirecting()->get($url);

        if (! $response->successful()) {
            return null;
        }

        return $this->buildPayload($response, $policy->maxBytes);
    }

    private function buildPayload(Response $response, int $maxBytes): ?RemoteMediaPayload
    {
        $contentLength = (int) ($response->header('Content-Length') ?: 0);
        $contentType = strtolower(trim(explode(';', (string) ($response->header('Content-Type') ?: ''))[0]));
        $body = $response->body();

        $valid = ($contentLength === 0 || $contentLength <= $maxBytes)
            && strlen($body) <= $maxBytes
            && $body !== ''
            && str_starts_with($contentType, 'image/');

        if (! $valid) {
            return null;
        }

        return new RemoteMediaPayload(bytes: $body, contentType: $contentType);
    }
}
