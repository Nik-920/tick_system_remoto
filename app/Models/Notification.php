<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property string $body
 * @property string|null $url
 * @property string|null $icon
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
class Notification extends Model
{
    use HasUuids;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'id',
        'user_id',
        'type',
        'title',
        'body',
        'url',
        'icon',
        'read_at',
        'created_at',
    ];

    /** @var string */
    protected $keyType = 'string';

    public $incrementing = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }
}
