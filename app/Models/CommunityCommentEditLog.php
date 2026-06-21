<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of a single community comment edit.
 *
 * @property string $id
 * @property string $comment_id
 * @property string $ticket_id
 * @property string|null $edited_by
 * @property string $previous_body
 * @property string $new_body
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property-read CommunityComment $comment
 * @property-read Ticket $ticket
 * @property-read User|null $editedBy
 */
class CommunityCommentEditLog extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'comment_id',
        'ticket_id',
        'edited_by',
        'previous_body',
        'new_body',
        'metadata',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(CommunityComment::class, 'comment_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
