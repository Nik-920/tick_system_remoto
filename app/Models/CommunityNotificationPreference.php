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
 * @property bool $enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class CommunityNotificationPreference extends Model
{
    use HasUuids;

    public const TYPE_REPORT_CREATED = 'community.report.created';

    public const TYPE_REPORT_REVIEWED = 'community.report.reviewed';

    public const TYPE_COMMENT_CREATED = 'community.comment.created';

    /** @var list<string> */
    protected $fillable = ['user_id', 'type', 'enabled'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /** @return list<string> */
    public static function allowedTypes(): array
    {
        return [
            self::TYPE_REPORT_CREATED,
            self::TYPE_REPORT_REVIEWED,
            self::TYPE_COMMENT_CREATED,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
