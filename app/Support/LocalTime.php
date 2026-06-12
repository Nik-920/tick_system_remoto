<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Presentation-only date formatting in the user-facing timezone (Perú).
 *
 * Storage stays in the app timezone (UTC by default); this helper converts
 * ONLY at render time, so DB values, comparisons and now() in services and
 * tests are not affected. Use it in Blade views instead of calling
 * ->format() directly on a Carbon attribute.
 */
final class LocalTime
{
    public const DEFAULT_FORMAT = 'd/m/Y H:i';

    /**
     * Format a date in the display timezone. Returns null when the value is
     * null so views can keep their `?? '—'` fallbacks.
     */
    public static function format(?DateTimeInterface $value, string $format = self::DEFAULT_FORMAT): ?string
    {
        if ($value === null) {
            return null;
        }

        $carbon = $value instanceof CarbonInterface
            ? Carbon::instance($value)
            : Carbon::parse($value->format(DateTimeInterface::ATOM));

        return $carbon->copy()->timezone(self::displayTimezone())->format($format);
    }

    /**
     * Timezone used to DISPLAY dates to users (America/Lima by default),
     * independent from app.timezone which governs storage.
     */
    public static function displayTimezone(): string
    {
        return (string) config('app.display_timezone', config('app.timezone', 'UTC'));
    }
}
