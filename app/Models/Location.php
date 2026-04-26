<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'building',
        'floor',
        'room_code',
        'qr_token',
        'qr_image_url',
        'qr_generation_status',
        'qr_last_error',
        'qr_job_id',
        'qr_generated_at',
        'is_active',
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
            'is_active' => 'boolean',
            'qr_generated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $this->applyActiveFilter($query, true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $this->applyActiveFilter($query, false);
    }

    public function scopeWithActiveState(Builder $query, ?bool $isActive): Builder
    {
        if ($isActive === null) {
            return $query;
        }

        return $this->applyActiveFilter($query, $isActive);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'location_id');
    }

    public function incidentHistory(): HasMany
    {
        return $this->hasMany(LocationIncidentHistory::class, 'location_id');
    }

    private function applyActiveFilter(Builder $query, bool $isActive): Builder
    {
        // Keep SQL boolean literals to avoid boolean/integer mismatches in PostgreSQL pooler mode.
        $column = $query->getQuery()->getGrammar()->wrap($this->qualifyColumn('is_active'));
        $operator = $isActive ? 'IS TRUE' : 'IS FALSE';

        return $query->whereRaw("{$column} {$operator}");
    }
}
