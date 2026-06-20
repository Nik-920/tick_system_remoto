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
 * @property string $action
 * @property string|null $reason
 * @property string|null $performed_by
 * @property bool|null $previous_visible
 * @property bool|null $new_visible
 * @property string|null $previous_reason
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property-read Ticket $ticket
 * @property-read User|null $performedBy
 */
class CommunityModerationLog extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    public const ACTION_HIDDEN = 'hidden';

    public const ACTION_RESTORED = 'restored';

    /** @var list<string> */
    protected $fillable = [
        'ticket_id',
        'action',
        'reason',
        'performed_by',
        'previous_visible',
        'new_visible',
        'previous_reason',
        'metadata',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'previous_visible' => 'boolean',
            'new_visible' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
