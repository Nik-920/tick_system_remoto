<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Immutable date range for dashboard metrics.
 *
 * Resolves a preset (or a validated custom from/to pair) into normalized
 * bounds (from = start of day, to = end of day). Used by both the web
 * dashboard and the PDF report so the two never diverge.
 */
final class DateRange
{
    public const PRESET_TODAY = 'today';

    public const PRESET_LAST_7_DAYS = 'last_7_days';

    public const PRESET_LAST_30_DAYS = 'last_30_days';

    public const PRESET_THIS_MONTH = 'this_month';

    public const PRESET_PREVIOUS_MONTH = 'previous_month';

    public const PRESET_CUSTOM = 'custom';

    public const DEFAULT_PRESET = self::PRESET_LAST_30_DAYS;

    public const MAX_DAYS = 366;

    /** @var list<string> */
    public const SELECTABLE_PRESETS = [
        self::PRESET_TODAY,
        self::PRESET_LAST_7_DAYS,
        self::PRESET_LAST_30_DAYS,
        self::PRESET_THIS_MONTH,
        self::PRESET_PREVIOUS_MONTH,
        self::PRESET_CUSTOM,
    ];

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $preset,
    ) {}

    /**
     * Resolve a range from raw request input.
     *
     * Precedence: an explicit non-custom preset wins; otherwise a complete
     * custom from/to pair is used; otherwise the default (last 30 days).
     */
    public static function resolve(?string $preset, ?string $from, ?string $to): self
    {
        $preset = is_string($preset) ? trim($preset) : '';
        $from = is_string($from) ? trim($from) : '';
        $to = is_string($to) ? trim($to) : '';

        if ($preset !== '' && $preset !== self::PRESET_CUSTOM) {
            return self::preset($preset);
        }

        if ($from !== '' && $to !== '') {
            return self::custom($from, $to);
        }

        return self::default();
    }

    public static function default(): self
    {
        return self::preset(self::DEFAULT_PRESET);
    }

    public static function preset(string $preset): self
    {
        $now = CarbonImmutable::now();

        return match ($preset) {
            self::PRESET_TODAY => new self($now->startOfDay(), $now->endOfDay(), self::PRESET_TODAY),
            self::PRESET_LAST_7_DAYS => new self($now->subDays(6)->startOfDay(), $now->endOfDay(), self::PRESET_LAST_7_DAYS),
            self::PRESET_LAST_30_DAYS => new self($now->subDays(29)->startOfDay(), $now->endOfDay(), self::PRESET_LAST_30_DAYS),
            self::PRESET_THIS_MONTH => new self($now->startOfMonth(), $now->endOfMonth(), self::PRESET_THIS_MONTH),
            self::PRESET_PREVIOUS_MONTH => self::previousMonth($now),
            default => throw new InvalidArgumentException("Preset de rango no valido: {$preset}"),
        };
    }

    public static function custom(string $from, string $to): self
    {
        try {
            $start = CarbonImmutable::parse($from)->startOfDay();
            $end = CarbonImmutable::parse($to)->endOfDay();
        } catch (Throwable) {
            throw new InvalidArgumentException('Las fechas del rango no son validas.');
        }

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la final.');
        }

        if (self::inclusiveDays($start, $end) > self::MAX_DAYS) {
            throw new InvalidArgumentException('El rango no puede exceder '.self::MAX_DAYS.' dias.');
        }

        return new self($start, $end, self::PRESET_CUSTOM);
    }

    public function fromDate(): string
    {
        return $this->from->format('Y-m-d');
    }

    public function toDate(): string
    {
        return $this->to->format('Y-m-d');
    }

    public function days(): int
    {
        return self::inclusiveDays($this->from, $this->to);
    }

    public function isCustom(): bool
    {
        return $this->preset === self::PRESET_CUSTOM;
    }

    public function presetLabel(): string
    {
        return match ($this->preset) {
            self::PRESET_TODAY => 'Hoy',
            self::PRESET_LAST_7_DAYS => 'Últimos 7 días',
            self::PRESET_LAST_30_DAYS => 'Últimos 30 días',
            self::PRESET_THIS_MONTH => 'Este mes',
            self::PRESET_PREVIOUS_MONTH => 'Mes anterior',
            default => 'Personalizado',
        };
    }

    /**
     * Query-string params that reproduce this exact range (for links / PDF export).
     *
     * @return array<string, string>
     */
    public function toQueryParams(): array
    {
        if ($this->isCustom()) {
            return [
                'preset' => self::PRESET_CUSTOM,
                'from' => $this->fromDate(),
                'to' => $this->toDate(),
            ];
        }

        return ['preset' => $this->preset];
    }

    private static function previousMonth(CarbonImmutable $now): self
    {
        $previous = $now->subMonthNoOverflow();

        return new self(
            $previous->startOfMonth(),
            $previous->endOfMonth(),
            self::PRESET_PREVIOUS_MONTH,
        );
    }

    private static function inclusiveDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
    }
}
