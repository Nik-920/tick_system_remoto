<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property string|null $assigned_by
 * @property Carbon|null $assigned_at
 * @property bool $assignment_locked
 * @property string|null $assignment_source
 * @property-read Location|null $location
 * @property-read Category|null $category
 * @property-read User|null $reporter
 * @property-read User|null $assignee
 * @property-read User|null $assignedBy
 * @property-read TicketEmbedding|null $embedding
 */
class Ticket extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATE_OPEN = 'open';

    public const STATE_IN_PROGRESS = 'in_progress';

    public const STATE_RESOLVED = 'resolved';

    public const STATE_REJECTED = 'rejected';

    public const ASSIGNMENT_SOURCE_SELF = 'self_claimed';

    public const ASSIGNMENT_SOURCE_ADMIN = 'admin_assigned';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'title',
        'description',
        'reporter_id',
        'assigned_by',
        'assigned_at',
        'assignment_locked',
        'assignment_source',
        'location_id',
        'category_id',
        'state',
        'priority',
        'resolved_at',
    ];

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'assignment_locked' => 'boolean',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected function assignmentLocked(): Attribute
    {
        return Attribute::make(
            get: fn ($value): bool => $this->normalizeBoolean($value),
            set: function (mixed $value) {
                $normalized = $this->normalizeBoolean($value);
                $driver = $this->getConnection()->getDriverName();

                if ($driver === 'pgsql') {
                    return $normalized ? 'true' : 'false';
                }

                return $normalized ? 1 : 0;
            }
        );
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(TicketEmbedding::class, 'ticket_id');
    }

    public function aiLogs(): HasMany
    {
        return $this->hasMany(TicketAiLog::class, 'ticket_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(TicketMedia::class, 'ticket_id');
    }

    public function stateHistory(): HasMany
    {
        return $this->hasMany(StateHistory::class, 'ticket_id');
    }

    public function scopeAvailableForClaim(Builder $query): Builder
    {
        return self::applyAssignmentUnlocked(
            $query
                ->where('state', self::STATE_OPEN)
                ->whereNull('assigned_to')
        );
    }

    /**
     * Tickets that a maintenance user can see:
     * - tickets assigned to them, OR
     * - tickets that are open and unassigned (available to claim).
     */
    public function scopeVisibleToMaintenance(Builder $query, string $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('assigned_to', $userId)
              ->orWhere(function (Builder $inner): void {
                  $inner->where('state', self::STATE_OPEN)
                        ->whereNull('assigned_to');
                  self::applyAssignmentUnlocked($inner);
              });
        });
    }

    /**
     * Tickets that are open and unassigned — the maintenance "claim queue".
     * Alias of availableForClaim, kept explicit for readability in queries.
     */
    public function scopeAvailableForMaintenance(Builder $query): Builder
    {
        return self::applyAssignmentUnlocked(
            $query
                ->where('state', self::STATE_OPEN)
                ->whereNull('assigned_to')
        );
    }

    private static function applyAssignmentUnlocked(Builder $query): Builder
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return $query->whereRaw('assignment_locked is false');
        }

        return $query->where('assignment_locked', false);
    }

    public function scopeAssignedToUser(Builder $query, string $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeReportedBy(Builder $query, string $userId): Builder
    {
        return $query->where('reporter_id', $userId);
    }

    public function scopeOpenOrInProgress(Builder $query): Builder
    {
        return $query->whereIn('state', [self::STATE_OPEN, self::STATE_IN_PROGRESS]);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_to');
    }

    public function embeddingText(): string
    {
        $title = trim((string) $this->title);
        $description = trim((string) $this->description);
        $state = trim((string) $this->state);

        $parts = [];
        if ($title !== '') {
            $parts[] = $title;
        }

        if ($description !== '') {
            $parts[] = $description;
        }

        if ($state !== '') {
            $parts[] = 'Estado: '.$state;
        }

        return trim(implode('. ', $parts));
    }

    private function normalizeBoolean(mixed $value): bool
    {
        $normalized = null;

        if (is_bool($value)) {
            $normalized = $value;
        } elseif (is_int($value)) {
            $normalized = $value === 1;
        } elseif (is_float($value)) {
            $normalized = (int) $value === 1;
        } elseif (is_string($value)) {
            $trimmed = trim(strtolower($value));

            if ($trimmed === 't') {
                $normalized = true;
            } elseif ($trimmed === 'f') {
                $normalized = false;
            } else {
                $filtered = filter_var($trimmed, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($filtered !== null) {
                    $normalized = $filtered;
                }
            }
        }

        return $normalized ?? (bool) $value;
    }
}
