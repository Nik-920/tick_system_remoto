<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\TicketMedia;

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
 *
 * All rules are enforced by RemoteImageUrlGuard + RemoteImageFetcher.
 * This class only holds the policy for the community media bucket.
 */
final class CommunityRemoteMediaFetcher
{
    public function __construct(
        private readonly RemoteImageFetcher $fetcher,
    ) {}

    public function fetch(TicketMedia $media): ?RemoteMediaPayload
    {
        $fileUrl = trim((string) ($media->file_url ?? ''));

        if ($fileUrl === '') {
            return null;
        }

        return $this->fetcher->fetch($fileUrl, $this->buildPolicy());
    }

    private function buildPolicy(): RemoteImagePolicy
    {
        $bucket = (string) config('community.media.supabase_public_bucket', 'tickets');

        /** @var list<mixed> $hosts */
        $hosts = (array) config('community.media.remote_allowed_hosts', []);

        return new RemoteImagePolicy(
            allowedHosts: array_values(array_filter(
                array_map(fn (mixed $h): string => strtolower(trim((string) $h)), $hosts),
                fn (string $h): bool => $h !== '',
            )),
            requiredPathPrefix: 'storage/v1/object/public/'.$bucket.'/',
            maxBytes: (int) config('community.media.max_remote_bytes', 8 * 1024 * 1024),
            timeoutSeconds: (int) config('community.media.remote_timeout_seconds', 5),
        );
    }
}
