<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationIncidentHistory extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var string
     */
    protected $table = 'location_incident_history';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'location_id',
        'category_id',
        'last_resolved_at',
        'recurrence_count',
        'avg_resolution_time',
    ];

    /**
     * @var string
     */
    protected $keyType = 'string';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_resolved_at' => 'datetime',
            'recurrence_count' => 'integer',
            'avg_resolution_time' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
