<?php

declare(strict_types=1);

namespace App\Support\Cache;

class CacheKeys
{
    public static function notificationsUnreadCount(string $userId): string
    {
        return "notifications:unread_count:{$userId}";
    }

    public static function notificationsRecent(string $userId): string
    {
        return "notifications:recent:{$userId}";
    }

    public static function maintenanceDashboard(string $userId, string $rangeHash): string
    {
        return "dashboard:maintenance:{$userId}:{$rangeHash}";
    }

    public static function adminDashboard(string $rangeHash): string
    {
        return "dashboard:admin:{$rangeHash}";
    }

    /**
     * Version counter key for a reporter's dashboard cache.
     * Incrementing this key invalidates all versioned entries for that reporter.
     */
    public static function reporterDashboardVersion(string $userId): string
    {
        return "dashboard:version:reporter:{$userId}";
    }

    /**
     * Versioned reporter dashboard entry key.
     * Built by DashboardCache — prefer using that service directly.
     */
    public static function reporterDashboard(string $userId, int $version = 1, string $contextHash = ''): string
    {
        return "dashboard:v{$version}:reporter:{$userId}:{$contextHash}";
    }

    public static function maintenanceReport(string $userId, string $from, string $to): string
    {
        return "maintenance-report:{$userId}:{$from}:{$to}";
    }
}
