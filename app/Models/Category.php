<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $icon
 * @property string|null $description
 * @property bool $community_default_visible
 * @property bool $community_visibility_locked
 * @property string|null $community_visibility_help
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Category extends Model
{
    use HasFactory;
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'icon',
        'description',
        'community_default_visible',
        'community_visibility_locked',
        'community_visibility_help',
    ];

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'community_default_visible' => 'boolean',
            'community_visibility_locked' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function defaultCommunityVisible(): bool
    {
        return (bool) $this->community_default_visible;
    }

    public function locksCommunityVisibility(): bool
    {
        return (bool) $this->community_visibility_locked;
    }

    /**
     * Resolves the final community_visible value for a new ticket in this category.
     * If the category locks visibility, the reporter's requested value is ignored.
     */
    public function resolveCommunityVisibility(?bool $requested): bool
    {
        if ($this->locksCommunityVisibility()) {
            return $this->defaultCommunityVisible();
        }

        return $requested ?? $this->defaultCommunityVisible();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }

    public function incidentHistory(): HasMany
    {
        return $this->hasMany(LocationIncidentHistory::class, 'category_id');
    }
}
