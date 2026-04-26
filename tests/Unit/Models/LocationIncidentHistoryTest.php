<?php

namespace Tests\Unit\Models;

use App\Models\LocationIncidentHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LocationIncidentHistoryTest extends TestCase
{
    public function test_factory_builds_expected_types(): void
    {
        $model = LocationIncidentHistory::factory()->make();

        $this->assertIsString($model->location_id);
        $this->assertIsString($model->category_id);
        $this->assertIsInt($model->recurrence_count);
        $this->assertIsString($model->avg_resolution_time);

        if ($model->last_resolved_at !== null) {
            $this->assertInstanceOf(Carbon::class, $model->last_resolved_at);
        }
    }

    public function test_relations_are_defined(): void
    {
        $model = new LocationIncidentHistory;

        $this->assertInstanceOf(BelongsTo::class, $model->location());
        $this->assertInstanceOf(BelongsTo::class, $model->category());
    }
}
