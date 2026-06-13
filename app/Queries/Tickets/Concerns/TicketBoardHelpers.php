<?php

declare(strict_types=1);

namespace App\Queries\Tickets\Concerns;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Shared formatting helpers used by all ticket board/history query classes.
 */
trait TicketBoardHelpers
{
    private const BOARD_CATEGORY_ICONS = [
        'hardware' => 'monitor', 'software' => 'cpu', 'seguridad' => 'shield-alert',
        'mobiliario' => 'armchair', 'equipos' => 'projector', 'conectividad' => 'cable',
        'redes' => 'cable', 'red' => 'cable', 'electricidad' => 'zap', 'servicios' => 'droplet',
    ];

    private function priorityTone(string $priority): string
    {
        return match ($priority) {
            'critical', 'high' => 'high',
            'low' => 'low',
            default => 'medium',
        };
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'critical' => 'Crítica',
            'high' => 'Alta',
            'medium' => 'Media',
            'low' => 'Baja',
            default => ucfirst($priority),
        };
    }

    private function iconFor(?string $category): string
    {
        return self::BOARD_CATEGORY_ICONS[strtolower(trim((string) $category))] ?? 'wrench';
    }

    private function formatDuration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $rest = $minutes % 60;

        if ($days > 0) {
            return $hours > 0 ? "{$days}d {$hours}h" : "{$days}d";
        }

        if ($hours > 0) {
            return $rest > 0 ? "{$hours}h {$rest}m" : "{$hours}h";
        }

        return "{$rest}m";
    }

    private function boardReference(string $id): string
    {
        return '#'.strtoupper(substr($id, 0, 8));
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{from: int, to: int, total: int, current: int, last: int, pages: list<int>}
     */
    private function buildPagination(int $page, int $shown, int $total, int $perPage): array
    {
        $last = max(1, (int) ceil($total / $perPage));
        $from = $total === 0 ? 0 : ($page - 1) * $perPage + 1;
        $to = $total === 0 ? 0 : $from + $shown - 1;

        $start = max(1, min($page - 2, $last - 4));
        $end = min($last, $start + 4);

        return [
            'from' => $from,
            'to' => $to,
            'total' => $total,
            'current' => $page,
            'last' => $last,
            'pages' => array_values(range($start, $end)),
        ];
    }

    private function displayName(?User $user): string
    {
        if ($user === null) {
            return 'Sistema';
        }

        $name = trim((string) $user->name.' '.(string) ($user->last_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return (string) ($user->email ?? 'Usuario');
    }
}
