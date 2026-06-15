<?php

declare(strict_types=1);

namespace App\Support\Cache;

class CacheTtl
{
    // Notification counts refresh fast — users expect near-real-time feedback.
    public const NOTIFICATIONS_UNREAD_COUNT = 30;

    public const NOTIFICATIONS_RECENT = 30;

    // Dashboard aggregates are expensive but tolerate 60s staleness.
    public const DASHBOARD_MAINTENANCE = 60;

    public const DASHBOARD_ADMIN = 60;

    public const DASHBOARD_REPORTER = 45;

    // Maintenance report: heavier query, longer TTL.
    public const MAINTENANCE_REPORT = 180;
}
