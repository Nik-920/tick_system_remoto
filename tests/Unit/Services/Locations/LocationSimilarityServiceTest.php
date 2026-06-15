<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Locations;

use App\Models\Location;
use App\Services\Locations\LocationSimilarityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationSimilarityServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'locations.duplicate_detection_enabled' => true,
            'locations.similarity_threshold' => 0.75,
            'locations.duplicate_active_only' => true,
            'locations.max_candidates' => 100,
        ]);
    }

    public function test_find_similar_returns_exact_normalized_match(): void
    {
        $service = new LocationSimilarityService;

        $location = $this->createLocation([
            'name' => 'Laboratorio 3',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-101',
            'qr_token' => 'qr-a-101',
        ]);

        $matches = $service->findSimilar([
            'name' => 'LABORATORIO 3',
            'building' => 'Edificio A',
            'floor' => '1',
        ]);

        $this->assertCount(1, $matches);
        $this->assertSame($location->id, $matches->first()?->id);
    }

    public function test_find_similar_returns_fuzzy_match_for_lab_abbreviation(): void
    {
        $service = new LocationSimilarityService;

        $location = $this->createLocation([
            'name' => 'Laboratorio 3',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-107',
            'qr_token' => 'qr-a-107',
        ]);

        $matches = $service->findSimilar([
            'name' => 'Lab 3',
            'building' => 'Edificio A',
            'floor' => '1',
        ]);

        $this->assertCount(1, $matches);
        $this->assertSame($location->id, $matches->first()?->id);
    }

    public function test_find_similar_returns_fuzzy_match_for_similar_name(): void
    {
        config(['locations.similarity_threshold' => 0.7]);

        $service = new LocationSimilarityService;

        $location = $this->createLocation([
            'name' => 'Laboratorio de Redes',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-102',
            'qr_token' => 'qr-a-102',
        ]);

        $matches = $service->findSimilar([
            'name' => 'Laboratorio Redes',
            'building' => 'Edificio A',
            'floor' => '1',
        ]);

        $this->assertCount(1, $matches);
        $this->assertSame($location->id, $matches->first()?->id);
    }

    public function test_find_similar_returns_empty_when_detection_disabled(): void
    {
        config(['locations.duplicate_detection_enabled' => false]);

        $service = new LocationSimilarityService;

        $this->createLocation([
            'name' => 'Laboratorio 10',
            'building' => 'Edificio A',
            'floor' => '1',
        ]);

        $matches = $service->findSimilar([
            'name' => 'Laboratorio 10',
            'building' => 'Edificio A',
            'floor' => '1',
        ]);

        $this->assertCount(0, $matches);
    }

    public function test_find_similar_returns_empty_when_payload_is_incomplete(): void
    {
        $service = new LocationSimilarityService;

        $matches = $service->findSimilar([
            'name' => '',
            'building' => 'Edificio A',
        ]);

        $this->assertCount(0, $matches);
    }

    public function test_find_similar_ignores_different_building(): void
    {
        $service = new LocationSimilarityService;

        $this->createLocation([
            'name' => 'Laboratorio 3',
            'building' => 'Edificio A',
            'floor' => '1',
        ]);

        $matches = $service->findSimilar([
            'name' => 'Laboratorio 3',
            'building' => 'Edificio B',
            'floor' => '1',
        ]);

        $this->assertCount(0, $matches);
    }

    public function test_find_similar_ignores_different_floor(): void
    {
        $service = new LocationSimilarityService;

        $this->createLocation([
            'name' => 'Laboratorio 3',
            'building' => 'Edificio A',
            'floor' => '1',
        ]);

        $matches = $service->findSimilar([
            'name' => 'Laboratorio 3',
            'building' => 'Edificio A',
            'floor' => '2',
        ]);

        $this->assertCount(0, $matches);
    }

    public function test_find_similar_falls_back_when_building_format_differs(): void
    {
        $service = new LocationSimilarityService;

        $location = $this->createLocation([
            'name' => 'Laboratorio 11',
            'building' => 'Edificio-A',
            'floor' => '1',
            'room_code' => 'A-108',
            'qr_token' => 'qr-a-108',
        ]);

        $matches = $service->findSimilar([
            'name' => 'Laboratorio 11',
            'building' => 'Edificio A',
            'floor' => '1',
        ]);

        $this->assertCount(1, $matches);
        $this->assertSame($location->id, $matches->first()?->id);
    }

    public function test_find_similar_ignores_inactive_locations_when_config_enabled(): void
    {
        $service = new LocationSimilarityService;

        $this->createLocation([
            'name' => 'Laboratorio 4',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-103',
            'qr_token' => 'qr-a-103',
            'is_active' => false,
        ]);

        $matches = $service->findSimilar([
            'name' => 'Laboratorio 4',
            'building' => 'Edificio A',
            'floor' => '1',
        ]);

        $this->assertCount(0, $matches);
    }

    public function test_find_similar_includes_inactive_locations_when_config_disabled(): void
    {
        config(['locations.duplicate_active_only' => false]);

        $service = new LocationSimilarityService;

        $location = $this->createLocation([
            'name' => 'Laboratorio 5',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-104',
            'qr_token' => 'qr-a-104',
            'is_active' => false,
        ]);

        $matches = $service->findSimilar([
            'name' => 'Laboratorio 5',
            'building' => 'Edificio A',
            'floor' => '1',
        ]);

        $this->assertCount(1, $matches);
        $this->assertSame($location->id, $matches->first()?->id);
    }

    public function test_find_similar_ignores_current_location_on_update(): void
    {
        $service = new LocationSimilarityService;

        $location = $this->createLocation([
            'name' => 'Laboratorio 6',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-105',
            'qr_token' => 'qr-a-105',
        ]);

        $matches = $service->findSimilar([
            'name' => 'Laboratorio 6',
            'building' => 'Edificio A',
            'floor' => '1',
        ], $location->id);

        $this->assertCount(0, $matches);
    }

    public function test_find_similar_matches_when_floor_is_null_or_empty(): void
    {
        $service = new LocationSimilarityService;

        $location = $this->createLocation([
            'name' => 'Laboratorio 12',
            'building' => 'Edificio A',
            'floor' => null,
            'room_code' => 'A-109',
            'qr_token' => 'qr-a-109',
        ]);

        $matches = $service->findSimilar([
            'name' => 'Laboratorio 12',
            'building' => 'Edificio A',
            'floor' => '',
        ]);

        $this->assertCount(1, $matches);
        $this->assertSame($location->id, $matches->first()?->id);
    }

    public function test_normalize_floor_handles_common_variants(): void
    {
        $service = new LocationSimilarityService;

        $location = $this->createLocation([
            'name' => 'Laboratorio 7',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-106',
            'qr_token' => 'qr-a-106',
        ]);

        $floors = ['piso 1', '1er piso', 'primer piso'];

        foreach ($floors as $floor) {
            $matches = $service->findSimilar([
                'name' => 'Laboratorio 7',
                'building' => 'Edificio A',
                'floor' => $floor,
            ]);

            $this->assertCount(1, $matches);
            $this->assertSame($location->id, $matches->first()?->id);
        }
    }

    public function test_find_similar_respects_max_candidates_limit(): void
    {
        config(['locations.max_candidates' => 2]);

        $service = new LocationSimilarityService;

        for ($i = 0; $i < 5; $i++) {
            $this->createLocation([
                'name' => 'Laboratorio 8',
                'building' => 'Edificio A',
                'floor' => '1',
                'room_code' => 'A-20'.$i,
                'qr_token' => 'qr-a-20'.$i,
            ]);
        }

        // No room_code in payload → target room_code is empty → room_code differentiation
        // does not apply → all candidates with matching name/building/floor are evaluated.
        $matches = $service->findSimilar([
            'name' => 'Laboratorio 8',
            'building' => 'Edificio A',
            'floor' => '1',
        ]);

        $this->assertGreaterThan(0, $matches->count());
        $this->assertLessThanOrEqual(2, $matches->count());
    }

    public function test_find_similar_returns_empty_when_room_codes_differ(): void
    {
        $service = new LocationSimilarityService;

        $this->createLocation([
            'name' => 'Pabellon de aulas',
            'building' => '1',
            'floor' => '1',
            'room_code' => 'AUL-001',
            'qr_token' => 'qr-aul-001',
        ]);

        $this->createLocation([
            'name' => 'Pabellon de aulas',
            'building' => '1',
            'floor' => '1',
            'room_code' => 'AUL-002',
            'qr_token' => 'qr-aul-002',
        ]);

        // AUL-003 is a distinct physical space — different room_code must suppress the match.
        $matches = $service->findSimilar([
            'name' => 'Pabellon de aulas',
            'building' => '1',
            'floor' => '1',
            'room_code' => 'AUL-003',
        ]);

        $this->assertCount(0, $matches);
    }

    public function test_find_similar_still_matches_when_payload_has_no_room_code(): void
    {
        $service = new LocationSimilarityService;

        $location = $this->createLocation([
            'name' => 'Sala de reuniones',
            'building' => 'Administracion',
            'floor' => '2',
            'room_code' => 'ADM-201',
            'qr_token' => 'qr-adm-201',
        ]);

        // Payload without room_code → target room_code is empty →
        // differentiation is skipped → name/building/floor match triggers the warning.
        $matches = $service->findSimilar([
            'name' => 'Sala de reuniones',
            'building' => 'Administracion',
            'floor' => '2',
        ]);

        $this->assertCount(1, $matches);
        $this->assertSame($location->id, $matches->first()?->id);
    }

    public function test_find_similar_room_code_comparison_is_case_insensitive(): void
    {
        $service = new LocationSimilarityService;

        $this->createLocation([
            'name' => 'Laboratorio Quimica',
            'building' => 'Ciencias',
            'floor' => '3',
            'room_code' => 'CIE-301',
            'qr_token' => 'qr-cie-301',
        ]);

        // Same room_code in lowercase → normalizeRoomCode uppercases both → treated as same.
        // Both sides non-empty and equal → not suppressed → name match returns it.
        $matches = $service->findSimilar([
            'name' => 'Laboratorio Quimica',
            'building' => 'Ciencias',
            'floor' => '3',
            'room_code' => 'cie-301',
        ]);

        $this->assertCount(1, $matches);
    }

    private function createLocation(array $overrides = []): Location
    {
        $index = $this->sequence++;

        return Location::query()->create(array_merge([
            'name' => 'Laboratorio '.$index,
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-'.$index,
            'qr_token' => 'qr-a-'.$index,
            'qr_image_url' => null,
            'qr_generation_status' => 'pending',
            'qr_last_error' => null,
            'qr_job_id' => null,
            'qr_generated_at' => null,
            'is_active' => true,
        ], $overrides));
    }
}
