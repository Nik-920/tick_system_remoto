<?php

namespace Database\Factories;

use App\Models\LocationIncidentHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LocationIncidentHistory>
 */
class LocationIncidentHistoryFactory extends Factory
{
    protected $model = LocationIncidentHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => (string) Str::uuid(),
            'category_id' => (string) Str::uuid(),
            'last_resolved_at' => $this->faker->optional()->dateTime(),
            'recurrence_count' => $this->faker->numberBetween(0, 10),
            'avg_resolution_time' => $this->faker->randomElement([
                '00:15:00',
                '00:30:00',
                '01:00:00',
            ]),
        ];
    }
}
