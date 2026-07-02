<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string|null $ticket_id
 * @property string $notification_type
 * @property string $channel
 * @property string $recipient_email
 * @property string|null $resend_email_id
 * @property string|null $resend_message_id
 * @property string $subject
 * @property string $status
 * @property string|null $dedup_key
 * @property string|null $last_event_type
 * @property Carbon|null $last_event_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $bounced_at
 * @property Carbon|null $complained_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $clicked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $user
 * @property-read Ticket|null $ticket
 */
class EmailNotificationDelivery extends Model
{
    use HasUuids;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_DELIVERY_DELAYED = 'delivery_delayed';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_COMPLAINED = 'complained';

    public const STATUS_OPENED = 'opened';

    public const STATUS_CLICKED = 'clicked';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'ticket_id',
        'notification_type',
        'channel',
        'recipient_email',
        'resend_email_id',
        'resend_message_id',
        'subject',
        'status',
        'dedup_key',
        'last_event_type',
        'last_event_at',
        'metadata',
        'sent_at',
        'delivered_at',
        'bounced_at',
        'complained_at',
        'opened_at',
        'clicked_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_event_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
