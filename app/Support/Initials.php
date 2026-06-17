<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Initials
{
    /**
     * Return word-based initials: first letter of each word, up to $limit words.
     *
     * Examples: 'Juan Pérez', 2 → 'JP' | 'Juan', 2 → 'J' | null → fallback
     */
    public static function from(?string $name, int $limit = 2, string $fallback = '?'): string
    {
        $source = trim((string) ($name ?? ''));

        if ($source === '') {
            return Str::upper($fallback);
        }

        $words = preg_split('/\s+/', $source, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) {
            return Str::upper($fallback);
        }

        $result = '';
        foreach (array_slice($words, 0, max(1, $limit)) as $word) {
            $result .= Str::upper(Str::substr($word, 0, 1));
        }

        return $result;
    }
}
