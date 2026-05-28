<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LocationSimilarityService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, Location>
     */
    public function findSimilar(array $payload, ?string $ignoreLocationId = null): Collection
    {
        $matches = collect();

        if (config('locations.duplicate_detection_enabled', true)) {
            $normalized = $this->normalizePayload($payload);
            if ($this->isSearchable($normalized)) {
                $candidates = $this->fetchCandidateLocations($payload, $normalized, $ignoreLocationId);
                $matches = $this->matchCandidates($candidates, $normalized, $this->normalizedThreshold());
            }
        }

        return $matches;
    }

    /**
     * @param  Collection<int, Location>  $candidates
     * @param  array{name: string, building: string, floor: string}  $target
     * @return Collection<int, Location>
     */
    private function matchCandidates(Collection $candidates, array $target, float $threshold): Collection
    {
        $matches = $candidates->map(function (Location $location) use ($target, $threshold): ?array {
            return $this->evaluateCandidate($location, $target, $threshold);
        })->filter();

        return $matches
            ->sortByDesc('score')
            ->map(static fn (array $match): Location => $match['location'])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{name: string, building: string, floor: string}  $normalized
     * @return Collection<int, Location>
     */
    private function fetchCandidateLocations(array $payload, array $normalized, ?string $ignoreLocationId): Collection
    {
        $filters = $this->extractSqlFilters($payload, $normalized);
        $maxCandidates = $this->maxCandidates();

        $candidates = $this->queryCandidatesWithFilters($ignoreLocationId, $filters, true, $maxCandidates);

        if ($candidates->isEmpty()) {
            $candidates = $this->queryCandidatesWithFilters($ignoreLocationId, $filters, false, $maxCandidates);
        }

        if ($candidates->isEmpty()) {
            $candidates = $this->buildBaseCandidateQuery($ignoreLocationId)
                ->limit($maxCandidates)
                ->get();
        }

        return $candidates;
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
            $numericFloor = $this->extractFloorNumber($normalized);
            $result = $numericFloor ?? $this->normalizeFloorWords($normalized);
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{name: string, building: string, floor: string}  $normalized
     * @return array{building_lower: ?string, floor_lower: ?string, apply_floor: bool}
     */
    private function extractSqlFilters(array $payload, array $normalized): array
    {
        $building = $this->trimToNull($payload['building'] ?? null);
        $floor = $this->trimToNull($payload['floor'] ?? null);

        return [
            'building_lower' => $this->lowerValue($building),
            'floor_lower' => $this->lowerValue($floor),
            'apply_floor' => $this->shouldApplyFloorFilter($floor, $normalized['floor']),
        ];
    }

    /**
     * @param  array{building_lower: ?string, floor_lower: ?string, apply_floor: bool}  $filters
     * @return Collection<int, Location>
     */
    private function queryCandidatesWithFilters(
        ?string $ignoreLocationId,
        array $filters,
        bool $applyFloor,
        int $limit
    ): Collection {
        $query = $this->buildBaseCandidateQuery($ignoreLocationId);

        $this->applyBuildingFilter($query, $filters['building_lower']);
        if ($applyFloor && $filters['apply_floor']) {
            $this->applyFloorFilter($query, $filters['floor_lower']);
        }

        return $query->limit($limit)->get()
            ->map(static fn (Location $location): Location => $location);
    }

    private function buildBaseCandidateQuery(?string $ignoreLocationId): Builder
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
            $query->withActiveState(true);
        }

        if ($ignoreLocationId !== null && $ignoreLocationId !== '') {
            $query->where('id', '!=', $ignoreLocationId);
        }

        return $query;
    }

    private function applyBuildingFilter(Builder $query, ?string $buildingLower): void
    {
        if ($buildingLower !== null) {
            $query->whereRaw('LOWER(building) = ?', [$buildingLower]);
        }
    }

    private function applyFloorFilter(Builder $query, ?string $floorLower): void
    {
        $query->where(function (Builder $innerQuery) use ($floorLower): void {
            if ($floorLower === null || $floorLower === '') {
                $innerQuery->whereNull('floor')->orWhere('floor', '');
            } else {
                $innerQuery->whereRaw('LOWER(floor) = ?', [$floorLower]);
            }
        });
    }

    private function maxCandidates(): int
    {
        $maxCandidates = (int) config('locations.max_candidates', 100);

        if ($maxCandidates <= 0) {
            $maxCandidates = 100;
        }

        return $maxCandidates;
    }

    private function shouldApplyFloorFilter(?string $rawFloor, string $normalizedFloor): bool
    {
        $floorValue = $rawFloor === null ? '' : trim($rawFloor);
        $apply = false;

        if ($floorValue === '') {
            $apply = true;
        } elseif ($normalizedFloor !== '' && preg_match('/^\d+$/', $floorValue) === 1) {
            $apply = true;
        } elseif ($normalizedFloor !== '' && $floorValue === $normalizedFloor) {
            $apply = true;
        }

        return $apply;
    }

    private function trimToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function lowerValue(?string $value): ?string
    {
        $lowered = null;

        if ($value !== null) {
            $lowered = mb_strtolower($value, 'UTF-8');
        }

        return $lowered;
    }

    /**
     * @param  array{name: string, building: string, floor: string}  $target
     * @return array{location: Location, score: float}|null
     */
    private function evaluateCandidate(Location $location, array $target, float $threshold): ?array
    {
        $match = null;

        $candidate = $this->candidateMatchesContext($location, $target);
        if ($candidate !== null) {
            $score = $this->calculateNameSimilarity($candidate['name'], $target['name'], $threshold);
            if ($score !== null) {
                $match = [
                    'location' => $location,
                    'score' => $score,
                ];
            }
        }

        return $match;
    }

    /**
     * @param  array{name: string, building: string, floor: string}  $target
     * @return array{name: string, building: string, floor: string}|null
     */
    private function candidateMatchesContext(Location $location, array $target): ?array
    {
        $candidate = [
            'name' => $this->normalizeName($location->name),
            'building' => $this->normalizeBuilding($location->building),
            'floor' => $this->normalizeFloor($location->floor),
        ];

        $matches = $candidate['name'] !== ''
            && $candidate['building'] === $target['building']
            && $candidate['floor'] === $target['floor'];

        return $matches ? $candidate : null;
    }

    private function calculateNameSimilarity(string $candidateName, string $targetName, float $threshold): ?float
    {
        $score = null;

        if ($candidateName === $targetName) {
            $score = 1.0;
        } else {
            similar_text($candidateName, $targetName, $percent);
            $calculatedScore = $percent / 100;

            if ($calculatedScore >= $threshold) {
                $score = $calculatedScore;
            }
        }

        return $score;
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

        foreach ($wordMap as $word => $digit) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/', $value) === 1) {
                $result = $digit;
                break;
            }
        }

        return $result;
    }

    private function extractFloorNumber(string $value): ?string
    {
        $result = null;

        if (preg_match('/\b(\d+)(?:er|ro|do|to|mo)?\b/', $value, $matches) === 1) {
            $result = $matches[1];
        }

        //  🪪  return.type
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
