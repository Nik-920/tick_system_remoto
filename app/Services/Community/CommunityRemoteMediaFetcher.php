<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\TicketMedia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Downloads media from an allowlisted remote host (Supabase public bucket).
 *
 * Security contract:
 * - Only https:// URLs are accepted.
 * - Host must be in the config allowlist derived from SUPABASE_URL.
 * - URL must follow the Supabase public storage path pattern.
 * - No userinfo (user:pass@host) permitted.
 * - Private/loopback IPs and hostnames are blocked.
 * - Redirects are not followed (SSRF prevention).
 * - Content-Type must be image/*.
 * - Body size must not exceed max_remote_bytes.
 */
final class CommunityRemoteMediaFetcher
{
    public function fetch(TicketMedia $media): ?RemoteMediaPayload
    {
        $fileUrl = trim((string) ($media->file_url ?? ''));
        if ($fileUrl === '') {
            return null;
        }

        if (! $this->isAllowedUrl($fileUrl)) {
            return null;
        }

        try {
            $response = Http::timeout((int) config('community.media.remote_timeout_seconds', 5))
                ->withoutRedirecting()
                ->get($fileUrl);

            if (! $response->successful()) {
                return null;
            }

            $maxBytes = (int) config('community.media.max_remote_bytes', 8 * 1024 * 1024);

            $contentLength = (int) ($response->header('Content-Length') ?: 0);
            if ($contentLength > 0 && $contentLength > $maxBytes) {
                return null;
            }

            $contentType = strtolower(trim((string) ($response->header('Content-Type') ?: '')));
            $contentType = trim(explode(';', $contentType)[0]);

            if (! str_starts_with($contentType, 'image/')) {
                return null;
            }

            $body = $response->body();
            if (strlen($body) > $maxBytes) {
                return null;
            }

            if ($body === '') {
                return null;
            }

            return new RemoteMediaPayload(bytes: $body, contentType: $contentType);
        } catch (\Throwable $e) {
            Log::warning('CommunityRemoteMediaFetcher: fetch failed', [
                'media_id' => (string) $media->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function isAllowedUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (! is_array($parsed)) {
            return false;
        }

        if (($parsed['scheme'] ?? '') !== 'https') {
            return false;
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return false;
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        if ($this->isPrivateHost($host)) {
            return false;
        }

        /** @var list<mixed> $configHosts */
        $configHosts = (array) config('community.media.remote_allowed_hosts', []);
        $allowedHosts = array_values(array_filter(
            array_map(fn (mixed $h): string => strtolower(trim((string) $h)), $configHosts),
            fn (string $h): bool => $h !== '',
        ));

        if (! in_array($host, $allowedHosts, true)) {
            return false;
        }

        $bucket = (string) config('community.media.supabase_public_bucket', 'tickets');
        $path = ltrim((string) ($parsed['path'] ?? ''), '/');
        $expectedPrefix = 'storage/v1/object/public/'.$bucket.'/';

        return str_starts_with($path, $expectedPrefix);
    }

    private function isPrivateHost(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if (str_starts_with($host, '169.254.') || str_starts_with($host, '100.64.')) {
            return true;
        }

        if (str_starts_with($host, '10.') || str_starts_with($host, '192.168.')) {
            return true;
        }

        if ((bool) preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $host)) {
            return true;
        }

        if (str_starts_with($host, '0.') || $host === '0.0.0.0') {
            return true;
        }

        return false;
    }
}
