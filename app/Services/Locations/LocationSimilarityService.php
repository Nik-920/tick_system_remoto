<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Models\Location;
use Illuminate\Support\Collection;

class LocationSimilarityService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, Location>
     */
    public function findSimilar(array $payload, ?string $ignoreLocationId = null): Collection
    {
        if (! config('locations.duplicate_detection_enabled', true)) {
            return collect();
        }

        $name = $this->normalizeName($payload['name'] ?? null);
        $building = $this->normalizeBuilding($payload['building'] ?? null);
        $floor = $this->normalizeFloor($payload['floor'] ?? null);

        if ($name === '' || $building === '') {
            return collect();
        }

        $query = Location::query()->select([
            'id',
            'name',
            'building',
            'floor',
            'room_code',
            'is_active',
        ]);

        if (config('locations.duplicate_active_only', true)) {
            $query->where('is_active', true);
        }

        if ($ignoreLocationId !== null && $ignoreLocationId !== '') {
            $query->where('id', '!=', $ignoreLocationId);
        }

        $threshold = $this->normalizedThreshold();

        $matches = $query->get()->map(function (Location $location) use ($name, $building, $floor, $threshold): ?array {
            $candidateBuilding = $this->normalizeBuilding($location->building);
            if ($candidateBuilding !== $building) {
                return null;
            }

            $candidateFloor = $this->normalizeFloor($location->floor);
            if ($candidateFloor !== $floor) {
                return null;
            }

            $candidateName = $this->normalizeName($location->name);
            if ($candidateName === '') {
                return null;
            }

            if ($candidateName === $name) {
                return [
                    'location' => $location,
                    'score' => 1.0,
                ];
            }

            similar_text($candidateName, $name, $percent);
            $score = $percent / 100;

            if ($score < $threshold) {
                return null;
            }

            return [
                'location' => $location,
                'score' => $score,
            ];
        })->filter();

        return $matches
            ->sortByDesc('score')
            ->map(static fn (array $match): Location => $match['location'])
            ->values();
    }

    /**
     * @param  Collection<int, Location>  $locations
     * @return array<int, array<string, mixed>>
     */
    public function formatSimilarLocations(Collection $locations): array
    {
        return $locations
            ->map(fn (Location $location): array => $this->similarLocationPayload($location))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function similarLocationPayload(Location $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'building' => $location->building,
            'floor' => $location->floor,
            'room_code' => $location->room_code,
            'is_active' => (bool) $location->is_active,
        ];
    }

    private function normalizedThreshold(): float
    {
        $threshold = (float) config('locations.similarity_threshold', 0.75);

        if ($threshold <= 0 || $threshold > 1) {
            return 0.75;
        }

        return $threshold;
    }

    private function normalizeName(?string $value): string
    {
        return $this->normalizeText($value);
    }

    private function normalizeBuilding(?string $value): string
    {
        return $this->normalizeText($value);
    }

    private function normalizeFloor(?string $value): string
    {
        $normalized = $this->normalizeBasic($value);

        if ($normalized === '') {
            return '';
        }

        $normalized = $this->normalizeRomanNumerals($normalized);

        if (preg_match('/\b(\d+)\b/', $normalized, $matches) === 1) {
            return $matches[1];
        }

        $wordMap = [
            'primer' => '1',
            'primero' => '1',
            'segundo' => '2',
            'tercero' => '3',
            'cuarto' => '4',
            'quinto' => '5',
            'sexto' => '6',
            'septimo' => '7',
            'octavo' => '8',
            'noveno' => '9',
            'decimo' => '10',
        ];

        foreach ($wordMap as $word => $value) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/', $normalized) === 1) {
                return $value;
            }
        }

        return $normalized;
    }

    private function normalizeText(?string $value): string
    {
        $normalized = $this->normalizeBasic($value);

        if ($normalized === '') {
            return '';
        }

        $normalized = $this->normalizeAbbreviations($normalized);
        $normalized = $this->normalizeRomanNumerals($normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function normalizeBasic(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = $this->transliterate($value);
        $value = str_replace(['_', '-'], ' ', $value);
        $value = preg_replace('/[^\pL\pN\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeAbbreviations(string $value): string
    {
        $value = preg_replace('/\blab\b/', 'laboratorio', $value) ?? $value;
        $value = preg_replace('/\blaborat\b/', 'laboratorio', $value) ?? $value;
        $value = preg_replace('/\bnum\b(?=\s*\d)/', 'numero', $value) ?? $value;
        $value = preg_replace('/\bno\b(?=\s*\d)/', 'numero', $value) ?? $value;
        $value = preg_replace('/\bn\b(?=\s*\d)/', 'numero', $value) ?? $value;

        return $value;
    }

    private function normalizeRomanNumerals(string $value): string
    {
        $map = [
            'x' => '10',
            'ix' => '9',
            'viii' => '8',
            'vii' => '7',
            'vi' => '6',
            'v' => '5',
            'iv' => '4',
            'iii' => '3',
            'ii' => '2',
            'i' => '1',
        ];

        foreach ($map as $roman => $digit) {
            $value = preg_replace('/\b'.preg_quote($roman, '/').'\b/', $digit, $value) ?? $value;
        }

        return $value;
    }

    private function transliterate(string $value): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return is_string($transliterated) ? $transliterated : $value;
    }
}
