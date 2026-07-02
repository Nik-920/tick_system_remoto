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
 * @property int|null $strategy_score
 * @property array<int, array<string, mixed>>|null $strategy_results
 * @property array<string, mixed>|null $strategy_metadata
 * @property bool $strategy_suggests_recurrence
 * @property string|null $precheck_matched_ticket_id
 * @property string|null $precheck_reason
 * @property Carbon|null $precheck_confirmed_at
 * @property-read Ticket $ticket
 * @property-read Ticket|null $matchedTicket
 * @property-read Ticket|null $precheckMatchedTicket
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
        // Strategy-engine explainability columns — AI-managed
        'strategy_score',
        'strategy_results',
        'strategy_metadata',
        'strategy_suggests_recurrence',
        // Manual review columns — never written by AI jobs
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        // Reporter precheck columns — written once at ticket-creation time only;
        // never touched by AI jobs or the admin review action.
        'precheck_matched_ticket_id',
        'precheck_reason',
        'precheck_confirmed_at',
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
            'strategy_score' => 'integer',
            'strategy_results' => 'array',
            'strategy_metadata' => 'array',
            'strategy_suggests_recurrence' => 'boolean',
            'reviewed_at' => 'datetime',
            'precheck_confirmed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ── Mutator: persist bool as Postgres-native 'true'/'false' ─────────────
    protected function isDuplicate(): Attribute
    {
        return Attribute::make(
            set: function (mixed $value) {
                $result = $value;

                if ($value instanceof Expression) {
                    $result = $value;
                } elseif ($value === null) {
                    $result = null;
                } else {
                    $driver = $this->getConnection()->getDriverName();
                    $result = $driver === 'pgsql' ? ($value ? 'true' : 'false') : (bool) $value;
                }

                return $result;
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
        $status = $this->review_status;
        $effective = (bool) $this->is_duplicate;

        if ($status === self::REVIEW_DISMISSED) {
            $effective = false;
        } elseif ($status === self::REVIEW_CONFIRMED) {
            $effective = true;
        }

        return $effective;
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

    /**
     * True when the reporter was warned about a related ticket at creation
     * time (DuplicatePrecheckService) and proceeded anyway ("caso distinto").
     * Independent of the AI lane (is_duplicate) and the admin-review lane
     * (review_status) — see the 2026_07_01_000100 migration.
     */
    public function hasPrecheckCandidate(): bool
    {
        return $this->precheck_matched_ticket_id !== null;
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

    public function precheckMatchedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'precheck_matched_ticket_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
