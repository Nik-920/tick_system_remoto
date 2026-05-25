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

        $normalized = $this->normalizePayload($payload);
        if (! $this->isSearchable($normalized)) {
            return collect();
        }

        $query = $this->buildSimilarityQuery($ignoreLocationId);
        $threshold = $this->normalizedThreshold();

        $matches = $query->get()->map(function (Location $location) use ($normalized, $threshold): ?array {
            return $this->evaluateCandidate($location, $normalized, $threshold);
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
        $result = '';

        if ($normalized !== '') {
            $normalized = $this->normalizeRomanNumerals($normalized);

            if (preg_match('/\b(\d+)\b/', $normalized, $matches) === 1) {
                $result = $matches[1];
            } else {
                $result = $this->normalizeFloorWords($normalized);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{name: string, building: string, floor: string}
     */
    private function normalizePayload(array $payload): array
    {
        return [
            'name' => $this->normalizeName($payload['name'] ?? null),
            'building' => $this->normalizeBuilding($payload['building'] ?? null),
            'floor' => $this->normalizeFloor($payload['floor'] ?? null),
        ];
    }

    /** @param  array{name: string, building: string, floor: string}  $normalized */
    private function isSearchable(array $normalized): bool
    {
        return $normalized['name'] !== '' && $normalized['building'] !== '';
    }

    private function buildSimilarityQuery(?string $ignoreLocationId)
    {
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

        return $query;
    }

    /**
     * @param  array{name: string, building: string, floor: string}  $target
     * @return array{location: Location, score: float}|null
     */
    private function evaluateCandidate(Location $location, array $target, float $threshold): ?array
    {
        $match = null;

        $candidateBuilding = $this->normalizeBuilding($location->building);
        $candidateFloor = $this->normalizeFloor($location->floor);
        $candidateName = $this->normalizeName($location->name);

        $matchesScope = $candidateBuilding === $target['building']
            && $candidateFloor === $target['floor']
            && $candidateName !== '';

        if ($matchesScope) {
            if ($candidateName === $target['name']) {
                $match = [
                    'location' => $location,
                    'score' => 1.0,
                ];
            } else {
                similar_text($candidateName, $target['name'], $percent);
                $score = $percent / 100;

                if ($score >= $threshold) {
                    $match = [
                        'location' => $location,
                        'score' => $score,
                    ];
                }
            }
        }

        return $match;
    }

    private function normalizeFloorWords(string $value): string
    {
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

        $result = $value;

        foreach ($wordMap as $word => $mappedValue) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/', $value) === 1) {
                $result = $mappedValue;
                break;
            }
        }

        return $result;
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
