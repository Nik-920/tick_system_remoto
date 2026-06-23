<?php

declare(strict_types=1);

namespace App\Services\Community;

/**
 * SSRF guard for remote image URLs.
 *
 * Security rules enforced:
 * - Scheme must be https.
 * - No userinfo (user:pass@host).
 * - Host must appear in the policy allowlist.
 * - Host must not be a private/loopback/link-local address.
 * - URL path must begin with the policy's required prefix (bucket guard).
 * - Redirects are never followed (enforced at the HTTP layer, not here).
 */
final class RemoteImageUrlGuard
{
    public function allows(string $url, RemoteImagePolicy $policy): bool
    {
        $parsed = parse_url($url);

        if (! is_array($parsed)) {
            return false;
        }

        return $this->passesAllChecks($parsed, $policy);
    }

    /** @param array<string, mixed> $parsed */
    private function passesAllChecks(array $parsed, RemoteImagePolicy $policy): bool
    {
        $host = strtolower((string) ($parsed['host'] ?? ''));
        $path = ltrim((string) ($parsed['path'] ?? ''), '/');

        return ($parsed['scheme'] ?? '') === 'https'
            && ! isset($parsed['user'])
            && ! isset($parsed['pass'])
            && $host !== ''
            && ! $this->isPrivateHost($host)
            && in_array($host, $policy->allowedHosts, true)
            && str_starts_with($path, $policy->requiredPathPrefix);
    }

    private function isPrivateHost(string $host): bool
    {
        $loopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        $cgnat = str_starts_with($host, '169.254.') || str_starts_with($host, '100.64.');
        $classA = str_starts_with($host, '10.') || str_starts_with($host, '192.168.');
        $class172 = (bool) preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $host);
        $zeroNet = str_starts_with($host, '0.') || $host === '0.0.0.0';

        return $loopback || $cgnat || $classA || $class172 || $zeroNet;
    }
}
