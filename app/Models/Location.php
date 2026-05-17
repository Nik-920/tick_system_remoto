<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $building
 * @property string $floor
 * @property string $room_code
 * @property string|null $qr_token
 * @property string|null $qr_image_url
 * @property string|null $qr_generation_status
 * @property string|null $qr_last_error
 * @property string|null $qr_job_id
 * @property Carbon|null $qr_generated_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Location extends Model
{
    use HasFactory;
    use HasUuids;

    /** @var list<string> */
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

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /** @return array<string, string> */
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
        $column = $query->getQuery()->getGrammar()->wrap($this->qualifyColumn('is_active'));
        $operator = $isActive ? 'IS TRUE' : 'IS FALSE';

        return $query->whereRaw("{$column} {$operator}");
    }
}
