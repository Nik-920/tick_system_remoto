<?php

declare(strict_types=1);

namespace App\Support\Functional;

use InvalidArgumentException;

/**
 * Immutable value object for upload validation rules.
 *
 * PHP 8.2 readonly: once constructed, properties cannot change —
 * making this object safe to pass through the functional pipeline
 * without risk of mutation.
 *
 * @see https://www.php.net/manual/en/language.oop5.properties.php
 */
final readonly class UploadRules
{
    /**
     * @param  list<string>  $allowedExtensions  Lowercase extensions (e.g. ['jpg','png'])
     */
    public function __construct(
        public int $maxFiles,
        public int $maxFileSizeKb,
        public int $maxTotalSizeMb,
        public array $allowedExtensions,
        public string $maxFileSizeLabel,
        public string $maxTotalSizeLabel,
    ) {}

    /**
     * Build rules from a named config profile in config/tickets.php.
     *
     * Supported profiles: 'create', 'reporter_edit', 'maintenance'.
     */
    public static function fromConfig(string $profile): self
    {
        $cfg = config("tickets.media.{$profile}");

        if (! is_array($cfg)) {
            throw new InvalidArgumentException("Unknown upload profile: {$profile}");
        }

        $maxFileSizeKb = (int) $cfg['max_file_size_kb'];
        $maxTotalSizeMb = (int) $cfg['max_total_size_mb'];

        return new self(
            maxFiles: (int) $cfg['max_files'],
            maxFileSizeKb: $maxFileSizeKb,
            maxTotalSizeMb: $maxTotalSizeMb,
            allowedExtensions: array_map('strtolower', (array) $cfg['allowed_extensions']),
            maxFileSizeLabel: ($maxFileSizeKb / 1024).' MB',
            maxTotalSizeLabel: $maxTotalSizeMb.' MB',
        );
    }

    public function maxFileSizeBytes(): int
    {
        return $this->maxFileSizeKb * 1024;
    }

    public function maxTotalSizeBytes(): int
    {
        return $this->maxTotalSizeMb * 1024 * 1024;
    }

    /** @return list<string> */
    public function normalizedExtensions(): array
    {
        return array_values(array_map('strtolower', $this->allowedExtensions));
    }
}
