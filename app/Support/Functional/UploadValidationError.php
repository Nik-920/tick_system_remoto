<?php

declare(strict_types=1);

namespace App\Support\Functional;

/**
 * Immutable value object representing a single upload validation failure.
 *
 * PHP 8.2 readonly enforces immutability — once created it cannot be modified,
 * which keeps the pipeline free of hidden side effects.
 *
 * @see https://www.php.net/manual/en/language.oop5.properties.php
 */
final readonly class UploadValidationError
{
    /**
     * @param  string  $code  Machine-readable error code (max_files, max_file_size,
     *                        max_total_size, invalid_extension, invalid_upload)
     * @param  string  $message  Human-readable Spanish message for the UI
     * @param  string|null  $filename  Original filename when error is per-file
     */
    public function __construct(
        public string $code,
        public string $message,
        public ?string $filename = null,
    ) {}
}
