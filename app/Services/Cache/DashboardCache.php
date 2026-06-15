<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Versioned-key cache for dashboard surfaces.
 *
 * Keys follow the pattern:
 *   dashboard:v{version}:{surface}:{userId}:{contextHash}
 *
 * Invalidation bumps the per-user version number, making all existing
 * entries for that user unreachable without touching other users or
 * calling Cache::flush(). Expired entries are eventually evicted by Redis TTL.
 *
 * Prefix collision notes:
 *   - Lock keys:        tick:ticket:*   (separate prefix, no overlap)
 *   - Idempotency:      idempotency:*   (separate prefix, no overlap)
 *   - Queues:           queues:*        (separate prefix, no overlap)
 *   - Notifications:    notifications:* (separate prefix, no overlap)
 *   - Dashboard cache:  dashboard:*     (this class only)
 */
class DashboardCache
{
    private const VERSION_TTL_DAYS = 7;

    /**
     * @param  array<string, mixed>  $context  Scalar-only context (filters, status, page, etc.)
     */
    public function rememberReporter(int|string $userId, array $context, int $ttl, Closure $callback): mixed
    {
        if (! $this->isEnabled()) {
            return $callback();
        }

        $version = $this->getOrInitVersion($this->versionKeyReporter($userId));
        $key = "dashboard:v{$version}:reporter:{$userId}:{$this->contextHash($context)}";

        return Cache::remember($key, $ttl, $callback);
    }

    public function invalidateReporter(int|string $userId): void
    {
        $this->bumpVersion($this->versionKeyReporter($userId));
    }

    /**
     * Produces a short, stable hash from scalar context values only.
     *
     * @param  array<string, mixed>  $context
     */
    public function contextHash(array $context): string
    {
        $scalar = array_filter(
            $context,
            fn (mixed $v): bool => is_scalar($v) && $v !== '' && $v !== false && $v !== null
        );
        ksort($scalar);

        return substr(sha1(serialize($scalar)), 0, 16);
    }

    private function versionKeyReporter(int|string $userId): string
    {
        return "dashboard:version:reporter:{$userId}";
    }

    private function getOrInitVersion(string $versionKey): int
    {
        $version = Cache::get($versionKey);
        if ($version === null) {
            Cache::put($versionKey, 1, now()->addDays(self::VERSION_TTL_DAYS));

            return 1;
        }

        return (int) $version;
    }

    private function bumpVersion(string $versionKey): void
    {
        try {
            $current = (int) Cache::get($versionKey, 0);
            Cache::put($versionKey, $current + 1, now()->addDays(self::VERSION_TTL_DAYS));
        } catch (\Throwable $e) {
            Log::warning('DashboardCache: version bump failed.', [
                'key' => $versionKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isEnabled(): bool
    {
        return (bool) config('cache.dashboard_enabled', true);
    }
}
