<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationBooleanCastingTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 1;

    public function test_location_is_active_accepts_true_and_reads_boolean_true(): void
    {
        $location = $this->createLocationWithActive(true);

        $this->assertTrue($location->fresh()->is_active);
        $this->assertIsBool($location->fresh()->is_active);
    }

    public function test_location_is_active_accepts_false_and_reads_boolean_false(): void
    {
        $location = $this->createLocationWithActive(false);

        $this->assertFalse($location->fresh()->is_active);
        $this->assertIsBool($location->fresh()->is_active);
    }

    public function test_location_is_active_accepts_string_one_and_reads_boolean_true(): void
    {
        $location = $this->createLocationWithActive('1');

        $this->assertTrue($location->fresh()->is_active);
        $this->assertIsBool($location->fresh()->is_active);
    }

    public function test_location_is_active_accepts_string_zero_and_reads_boolean_false(): void
    {
        $location = $this->createLocationWithActive('0');

        $this->assertFalse($location->fresh()->is_active);
        $this->assertIsBool($location->fresh()->is_active);
    }

    public function test_location_is_active_accepts_postgres_t_and_reads_boolean_true(): void
    {
        $location = $this->createLocationWithActive('t');

        $fresh = $location->fresh();

        $this->assertTrue($fresh->is_active);
        $this->assertIsBool($fresh->is_active);
    }

    public function test_location_is_active_accepts_postgres_f_and_reads_boolean_false(): void
    {
        $location = $this->createLocationWithActive('f');

        $fresh = $location->fresh();

        $this->assertFalse($fresh->is_active);
        $this->assertIsBool($fresh->is_active);
    }

    private function createLocationWithActive(mixed $isActive): Location
    {
        $index = $this->sequence++;

        return Location::query()->create([
            'name' => 'Ubicacion Bool '.$index,
            'building' => 'Edificio Bool',
            'floor' => '1',
            'room_code' => 'BOOL-'.$index,
            'qr_token' => 'qr-bool-'.$index,
            'is_active' => $isActive,
        ]);
    }
}
