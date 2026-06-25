<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string $channel
 * @property bool $enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class TicketNotificationPreference extends Model
{
    use HasUuids;

    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNEL_FCM = 'fcm';

    public const TYPE_TICKET_CREATED_ADMIN = 'ticket.created.admin';

    public const TYPE_TICKET_ASSIGNED_ASSIGNEE = 'ticket.assigned.assignee';

    public const TYPE_TICKET_UNASSIGNED_ASSIGNEE = 'ticket.unassigned.assignee';

    public const TYPE_TICKET_STATE_REPORTER = 'ticket.state.reporter';

    public const TYPE_TICKET_STATE_ASSIGNEE = 'ticket.state.assignee';

    public const TYPE_TICKET_EVIDENCE_REPORTER = 'ticket.evidence.reporter';

    public const TYPE_TICKET_EVIDENCE_ASSIGNEE = 'ticket.evidence.assignee';

    public const TYPE_TICKET_COMMENT_REPORTER = 'ticket.comment.reporter';

    public const TYPE_TICKET_COMMENT_ASSIGNEE = 'ticket.comment.assignee';

    /** @var list<string> */
    protected $fillable = ['user_id', 'type', 'channel', 'enabled'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
