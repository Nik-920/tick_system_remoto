<?php

namespace App\Services\Observability;

use Illuminate\Support\Str;

class SentrySanitizer
{
    private const HASHED_KEYS = ['email', 'qr_token', 'token'];

    private const REDACTED_KEYS = [
        'authorization',
        'comment',
        'cookie',
        'description',
        'password',
        'secret',
        'title',
        'x-api-key',
    ];

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function sanitizeArray(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $stringKey = (string) $key;
            $sanitized[$stringKey] = self::sanitizeValue($stringKey, $value);
        }

        return $sanitized;
    }

    private static function sanitizeValue(string $key, mixed $value): mixed
    {
        $normalizedKey = Str::lower($key);

        if (in_array($normalizedKey, self::HASHED_KEYS, true)) {
            return self::hashValue($value);
        }

        if (in_array($normalizedKey, self::REDACTED_KEYS, true)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            return self::sanitizeArray($value);
        }

        if (is_string($value)) {
            return Str::limit($value, 500, '...');
        }

        return $value;
    }

    private static function hashValue(mixed $value): string
    {
        if (is_array($value)) {
            $encoded = json_encode($value);

            return hash('sha256', $encoded === false ? '' : $encoded);
        }

        if (is_object($value)) {
            return hash('sha256', get_class($value));
        }

        return hash('sha256', (string) $value);
    }
}
