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
 * @property string|null $user_id
 * @property string $body
 * @property string $status
 * @property string|null $hidden_by
 * @property Carbon|null $hidden_at
 * @property string|null $hidden_reason
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Ticket $ticket
 * @property-read User|null $user
 * @property-read User|null $hiddenBy
 */
class CommunityComment extends Model
{
    use HasUuids;

    public const STATUS_VISIBLE = 'visible';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUS_DELETED = 'deleted';

    /** @var list<string> */
    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'status',
        'hidden_by',
        'hidden_at',
        'hidden_reason',
        'deleted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hidden_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_VISIBLE,
            self::STATUS_HIDDEN,
            self::STATUS_DELETED,
        ];
    }

    public function isVisible(): bool
    {
        return $this->status === self::STATUS_VISIBLE;
    }

    public function isHidden(): bool
    {
        return $this->status === self::STATUS_HIDDEN;
    }

    public function isDeleted(): bool
    {
        return $this->status === self::STATUS_DELETED;
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }
}
