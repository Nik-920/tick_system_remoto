<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SanitizedFileName
{
    public static function fromUploadedFile(
        UploadedFile $file,
        string $fallbackBase = 'file',
        string $fallbackExtension = 'bin',
    ): string {
        $originalName = trim((string) $file->getClientOriginalName());
        if ($originalName !== '') {
            $guessedExtension = trim((string) $file->getClientOriginalExtension());

            return self::fromRawName($originalName, $fallbackBase, $guessedExtension !== '' ? $guessedExtension : $fallbackExtension);
        }

        $fallback = self::normalizeSegment($fallbackBase, 'file', false);
        $extension = trim((string) $file->extension());
        $extension = self::normalizeSegment($extension, $fallbackExtension, true);

        return $fallback . '.' . $extension;
    }

    public static function fromRawName(
        string $rawName,
        string $fallbackBase = 'file',
        string $fallbackExtension = 'bin',
    ): string {
        $normalizedRawName = trim(str_replace('\\', '/', $rawName));
        $baseName = basename($normalizedRawName);

        $namePart = (string) pathinfo($baseName, PATHINFO_FILENAME);
        $extensionPart = (string) pathinfo($baseName, PATHINFO_EXTENSION);

        $normalizedBase = self::normalizeSegment($namePart, $fallbackBase, false);
        $normalizedExtension = self::normalizeSegment($extensionPart, $fallbackExtension, true);

        return $normalizedBase . '.' . $normalizedExtension;
    }

    private static function normalizeSegment(string $value, string $fallback, bool $extension): string
    {
        $ascii = Str::ascii($value);
        $lower = Str::lower($ascii);

        $pattern = $extension ? '/[^a-z0-9]+/' : '/[^a-z0-9._-]+/';
        $normalized = preg_replace($pattern, '-', $lower);
        $normalized = is_string($normalized) ? trim($normalized, '-_.') : '';

        if ($normalized !== '') {
            return $normalized;
        }

        $fallbackAscii = Str::ascii($fallback);
        $fallbackLower = Str::lower($fallbackAscii);
        $fallbackNormalized = preg_replace($pattern, '-', $fallbackLower);

        if (! is_string($fallbackNormalized)) {
            return $extension ? 'bin' : 'file';
        }

        $fallbackNormalized = trim($fallbackNormalized, '-_.');

        if ($fallbackNormalized === '') {
            return $extension ? 'bin' : 'file';
        }

        return $fallbackNormalized;
    }
}
