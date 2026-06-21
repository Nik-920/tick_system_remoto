<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $ticket_id
 * @property string|null $comment_id
 * @property string|null $reported_by
 * @property string $reason
 * @property string|null $note
 * @property string $status
 * @property string|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $resolution_note
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read CommunityComment|null $comment
 * @property-read User|null $reportedBy
 * @property-read User|null $reviewedBy
 */
class CommunityReport extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    public const REASON_SENSITIVE_INFO = 'sensitive_info';

    public const REASON_INAPPROPRIATE_EVIDENCE = 'inappropriate_evidence';

    public const REASON_INCORRECT_INFO = 'incorrect_info';

    public const REASON_DUPLICATE_OR_CONFUSING = 'duplicate_or_confusing';

    public const REASON_OTHER = 'other';

    /** @var list<string> */
    protected $fillable = [
        'ticket_id',
        'comment_id',
        'reported_by',
        'reason',
        'note',
        'status',
        'reviewed_by',
        'reviewed_at',
        'resolution_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public static function allowedReasons(): array
    {
        return [
            self::REASON_SENSITIVE_INFO,
            self::REASON_INAPPROPRIATE_EVIDENCE,
            self::REASON_INCORRECT_INFO,
            self::REASON_DUPLICATE_OR_CONFUSING,
            self::REASON_OTHER,
        ];
    }

    /** @return list<string> */
    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RESOLVED,
            self::STATUS_DISMISSED,
        ];
    }

    public static function reasonLabel(string $reason): string
    {
        return match ($reason) {
            self::REASON_SENSITIVE_INFO => 'Información sensible',
            self::REASON_INAPPROPRIATE_EVIDENCE => 'Evidencia no apta',
            self::REASON_INCORRECT_INFO => 'Contenido incorrecto',
            self::REASON_DUPLICATE_OR_CONFUSING => 'Duplicado o confuso',
            self::REASON_OTHER => 'Otro motivo',
            default => ucfirst(str_replace('_', ' ', $reason)),
        };
    }

    public function isCommentReport(): bool
    {
        return $this->comment_id !== null;
    }

    public function isTicketReport(): bool
    {
        return $this->comment_id === null;
    }

    public function targetLabel(): string
    {
        return $this->isCommentReport() ? 'Comentario' : 'Publicación';
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(CommunityComment::class, 'comment_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
