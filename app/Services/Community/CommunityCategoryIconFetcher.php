<?php

declare(strict_types=1);

namespace App\Services\Community;

/**
 * Fetches category icon images from an allowlisted Supabase bucket.
 *
 * Security contract:
 * - Only https:// URLs are accepted.
 * - Host must be in the allowlist derived from SUPABASE_URL (community.media.remote_allowed_hosts).
 * - URL path must start with /storage/v1/object/public/{category_icon_bucket}/.
 * - No userinfo (user:pass@host) permitted.
 * - Private/loopback IPs and hostnames are blocked.
 * - Redirects are not followed (SSRF prevention).
 * - Content-Type must be image/*.
 * - Body size must not exceed max_remote_bytes.
 *
 * All rules are enforced by RemoteImageUrlGuard + RemoteImageFetcher.
 * This class only holds the policy for the category-icons bucket.
 */
final class CommunityCategoryIconFetcher
{
    public function __construct(
        private readonly RemoteImageFetcher $fetcher,
    ) {}

    public function fetch(string $url): ?RemoteMediaPayload
    {
        return $this->fetcher->fetch($url, $this->buildPolicy());
    }

    public function isAllowedUrl(string $url): bool
    {
        return $this->fetcher->allows($url, $this->buildPolicy());
    }

    private function buildPolicy(): RemoteImagePolicy
    {
        $bucket = (string) config('community.category_icons.supabase_bucket', 'TicketCategoria');

        /** @var list<mixed> $hosts */
        $hosts = (array) config('community.media.remote_allowed_hosts', []);

        return new RemoteImagePolicy(
            allowedHosts: array_values(array_filter(
                array_map(fn (mixed $h): string => strtolower(trim((string) $h)), $hosts),
                fn (string $h): bool => $h !== '',
            )),
            requiredPathPrefix: 'storage/v1/object/public/'.$bucket.'/',
            maxBytes: (int) config('community.category_icons.max_remote_bytes', 2 * 1024 * 1024),
            timeoutSeconds: (int) config('community.category_icons.remote_timeout_seconds', 5),
        );
    }
}
