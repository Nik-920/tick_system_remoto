<?php

declare(strict_types=1);

namespace App\ViewModels\Dashboard\MaintenanceReport;

/**
 * One row of the "Alcance del informe" section: a named ticket universe
 * (A–I), its clock (snapshot/periodo), its count and a plain-language
 * description of what it covers.
 */
final class ScopeUniverseRow
{
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $clock,
        public readonly int $count,
        public readonly string $description,
    ) {}
}
