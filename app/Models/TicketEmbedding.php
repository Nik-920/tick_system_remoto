<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $ticket_id
 * @property array<float> $embedding_vector
 * @property string $description_hash
 * @property float|null $similarity_score
 * @property string|null $matched_ticket_id
 * @property bool $is_duplicate
 * @property string|null $review_status null | 'confirmed' | 'dismissed'
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property-read Ticket $ticket
 * @property-read Ticket|null $matchedTicket
 * @property-read User|null $reviewer
 * @property-read bool $effective_duplicate
 */
class TicketEmbedding extends Model
{
    use HasFactory;
    use HasUuids;

    // ── Review-status constants ──────────────────────────────────────────────
    public const REVIEW_CONFIRMED = 'confirmed';

    public const REVIEW_DISMISSED = 'dismissed';

    // ── Allowed review statuses ──────────────────────────────────────────────
    /** @var list<string> */
    public const REVIEW_STATUSES = [self::REVIEW_CONFIRMED, self::REVIEW_DISMISSED];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'ticket_id',
        'embedding_vector',
        'description_hash',
        'similarity_score',
        'matched_ticket_id',
        'is_duplicate',
        // Manual review columns — never written by AI jobs
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
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
            'embedding_vector' => 'array',
            'similarity_score' => 'float',
            'is_duplicate' => 'boolean',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ── Mutator: persist bool as Postgres-native 'true'/'false' ─────────────
    protected function isDuplicate(): Attribute
    {
        return Attribute::make(
            set: function (mixed $value) {
                if ($value instanceof Expression) {
                    return $value;
                }

                if ($value === null) {
                    return null;
                }

                $driver = $this->getConnection()->getDriverName();

                if ($driver === 'pgsql') {
                    return $value ? 'true' : 'false';
                }

                return (bool) $value;
            }
        );
    }

    // ── Computed: effective duplicate (AI decision + human override) ─────────

    /**
     * Returns the *effective* duplicate flag.
     *
     * Logic:
     *  - dismissed → false  (human overrode the AI)
     *  - confirmed → true   (human confirmed it)
     *  - null      → use is_duplicate from AI
     */
    public function getEffectiveDuplicateAttribute(): bool
    {
        if ($this->review_status === self::REVIEW_DISMISSED) {
            return false;
        }

        if ($this->review_status === self::REVIEW_CONFIRMED) {
            return true;
        }

        return (bool) $this->is_duplicate;
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeEffectiveDuplicates(Builder $query): Builder
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        return $query->where(function (Builder $outer) use ($driver): void {
            $outer->where(function (Builder $aiDup) use ($driver): void {
                if ($driver === 'pgsql') {
                    $aiDup->whereRaw('is_duplicate IS TRUE');
                } else {
                    $aiDup->where('is_duplicate', true);
                }

                $aiDup->where(function (Builder $review): void {
                    $review->whereNull('review_status')
                        ->orWhere('review_status', '!=', self::REVIEW_DISMISSED);
                });
            })->orWhere('review_status', self::REVIEW_CONFIRMED);
        });
    }

    // ── Helper methods ───────────────────────────────────────────────────────

    /** Human explicitly dismissed the AI duplicate flag. */
    public function isDismissedDuplicate(): bool
    {
        return $this->review_status === self::REVIEW_DISMISSED;
    }

    /** Human explicitly confirmed the AI duplicate flag. */
    public function isConfirmedDuplicate(): bool
    {
        return $this->review_status === self::REVIEW_CONFIRMED;
    }

    /** No human review has been performed yet. */
    public function isPendingReview(): bool
    {
        return $this->review_status === null;
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function matchedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'matched_ticket_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
