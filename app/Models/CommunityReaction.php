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
 * @property string $user_id
 * @property string $type
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read User $user
 */
class CommunityReaction extends Model
{
    use HasUuids;

    public const TYPE_INTERESTED = 'interested';

    public const TYPE_ALSO_HAPPENS = 'also_happens';

    public const TYPE_SEEN = 'seen';

    /** @var list<string> */
    protected $fillable = [
        'ticket_id',
        'user_id',
        'type',
    ];

    /** @return list<string> */
    public static function allowedTypes(): array
    {
        return [
            self::TYPE_INTERESTED,
            self::TYPE_ALSO_HAPPENS,
            self::TYPE_SEEN,
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
